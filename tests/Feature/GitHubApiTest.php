<?php

use App\Models\GitHubConnection;
use App\Support\GitHubApi;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

test('lists repositories through the github http macro', function () {
    Http::preventStrayRequests();
    Http::fake([
        'https://api.github.com/user/repos*' => Http::response([
            [
                'id' => 42,
                'full_name' => 'acme/app',
                'private' => true,
                'default_branch' => 'main',
            ],
        ]),
    ]);

    $repositories = (new GitHubApi(GitHubConnection::factory()->make([
        'token' => 'gho_test',
    ])))->repositories();

    expect($repositories)->toBe([
        [
            'id' => 42,
            'full_name' => 'acme/app',
            'private' => true,
            'default_branch' => 'main',
        ],
    ]);

    Http::assertSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://api.github.com/user/repos')
        && $request->hasHeader('Authorization', 'Bearer gho_test')
        && $request->hasHeader('X-GitHub-Api-Version', '2022-11-28')
        && $request->hasHeader('Accept', 'application/json'));
});

test('fetches a repository through the github http macro', function () {
    Http::preventStrayRequests();
    Http::fake([
        'https://api.github.com/repos/acme/app' => Http::response([
            'id' => 42,
            'full_name' => 'acme/app',
            'private' => false,
            'default_branch' => 'develop',
        ]),
    ]);

    $repository = (new GitHubApi(GitHubConnection::factory()->make([
        'token' => 'gho_test',
    ])))->repository('acme', 'app');

    expect($repository['full_name'])->toBe('acme/app')
        ->and($repository['default_branch'])->toBe('develop');
});

test('throws a validation exception when the repository cannot be found', function () {
    Http::preventStrayRequests();
    Http::fake([
        'https://api.github.com/repos/acme/missing' => Http::response(['message' => 'Not Found'], 404),
    ]);

    expect(fn () => (new GitHubApi(GitHubConnection::factory()->make([
        'token' => 'gho_test',
    ])))->repository('acme', 'missing'))
        ->toThrow(ValidationException::class);
});
