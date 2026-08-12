<?php

use App\Enums\ConnectionStatus;
use App\Jobs\ProcessOperation;
use App\Models\Server;
use App\Models\User;
use App\Services\SSHService;
use App\Ssh\SshResult;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

test('process operation stores server info from the server.info recipe', function () {
    $user = User::factory()->create();
    $server = Server::factory()->for($user)->create([
        'connection_status' => ConnectionStatus::CONNECTED,
        'host_key' => 'ssh-ed25519 AAAA',
        'ssh_private_key' => "-----BEGIN OPENSSH PRIVATE KEY-----\ntest\n-----END OPENSSH PRIVATE KEY-----",
        'server_info' => null,
    ]);

    $operation = $server->operations()->create([
        'uuid' => (string) Str::uuid(),
        'user_id' => $user->id,
        'type' => 'provision',
        'status' => 'pending',
        'plan_snapshot' => [
            'profile' => 'app',
            'php_version' => '8.3',
            'steps' => [],
        ],
    ]);

    $operation->steps()->create([
        'position' => 1,
        'name' => 'Fetch server info',
        'recipe' => 'server.info@v1',
        'aftermath' => 'persist_server_info',
        'status' => 'pending',
        'arguments' => [],
    ]);

    $info = [
        'hostname' => 'web-01',
        'os' => 'debian',
        'os_version' => '13',
        'arch' => 'x86_64',
        'cpu_cores' => 4,
        'memory_total_kb' => 8192000,
    ];

    $payload = json_encode([
        'step_key' => 'server.info',
        'success' => true,
        'changed' => false,
        'data' => $info,
        'error' => [
            'code' => null,
            'message' => null,
            'details' => null,
        ],
    ], JSON_THROW_ON_ERROR);

    $this->mock(SSHService::class, function ($mock) use ($payload) {
        $mock->shouldReceive('run')
            ->once()
            ->andReturn(new SshResult(0, $payload, ''));
    });

    app()->call([new ProcessOperation($operation->id), 'handle']);

    expect($server->fresh()->server_info)->toMatchArray($info)
        ->and($operation->fresh()->status)->toBe('succeeded')
        ->and($operation->steps()->first()->status)->toBe('succeeded');
});

test('failed operations can be retried from the failed step', function () {
    Queue::fake();

    $user = User::factory()->create();
    $server = Server::factory()->for($user)->create([
        'connection_status' => ConnectionStatus::CONNECTED,
    ]);

    $operation = $server->operations()->create([
        'uuid' => (string) Str::uuid(),
        'user_id' => $user->id,
        'type' => 'provision',
        'status' => 'failed',
        'failure_message' => 'Recipe [server.info@v1] was not found.',
        'finished_at' => now(),
        'plan_snapshot' => [
            'profile' => 'app',
            'steps' => [],
        ],
    ]);

    $operation->steps()->create([
        'position' => 1,
        'name' => 'Verify server access',
        'recipe' => 'preflight.check@v1',
        'status' => 'succeeded',
        'arguments' => [],
    ]);

    $failed = $operation->steps()->create([
        'position' => 2,
        'name' => 'Fetch server info',
        'recipe' => 'server.info@v1',
        'status' => 'failed',
        'arguments' => [],
        'output' => 'Recipe [server.info@v1] was not found.',
    ]);

    $operation->steps()->create([
        'position' => 3,
        'name' => 'Install PHP',
        'recipe' => 'php.install@v1',
        'status' => 'pending',
        'arguments' => ['php_version' => '8.3'],
    ]);

    $this->actingAs($user)
        ->post(route('servers.operations.retry', [$server, $operation]))
        ->assertRedirect(route('servers.show', $server));

    $operation->refresh();
    $failed->refresh();

    expect($operation->status)->toBe('pending')
        ->and($operation->failure_message)->toBeNull()
        ->and($failed->status)->toBe('pending')
        ->and($failed->output)->toBeNull()
        ->and($operation->steps()->where('status', 'succeeded')->count())->toBe(1);

    Queue::assertPushed(ProcessOperation::class, function (ProcessOperation $job) use ($operation) {
        return $job->operationId === $operation->id;
    });
});
