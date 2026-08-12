<?php

use App\Enums\ConnectionStatus;
use App\Http\Controllers\ServerSSHController;
use App\Models\Server;
use App\Models\User;
use App\Services\SSHService;
use App\Ssh\HostFingerprint;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery;

test('server show page includes ssh connection props', function () {
    $user = User::factory()->create();
    $server = Server::factory()->for($user)->create([
        'name' => 'web-01',
        'ssh_public_key' => 'ssh-ed25519 AAAA example',
        'host_key_fingerprint' => 'SHA256:managementkey',
    ]);

    session()->put(ServerSSHController::sessionKey($server), [
        'key' => 'ssh-ed25519 AAAA hostkey',
        'fingerprint' => 'SHA256:discoveredhost',
    ]);

    $this->actingAs($user)
        ->get(route('servers.show', $server))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('servers/Show')
            ->where('server.uuid', $server->uuid)
            ->where('server.name', 'web-01')
            ->where('server.has_management_key', true)
            ->where('server.ssh_public_key', 'ssh-ed25519 AAAA example')
            ->where('discoveredFingerprint', 'SHA256:discoveredhost')
            ->missing('server.ssh_private_key'));
});

test('users cannot start ssh connect for another users server', function () {
    $user = User::factory()->create();
    $server = Server::factory()->create();

    $this->actingAs($user)
        ->post(route('servers.ssh.connect', $server))
        ->assertNotFound();
});

test('connect generates a management key and redirects to show', function () {
    $user = User::factory()->create();
    $server = Server::factory()->for($user)->create([
        'name' => 'debian-vm',
        'host' => '192.168.56.101',
    ]);

    $this->mock(SSHService::class, function ($mock) use ($server) {
        $mock->shouldReceive('generateKeyPair')
            ->once()
            ->with(Mockery::on(fn (Server $value) => $value->is($server)))
            ->andReturn([
                'host_key_fingerprint' => 'SHA256:management',
                'known_hosts_path' => '/tmp/known_hosts',
                'ssh_public_key' => 'ssh-ed25519 AAAA public',
                'ssh_private_key' => '/tmp/id_ed25519',
            ]);
    });

    $this->actingAs($user)
        ->post(route('servers.ssh.connect', $server))
        ->assertRedirect(route('servers.show', $server));

    $server->refresh();

    expect($server->ssh_public_key)->toBe('ssh-ed25519 AAAA public')
        ->and($server->host_key_fingerprint)->toBe('SHA256:management');
});

test('verify stores discovered fingerprint in session', function () {
    $user = User::factory()->create();
    $server = Server::factory()->for($user)->create([
        'ssh_public_key' => 'ssh-ed25519 AAAA public',
    ]);

    $this->mock(SSHService::class, function ($mock) use ($server) {
        $mock->shouldReceive('discoverHost')
            ->once()
            ->with(Mockery::on(fn (Server $value) => $value->is($server)))
            ->andReturn(new HostFingerprint('ssh-ed25519 AAAA host', 'SHA256:hostfp'));
    });

    $this->actingAs($user)
        ->post(route('servers.ssh.verify', $server))
        ->assertRedirect(route('servers.show', $server))
        ->assertSessionHas(ServerSSHController::sessionKey($server), [
            'key' => 'ssh-ed25519 AAAA host',
            'fingerprint' => 'SHA256:hostfp',
        ]);
});

test('confirm trusts fingerprint and marks server connected', function () {
    $user = User::factory()->create();
    $server = Server::factory()->for($user)->create([
        'ssh_public_key' => 'ssh-ed25519 AAAA public',
        'ssh_private_key' => 'private-key-material',
        'connection_status' => ConnectionStatus::UNVERIFIED,
    ]);

    $sessionKey = ServerSSHController::sessionKey($server);

    session()->put($sessionKey, [
        'key' => 'ssh-ed25519 AAAA host',
        'fingerprint' => 'SHA256:hostfp',
    ]);

    $this->mock(SSHService::class, function ($mock) use ($server) {
        $mock->shouldReceive('verifyConnection')
            ->once()
            ->with(
                Mockery::on(fn (Server $value) => $value->is($server)),
                Mockery::on(fn (HostFingerprint $host) => $host->fingerprint === 'SHA256:hostfp' && $host->key === 'ssh-ed25519 AAAA host'),
            );
    });

    $this->actingAs($user)
        ->withSession([$sessionKey => [
            'key' => 'ssh-ed25519 AAAA host',
            'fingerprint' => 'SHA256:hostfp',
        ]])
        ->post(route('servers.ssh.confirm', $server))
        ->assertRedirect(route('servers.show', $server));

    $server->refresh();

    expect($server->connection_status)->toBe(ConnectionStatus::CONNECTED)
        ->and($server->host_key)->toBe('ssh-ed25519 AAAA host')
        ->and($server->host_key_fingerprint)->toBe('SHA256:hostfp')
        ->and($server->verified_at)->not->toBeNull();
});

test('confirm requires a discovered fingerprint in session', function () {
    $user = User::factory()->create();
    $server = Server::factory()->for($user)->create([
        'ssh_public_key' => 'ssh-ed25519 AAAA public',
    ]);

    $this->actingAs($user)
        ->post(route('servers.ssh.confirm', $server))
        ->assertRedirect()
        ->assertSessionHasErrors('connection');
});
