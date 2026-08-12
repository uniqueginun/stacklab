<?php

use App\Jobs\ProcessOperation;
use App\Models\GitHubConnection;
use App\Models\Operation;
use App\Models\Release;
use App\Models\Site;
use App\Models\User;
use App\Operations\Aftermath\FinalizeSiteAftermath;
use App\Support\SiteDeploymentOptions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

function laravelDeployOptions(array $overrides = []): array
{
    return [
        ...SiteDeploymentOptions::defaults(),
        ...$overrides,
    ];
}

test('guests cannot start a deployment', function () {
    $site = Site::factory()->create();

    $this->post(route('sites.deployments.store', $site))
        ->assertRedirect(route('login'));
});

test('users cannot deploy another users site', function () {
    $site = Site::factory()->create();
    $other = User::factory()->create();

    $this->actingAs($other)
        ->post(route('sites.deployments.store', $site), laravelDeployOptions())
        ->assertForbidden();
});

test('deploying requires a github connection', function () {
    $user = User::factory()->create();
    $site = Site::factory()->forOwner($user)->create([
        'status' => 'active',
        'repository_url' => 'acme/app',
        'repository_branch' => 'main',
    ]);

    $this->actingAs($user)
        ->from(route('sites.show', $site))
        ->post(route('sites.deployments.store', $site), laravelDeployOptions())
        ->assertRedirect(route('sites.show', $site))
        ->assertSessionHasErrors('site');
});

test('deploying requires an attached github repository', function () {
    $user = User::factory()->create();
    $site = Site::factory()->forOwner($user)->create(['status' => 'active']);
    GitHubConnection::factory()->for($user)->create();

    $this->actingAs($user)
        ->from(route('sites.show', $site))
        ->post(route('sites.deployments.store', $site), laravelDeployOptions())
        ->assertRedirect(route('sites.show', $site))
        ->assertSessionHasErrors('site');
});

test('deploying requires an active site', function () {
    $user = User::factory()->create();
    $site = Site::factory()->forOwner($user)->create([
        'status' => 'pending',
        'repository_url' => 'acme/app',
        'repository_branch' => 'main',
    ]);
    GitHubConnection::factory()->for($user)->create();

    $this->actingAs($user)
        ->from(route('sites.show', $site))
        ->post(route('sites.deployments.store', $site), laravelDeployOptions())
        ->assertRedirect(route('sites.show', $site))
        ->assertSessionHasErrors('site');
});

test('users can deploy a laravel site and persist deploy options', function () {
    Queue::fake();
    Http::preventStrayRequests();
    Http::fake([
        'https://api.github.com/repos/acme/app/commits/main' => Http::response([
            'sha' => 'abc123def4567890',
            'commit' => ['message' => 'Ship it'],
        ]),
    ]);

    $user = User::factory()->create();
    $site = Site::factory()->forOwner($user)->create([
        'domain' => 'api.example.test',
        'status' => 'active',
        'type' => 'laravel',
        'repository_url' => 'acme/app',
        'repository_branch' => 'main',
    ]);
    GitHubConnection::factory()->for($user)->create();

    $options = laravelDeployOptions([
        'run_npm' => false,
        'run_migrations' => false,
    ]);

    $this->actingAs($user)
        ->post(route('sites.deployments.store', $site), $options)
        ->assertRedirect(route('sites.show', [
            'site' => $site,
            'tab' => 'deployment',
        ]));

    $site->refresh();
    $operation = Operation::query()->where('server_id', $site->server_id)->first();

    expect($operation)->not->toBeNull();

    $release = Release::query()->where('site_id', $site->id)->first();
    $clone = $operation->steps->firstWhere('recipe', 'deploy.clone@v1');
    $activate = $operation->steps->firstWhere('recipe', 'deploy.activate@v1');

    expect($site->deployment_options)->toMatchArray($options)
        ->and($operation->type)->toBe('deploy')
        ->and($operation->status)->toBe('pending')
        ->and(data_get($operation->plan_snapshot, 'site_public_id'))->toBe($site->uuid)
        ->and(data_get($operation->plan_snapshot, 'release_id'))->toBe($release?->id)
        ->and($clone)->not->toBeNull()
        ->and($clone->arguments['repository'])->toBe('acme/app')
        ->and($clone->arguments['branch'])->toBe('main')
        ->and($clone->arguments['site_public_id'])->toBe($site->uuid)
        ->and($clone->arguments['run_npm'])->toBe('0')
        ->and($clone->arguments['run_migrations'])->toBe('0')
        ->and($clone->arguments['run_composer'])->toBe('1')
        ->and($activate)->not->toBeNull()
        ->and($activate->aftermath)->toBe(FinalizeSiteAftermath::key())
        ->and($release)->not->toBeNull()
        ->and($release->commit_sha)->toBe('abc123def4567890')
        ->and($release->commit_message)->toBe('Ship it')
        ->and($release->status)->toBe('deploying');

    Queue::assertPushed(ProcessOperation::class, function (ProcessOperation $job) use ($operation): bool {
        return $job->operationId === $operation->id;
    });
});

test('php sites can deploy without laravel deploy options', function () {
    Queue::fake();
    Http::preventStrayRequests();
    Http::fake([
        'https://api.github.com/repos/acme/app/commits/main' => Http::response([
            'sha' => 'deadbeefcafebabe',
            'commit' => ['message' => 'PHP site'],
        ]),
    ]);

    $user = User::factory()->create();
    $site = Site::factory()->forOwner($user)->create([
        'status' => 'active',
        'type' => 'php',
        'repository_url' => 'acme/app',
        'repository_branch' => 'main',
    ]);
    GitHubConnection::factory()->for($user)->create();

    $this->actingAs($user)
        ->post(route('sites.deployments.store', $site))
        ->assertRedirect(route('sites.show', [
            'site' => $site,
            'tab' => 'deployment',
        ]));

    $operation = Operation::query()->where('server_id', $site->server_id)->first();

    expect($operation)->not->toBeNull();

    $clone = $operation->steps->firstWhere('recipe', 'deploy.clone@v1');

    expect($site->fresh()->deployment_options)->toBeNull()
        ->and($clone)->not->toBeNull()
        ->and($clone->arguments['repository'])->toBe('acme/app')
        ->and($clone->arguments['branch'])->toBe('main')
        ->and($clone->arguments['run_migrations'])->toBe('1')
        ->and($clone->arguments['run_composer'])->toBe('1')
        ->and($clone->arguments['run_queue_restart'])->toBe('0')
        ->and($clone->arguments['run_hook'])->toBe('0');
});

test('new sites default to the necessary laravel deploy options', function () {
    $user = User::factory()->create();
    $site = Site::factory()->forOwner($user)->create();

    $this->actingAs($user)
        ->get(route('sites.show', $site))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('sites/Show')
            ->where('site.deployment_options.run_composer', true)
            ->where('site.deployment_options.run_npm', true)
            ->where('site.deployment_options.run_migrations', true)
            ->where('site.deployment_options.run_caches', true)
            ->where('site.deployment_options.run_queue_restart', false)
            ->where('site.deployment_options.run_hook', false));
});

test('site show includes resolved deployment options', function () {
    $user = User::factory()->create();
    $site = Site::factory()->forOwner($user)->create([
        'deployment_options' => [
            'run_npm' => false,
        ],
    ]);

    $this->actingAs($user)
        ->get(route('sites.show', $site))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('sites/Show')
            ->where('site.deployment_options.run_npm', false)
            ->where('site.deployment_options.run_composer', true)
            ->where('site.deployment_options.run_migrations', true)
            ->where('site.deployment_options.run_queue_restart', false)
            ->where('site.deployment_options.run_hook', false));
});
