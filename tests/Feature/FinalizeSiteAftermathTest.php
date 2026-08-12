<?php

use App\DTO\StepExecutionResult;
use App\Jobs\ProcessOperation;
use App\Models\Operation;
use App\Models\Release;
use App\Models\Site;
use App\Models\User;
use App\Operations\Aftermath\FinalizeSiteAftermath;
use App\Operations\Aftermath\StepAftermathRegistry;
use App\Services\SSHService;
use App\Ssh\SshResult;
use Illuminate\Support\Str;

test('finalize site aftermath activates the new release after a successful deploy step', function () {
    [$site, $operation, $release, $previous] = deployAftermathFixture();

    $step = $operation->steps()->where('recipe', 'deploy.activate@v1')->firstOrFail();

    app(StepAftermathRegistry::class)->apply(
        $site->server,
        $operation,
        $step,
        successfulStepResult('deploy.activate'),
    );

    expect($release->fresh()->status)->toBe('active')
        ->and($release->fresh()->failure_message)->toBeNull()
        ->and($release->fresh()->activated_at)->not->toBeNull()
        ->and($previous->fresh()->status)->toBe('rolled_back')
        ->and($site->fresh()->current_release_id)->toBe($release->id)
        ->and($site->fresh()->deployed_at)->not->toBeNull()
        ->and($site->fresh()->failure_message)->toBeNull();
});

test('finalize site aftermath marks a failed deploy release as failed', function () {
    [$site, $operation, $release] = deployAftermathFixture();

    app(FinalizeSiteAftermath::class)->failed($operation, 'Build exploded');

    expect($release->fresh()->status)->toBe('failed')
        ->and($release->fresh()->failure_message)->toBe('Build exploded')
        ->and($site->fresh()->current_release_id)->not->toBe($release->id);
});

test('finalize site aftermath marks a previously activated rollback target as rolled back', function () {
    [, $operation, $release] = deployAftermathFixture(type: 'rollback');

    $release->forceFill([
        'activated_at' => now()->subHour(),
        'status' => 'deploying',
    ])->save();

    app(FinalizeSiteAftermath::class)->failed($operation, 'Rollback failed');

    expect($release->fresh()->status)->toBe('rolled_back')
        ->and($release->fresh()->failure_message)->toBe('Rollback failed');
});

test('finalize site aftermath restores the previous release when activate succeeded then the operation failed', function () {
    [$site, $operation, $release, $previous] = deployAftermathFixture();

    $operation->steps()
        ->where('recipe', 'deploy.activate@v1')
        ->update(['status' => 'succeeded']);

    $this->mock(SSHService::class, function ($mock) use ($site, $previous): void {
        $mock->shouldReceive('run')
            ->once()
            ->withArgs(function ($server, $host, string $script, int $timeout) use ($site, $previous): bool {
                return $server->is($site->server)
                    && str_contains($script, 'ln -sfn')
                    && str_contains($script, $previous->commit_sha)
                    && $timeout === 60;
            })
            ->andReturn(new SshResult(0, '', ''));
    });

    app(StepAftermathRegistry::class)->apply(
        $site->server,
        $operation,
        $operation->steps()->where('recipe', 'deploy.activate@v1')->firstOrFail(),
        successfulStepResult('deploy.activate'),
    );

    expect($site->fresh()->current_release_id)->toBe($release->id);

    app(StepAftermathRegistry::class)->applyFailure($operation, 'Verify failed');

    expect($release->fresh()->status)->toBe('failed')
        ->and($release->fresh()->failure_message)->toBe('Verify failed')
        ->and($previous->fresh()->status)->toBe('active')
        ->and($site->fresh()->current_release_id)->toBe($previous->id);
});

test('process operation applies finalize site aftermath when a later deploy step fails', function () {
    [$site, $operation, $release, $previous] = deployAftermathFixture();

    $ok = json_encode(successfulStepPayload('deploy'), JSON_THROW_ON_ERROR);
    $failed = json_encode([
        'step_key' => 'deploy.verify',
        'success' => false,
        'changed' => false,
        'data' => [],
        'error' => [
            'code' => 'verify_failed',
            'message' => 'Site did not respond',
            'details' => null,
        ],
    ], JSON_THROW_ON_ERROR);

    $this->mock(SSHService::class, function ($mock) use ($ok, $failed): void {
        $mock->shouldReceive('run')
            ->andReturn(
                new SshResult(0, $ok, ''),
                new SshResult(0, $ok, ''),
                new SshResult(1, $failed, ''),
                new SshResult(0, '', ''),
            );
    });

    $operation->forceFill(['status' => 'pending'])->save();

    app()->call([new ProcessOperation($operation->id), 'handle']);

    expect($operation->fresh()->status)->toBe('failed')
        ->and($release->fresh()->status)->toBe('failed')
        ->and($release->fresh()->failure_message)->toBe('Site did not respond')
        ->and($previous->fresh()->status)->toBe('active')
        ->and($site->fresh()->current_release_id)->toBe($previous->id);
});

/**
 * @return array{0: Site, 1: Operation, 2: Release, 3: Release}
 */
function deployAftermathFixture(string $type = 'deploy'): array
{
    $user = User::factory()->create();
    $site = Site::factory()->forOwner($user)->create([
        'status' => 'active',
        'root_path' => '/home/forge/app.example.test',
        'failure_message' => 'stale',
    ]);
    $site->server->update(['host_key' => 'ssh-ed25519 AAAA']);

    $operation = $site->server->operations()->create([
        'uuid' => (string) Str::uuid(),
        'user_id' => $user->id,
        'type' => $type,
        'status' => 'running',
        'plan_snapshot' => [
            'site_id' => $site->id,
            'commit_sha' => 'newsha123',
        ],
    ]);

    $previous = Release::query()->create([
        'uuid' => (string) Str::uuid(),
        'site_id' => $site->id,
        'user_id' => $user->id,
        'commit_sha' => 'oldsha456',
        'status' => 'active',
        'activated_at' => now()->subDay(),
    ]);

    $release = Release::query()->create([
        'uuid' => (string) Str::uuid(),
        'site_id' => $site->id,
        'user_id' => $user->id,
        'operation_id' => $operation->id,
        'commit_sha' => 'newsha123',
        'status' => 'deploying',
    ]);

    $operation->forceFill([
        'plan_snapshot' => [
            ...$operation->plan_snapshot,
            'release_id' => $release->id,
        ],
    ])->save();

    $site->forceFill(['current_release_id' => $previous->id])->save();

    $args = [
        'previous_sha' => $previous->commit_sha,
        'commit_sha' => $release->commit_sha,
    ];

    $operation->steps()->create([
        'position' => 1,
        'name' => 'Clone release',
        'recipe' => 'deploy.clone@v1',
        'status' => 'pending',
        'arguments' => $args,
    ]);

    $operation->steps()->create([
        'position' => 2,
        'name' => 'Activate release',
        'recipe' => 'deploy.activate@v1',
        'aftermath' => FinalizeSiteAftermath::key(),
        'status' => 'pending',
        'arguments' => $args,
    ]);

    $operation->steps()->create([
        'position' => 3,
        'name' => 'Verify site',
        'recipe' => 'deploy.verify@v1',
        'status' => 'pending',
        'arguments' => $args,
    ]);

    return [$site->fresh(['server']), $operation->fresh(), $release, $previous];
}

function successfulStepResult(string $stepKey): StepExecutionResult
{
    return StepExecutionResult::fromArray(successfulStepPayload($stepKey));
}

/**
 * @return array{
 *     step_key: string,
 *     success: bool,
 *     changed: bool,
 *     data: array<empty, empty>,
 *     error: array{code: null, message: null, details: null}
 * }
 */
function successfulStepPayload(string $stepKey): array
{
    return [
        'step_key' => $stepKey,
        'success' => true,
        'changed' => true,
        'data' => [],
        'error' => [
            'code' => null,
            'message' => null,
            'details' => null,
        ],
    ];
}
