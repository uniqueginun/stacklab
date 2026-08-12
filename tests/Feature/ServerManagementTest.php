<?php

use App\Models\Server;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('authenticated users can view their servers index', function () {
    $user = User::factory()->create();
    $server = Server::factory()->for($user)->create([
        'name' => 'slab-01',
    ]);
    Server::factory()->create([
        'name' => 'other-user-server',
    ]);

    $this->actingAs($user)
        ->get(route('servers.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('servers/Index')
            ->has('servers', 1)
            ->where('servers.0.uuid', $server->uuid)
            ->where('servers.0.name', 'slab-01'));
});

test('authenticated users can create a server and are redirected to show', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('servers.store'), [
        'name' => 'production-01',
        'provider' => 'hetzner',
        'host' => '203.0.113.10',
        'ssh_port' => 22,
        'ssh_user' => 'root',
    ]);

    $server = Server::query()->where('name', 'production-01')->first();

    expect($server)->not->toBeNull()
        ->and($server->user_id)->toBe($user->id)
        ->and($server->uuid)->not->toBeEmpty();

    $response->assertRedirect(route('servers.show', $server));
});

test('authenticated users can view their own server show page', function () {
    $user = User::factory()->create();
    $server = Server::factory()->for($user)->create([
        'name' => 'production-01',
    ]);

    $this->actingAs($user)
        ->get(route('servers.show', $server))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('servers/Show')
            ->where('server.uuid', $server->uuid)
            ->where('server.name', 'production-01')
            ->where('server.has_management_key', false)
            ->where('discoveredFingerprint', null)
            ->where('operation', null)
            ->has('provisioningProfiles')
            ->has('phpVersions'));
});

test('users cannot view another users server', function () {
    $user = User::factory()->create();
    $server = Server::factory()->create();

    $this->actingAs($user)
        ->get(route('servers.show', $server))
        ->assertNotFound();
});
