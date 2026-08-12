<?php

use App\Models\GitHubConnection;
use App\Models\Site;
use App\Models\User;
use App\Services\SSHService;
use App\Ssh\SshResult;
use Illuminate\Support\Facades\Http;

test('guests cannot view the repository catalog', function () {
    $site = Site::factory()->create();

    $this->get(route('sites.repository.edit', $site))
        ->assertRedirect(route('login'));
});

test('users cannot view another users repository catalog', function () {
    $site = Site::factory()->create();
    $other = User::factory()->create();

    $this->actingAs($other)
        ->get(route('sites.repository.edit', $site))
        ->assertForbidden();
});

test('the repository catalog is empty when github is not connected', function () {
    $user = User::factory()->create();
    $site = Site::factory()->forOwner($user)->create();

    $this->actingAs($user)
        ->get(route('sites.repository.edit', $site))
        ->assertOk()
        ->assertJson([
            'githubConnected' => false,
            'repository' => null,
            'branch' => null,
            'repositories' => [],
            'branches' => [],
        ]);
});

test('the repository catalog lists github repositories', function () {
    Http::preventStrayRequests();
    Http::fake([
        'https://api.github.com/user/repos*' => Http::response([
            [
                'id' => 42,
                'full_name' => 'acme/app',
                'private' => false,
                'default_branch' => 'main',
            ],
        ]),
    ]);

    $user = User::factory()->create();
    $site = Site::factory()->forOwner($user)->create();
    GitHubConnection::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route('sites.repository.edit', $site))
        ->assertOk()
        ->assertJson([
            'githubConnected' => true,
            'repositories' => [
                [
                    'id' => 42,
                    'full_name' => 'acme/app',
                    'private' => false,
                    'default_branch' => 'main',
                ],
            ],
            'branches' => [],
        ]);
});

test('the repository catalog lists branches for a selected repository', function () {
    Http::preventStrayRequests();
    Http::fake([
        'https://api.github.com/user/repos*' => Http::response([
            [
                'id' => 42,
                'full_name' => 'acme/app',
                'private' => false,
                'default_branch' => 'main',
            ],
        ]),
        'https://api.github.com/repos/acme/app/branches*' => Http::response([
            ['name' => 'main'],
            ['name' => 'develop'],
        ]),
    ]);

    $user = User::factory()->create();
    $site = Site::factory()->forOwner($user)->create([
        'repository_url' => 'acme/app',
        'repository_branch' => 'main',
    ]);
    GitHubConnection::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route('sites.repository.edit', [
            'site' => $site,
            'repository' => 'acme/app',
        ]))
        ->assertOk()
        ->assertJson([
            'githubConnected' => true,
            'repository' => 'acme/app',
            'branch' => 'main',
            'branches' => [
                ['name' => 'main'],
                ['name' => 'develop'],
            ],
        ]);
});

test('attaching a repository requires a repository and branch', function () {
    $user = User::factory()->create();
    $site = Site::factory()->forOwner($user)->create(['status' => 'active']);

    $this->actingAs($user)
        ->from(route('sites.show', $site))
        ->put(route('sites.repository.update', $site), [])
        ->assertRedirect(route('sites.show', $site))
        ->assertSessionHasErrors(['repository', 'branch']);
});

test('attaching a repository requires a github connection', function () {
    $user = User::factory()->create();
    $site = Site::factory()->forOwner($user)->create(['status' => 'active']);

    $this->actingAs($user)
        ->from(route('sites.show', $site))
        ->put(route('sites.repository.update', $site), [
            'repository' => 'acme/app',
            'branch' => 'main',
        ])
        ->assertRedirect(route('sites.show', $site))
        ->assertSessionHasErrors('repository');
});

test('users can attach a github repository to an active site', function () {
    Http::preventStrayRequests();
    Http::fake([
        'https://api.github.com/repos/acme/app' => Http::response([
            'id' => 42,
            'full_name' => 'acme/app',
            'private' => false,
            'default_branch' => 'main',
        ]),
        'https://api.github.com/repos/acme/app/branches*' => Http::response([
            ['name' => 'main'],
            ['name' => 'develop'],
        ]),
        'https://api.github.com/repos/acme/app/keys' => Http::response([
            'id' => 99,
            'title' => 'mini-forge:api.example.test',
            'key' => 'ssh-ed25519 AAAA',
            'fingerprint' => 'SHA256:abc',
        ], 201),
    ]);

    $this->mock(SSHService::class, function ($mock) {
        $mock->shouldReceive('run')
            ->once()
            ->andReturn(new SshResult(0, "DEPLOY_KEY_INSTALLED\n"));
    });

    $user = User::factory()->create();
    $site = Site::factory()->forOwner($user)->create([
        'domain' => 'api.example.test',
        'status' => 'active',
    ]);
    $site->server->update(['host_key' => 'ssh-ed25519 AAAA host']);
    GitHubConnection::factory()->for($user)->create();

    $this->actingAs($user)
        ->put(route('sites.repository.update', $site), [
            'repository' => 'acme/app',
            'branch' => 'main',
        ])
        ->assertRedirect(route('sites.show', [
            'site' => $site,
            'tab' => 'repository',
        ]));

    $site->refresh();

    expect($site->repository_url)->toBe('acme/app')
        ->and($site->repository_branch)->toBe('main')
        ->and($site->repository_id)->toBe(42)
        ->and($site->deploy_key_id)->toBe(99);
});

test('replacing a repository deletes the previous deploy key using repository_url', function () {
    Http::preventStrayRequests();
    Http::fake([
        'https://api.github.com/repos/acme/app' => Http::response([
            'id' => 42,
            'full_name' => 'acme/app',
            'private' => false,
            'default_branch' => 'main',
        ]),
        'https://api.github.com/repos/acme/app/branches*' => Http::response([
            ['name' => 'main'],
        ]),
        'https://api.github.com/repos/acme/app/keys' => Http::response([
            'id' => 100,
            'title' => 'mini-forge:api.example.test',
            'key' => 'ssh-ed25519 AAAA',
            'fingerprint' => 'SHA256:abc',
        ], 201),
        'https://api.github.com/repos/acme/old/keys/7' => Http::response(status: 204),
    ]);

    $this->mock(SSHService::class, function ($mock) {
        $mock->shouldReceive('run')
            ->once()
            ->andReturn(new SshResult(0, "DEPLOY_KEY_INSTALLED\n"));
    });

    $user = User::factory()->create();
    $site = Site::factory()->forOwner($user)->create([
        'domain' => 'api.example.test',
        'status' => 'active',
        'repository_url' => 'acme/old',
        'repository_id' => 7,
        'repository_branch' => 'main',
        'deploy_key_id' => 7,
    ]);
    $site->server->update(['host_key' => 'ssh-ed25519 AAAA host']);
    GitHubConnection::factory()->for($user)->create();

    $this->actingAs($user)
        ->put(route('sites.repository.update', $site), [
            'repository' => 'acme/app',
            'branch' => 'main',
        ])
        ->assertRedirect(route('sites.show', [
            'site' => $site,
            'tab' => 'repository',
        ]));

    Http::assertSent(fn ($request): bool => $request->method() === 'DELETE'
        && $request->url() === 'https://api.github.com/repos/acme/old/keys/7');

    $site->refresh();

    expect($site->repository_url)->toBe('acme/app')
        ->and($site->repository_branch)->toBe('main')
        ->and($site->deploy_key_id)->toBe(100);
});
