<?php

use App\DTO\StepExecutionResult;
use App\Enums\ConnectionStatus;
use App\Models\Operation;
use App\Models\Server;
use App\Models\User;
use App\Operations\Aftermath\PersistServerInfoAftermath;
use App\Operations\Aftermath\StepAftermathRegistry;
use App\Support\ProvisioningProfiles;
use Illuminate\Support\Str;
use RuntimeException;

test('provisioning profiles attach aftermath keys to steps', function () {
    $steps = app(ProvisioningProfiles::class)->steps('app');

    $infoStep = collect($steps)->firstWhere('recipe', 'server.info@v1');

    expect($infoStep)->not->toBeNull()
        ->and($infoStep['aftermath'])->toBe(PersistServerInfoAftermath::key());
});

test('step aftermath registry runs the handler declared on the step', function () {
    $user = User::factory()->create();
    $server = Server::factory()->for($user)->create([
        'connection_status' => ConnectionStatus::CONNECTED,
        'server_info' => null,
    ]);

    $operation = $server->operations()->create([
        'uuid' => (string) Str::uuid(),
        'user_id' => $user->id,
        'type' => 'provision',
        'status' => 'running',
        'plan_snapshot' => ['profile' => 'app'],
    ]);

    $step = $operation->steps()->create([
        'position' => 1,
        'name' => 'Fetch server info',
        'recipe' => 'server.info@v1',
        'aftermath' => PersistServerInfoAftermath::key(),
        'status' => 'succeeded',
        'arguments' => [],
    ]);

    $info = [
        'hostname' => 'web-01',
        'os' => 'debian',
        'cpu_cores' => 2,
    ];

    app(StepAftermathRegistry::class)->apply(
        $server,
        $operation,
        $step,
        StepExecutionResult::fromArray([
            'step_key' => 'server.info',
            'success' => true,
            'changed' => false,
            'data' => $info,
            'error' => ['code' => null, 'message' => null, 'details' => null],
            'output' => null,
        ]),
    );

    expect($server->fresh()->server_info)->toMatchArray($info);
});

test('step aftermath registry ignores steps without an aftermath', function () {
    $user = User::factory()->create();
    $server = Server::factory()->for($user)->create([
        'server_info' => null,
    ]);

    $operation = $server->operations()->create([
        'uuid' => (string) Str::uuid(),
        'user_id' => $user->id,
        'type' => 'provision',
        'status' => 'running',
        'plan_snapshot' => ['profile' => 'web'],
    ]);

    $step = $operation->steps()->create([
        'position' => 1,
        'name' => 'Install PHP',
        'recipe' => 'php.install@v1',
        'aftermath' => null,
        'status' => 'succeeded',
        'arguments' => [],
    ]);

    app(StepAftermathRegistry::class)->apply(
        $server,
        $operation,
        $step,
        StepExecutionResult::fromArray([
            'step_key' => 'php.install',
            'success' => true,
            'changed' => true,
            'data' => ['php_version' => '8.3'],
            'error' => ['code' => null, 'message' => null, 'details' => null],
        ]),
    );

    expect($server->fresh()->server_info)->toBeNull();
});

test('step aftermath registry skips failure handling when the handler does not support it', function () {
    $user = User::factory()->create();
    $server = Server::factory()->for($user)->create([
        'server_info' => null,
    ]);

    $operation = $server->operations()->create([
        'uuid' => (string) Str::uuid(),
        'user_id' => $user->id,
        'type' => 'provision',
        'status' => 'failed',
        'plan_snapshot' => ['profile' => 'app'],
    ]);

    $operation->steps()->create([
        'position' => 1,
        'name' => 'Fetch server info',
        'recipe' => 'server.info@v1',
        'aftermath' => PersistServerInfoAftermath::key(),
        'status' => 'failed',
        'arguments' => [],
    ]);

    app(StepAftermathRegistry::class)->applyFailure($operation, 'SSH timed out');

    expect($server->fresh()->server_info)->toBeNull();
});

test('step aftermath registry fails when the step key is unknown', function () {
    $user = User::factory()->create();
    $server = Server::factory()->for($user)->create();
    $operation = Operation::query()->make();
    $step = $operation->steps()->make([
        'aftermath' => 'missing_handler',
    ]);

    app(StepAftermathRegistry::class)->apply(
        $server,
        $operation,
        $step,
        StepExecutionResult::fromArray([
            'step_key' => 'x',
            'success' => true,
            'changed' => false,
            'data' => [],
            'error' => ['code' => null, 'message' => null, 'details' => null],
        ]),
    );
})->throws(RuntimeException::class, 'No step aftermath handler registered for [missing_handler].');
