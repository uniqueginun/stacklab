<?php

use App\Enums\ConnectionStatus;
use App\Jobs\ProcessOperation;
use App\Models\Operation;
use App\Models\Server;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

test('connected servers can start provisioning with a profile and php version', function () {
    Queue::fake();

    $user = User::factory()->create();
    $server = Server::factory()->for($user)->create([
        'connection_status' => ConnectionStatus::CONNECTED,
        'host_key' => 'ssh-ed25519 AAAA',
        'host_key_fingerprint' => 'SHA256:test',
    ]);

    $this->actingAs($user)
        ->post(route('servers.provision', $server), [
            'profile' => 'web',
            'php_version' => '8.3',
        ])
        ->assertRedirect(route('servers.show', $server));

    $operation = Operation::query()->where('server_id', $server->id)->first();

    expect($operation)->not->toBeNull()
        ->and($operation->type)->toBe('provision')
        ->and($operation->status)->toBe('pending')
        ->and(data_get($operation->plan_snapshot, 'profile'))->toBe('web')
        ->and(data_get($operation->plan_snapshot, 'php_version'))->toBe('8.3')
        ->and($operation->steps)->toHaveCount(6);

    expect($operation->steps->firstWhere('recipe', 'php.install@v1')->arguments)
        ->toBe(['php_version' => '8.3']);

    expect($operation->steps->firstWhere('recipe', 'server.info@v1'))->toBeNull();

    Queue::assertPushed(ProcessOperation::class, function (ProcessOperation $job) use ($operation) {
        return $job->operationId === $operation->id;
    });
});

test('cache profile does not require php version', function () {
    Queue::fake();

    $user = User::factory()->create();
    $server = Server::factory()->for($user)->create([
        'connection_status' => ConnectionStatus::CONNECTED,
        'host_key' => 'ssh-ed25519 AAAA',
    ]);

    $this->actingAs($user)
        ->post(route('servers.provision', $server), [
            'profile' => 'cache',
        ])
        ->assertRedirect(route('servers.show', $server));

    $operation = Operation::query()->where('server_id', $server->id)->first();

    expect($operation)->not->toBeNull()
        ->and(data_get($operation->plan_snapshot, 'php_version'))->toBeNull()
        ->and($operation->steps->contains(fn ($step) => $step->recipe === 'php.install@v1'))->toBeFalse();
});

test('web profile requires a php version', function () {
    $user = User::factory()->create();
    $server = Server::factory()->for($user)->create([
        'connection_status' => ConnectionStatus::CONNECTED,
        'host_key' => 'ssh-ed25519 AAAA',
    ]);

    $this->actingAs($user)
        ->from(route('servers.show', $server))
        ->post(route('servers.provision', $server), [
            'profile' => 'web',
        ])
        ->assertRedirect(route('servers.show', $server))
        ->assertSessionHasErrors('php_version');
});

test('users cannot provision another users server', function () {
    $user = User::factory()->create();
    $server = Server::factory()->create([
        'connection_status' => ConnectionStatus::CONNECTED,
    ]);

    $this->actingAs($user)
        ->post(route('servers.provision', $server), [
            'profile' => 'cache',
        ])
        ->assertNotFound();
});

test('unconnected servers cannot be provisioned', function () {
    $user = User::factory()->create();
    $server = Server::factory()->for($user)->create([
        'connection_status' => ConnectionStatus::UNVERIFIED,
    ]);

    $this->actingAs($user)
        ->from(route('servers.show', $server))
        ->post(route('servers.provision', $server), [
            'profile' => 'cache',
        ])
        ->assertRedirect(route('servers.show', $server))
        ->assertSessionHasErrors('server');
});

test('server show includes provisioning props and latest operation', function () {
    $user = User::factory()->create();
    $server = Server::factory()->for($user)->create([
        'connection_status' => ConnectionStatus::CONNECTED,
        'profile' => null,
    ]);

    $operation = $server->operations()->create([
        'uuid' => (string) Str::uuid(),
        'user_id' => $user->id,
        'type' => 'provision',
        'status' => 'running',
        'plan_snapshot' => [
            'profile' => 'cache',
            'php_version' => null,
            'steps' => [],
        ],
        'started_at' => now(),
    ]);

    $operation->steps()->create([
        'position' => 1,
        'name' => 'Verify server access',
        'recipe' => 'preflight.check@v1',
        'status' => 'running',
        'arguments' => [],
        'output' => "checking sudo\nok",
    ]);

    $this->actingAs($user)
        ->get(route('servers.show', $server))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('servers/Show')
            ->where('operation.uuid', $operation->uuid)
            ->where('operation.status', 'running')
            ->has('operation.steps', 1)
            ->where('operation.steps.0.output', "checking sudo\nok")
            ->has('provisioningProfiles')
            ->has('phpVersions')
            ->where('defaultPhpVersion', '8.3'));
});
