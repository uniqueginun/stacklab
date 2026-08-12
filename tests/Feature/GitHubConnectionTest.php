<?php

use App\Models\GitHubConnection;
use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

beforeEach(function () {
    config([
        'services.github.client_id' => 'github-client-id',
        'services.github.client_secret' => 'github-client-secret',
        'services.github.redirect' => 'http://localhost/settings/connections/github/callback',
    ]);
});

test('guests cannot start github connection', function () {
    $this->get(route('connections.github.redirect'))
        ->assertRedirect(route('login'));
});

test('authenticated users can create a github connection', function () {
    $user = User::factory()->create();

    expect(Gate::forUser($user)->allows('create', GitHubConnection::class))->toBeTrue();
});

test('users can only delete their own github connection', function () {
    $user = User::factory()->create();
    $connection = GitHubConnection::factory()->for($user)->create();
    $other = User::factory()->create();

    expect(Gate::forUser($user)->allows('delete', $connection))->toBeTrue()
        ->and(Gate::forUser($other)->denies('delete', $connection))->toBeTrue();
});

test('users are redirected to github to connect their account', function () {
    Socialite::fake('github');

    $user = User::factory()->create();
    $returnTo = '/sites/example-uuid?tab=repository';

    $this->actingAs($user)
        ->get(route('connections.github.redirect', ['return' => $returnTo]))
        ->assertRedirect('https://socialite.fake/github/authorize')
        ->assertSessionHas('url.intended', $returnTo);
});

test('github redirect ignores external return urls', function () {
    Socialite::fake('github');

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('connections.github.redirect', ['return' => 'https://evil.test/phish']))
        ->assertRedirect('https://socialite.fake/github/authorize');

    expect(session('url.intended'))->not->toBe('https://evil.test/phish');
});

test('users can connect github and return to the site repository tab', function () {
    Socialite::fake('github', SocialiteUser::fake([
        'id' => 'github-123',
        'nickname' => 'octocat',
        'name' => 'The Octocat',
        'token' => 'gho_test_token',
    ]));

    $user = User::factory()->create();
    $site = Site::factory()->forOwner($user)->create();
    $returnTo = route('sites.show', $site).'?tab=repository';

    $this->actingAs($user)
        ->get(route('connections.github.redirect', [
            'return' => route('sites.show', $site, absolute: false).'?tab=repository',
        ]))
        ->assertRedirect();

    $this->actingAs($user)
        ->get(route('connections.github.callback'))
        ->assertRedirect($returnTo);

    $connection = $user->fresh()->githubConnection;

    expect($connection)->not->toBeNull()
        ->and($connection->github_id)->toBe('github-123')
        ->and($connection->username)->toBe('octocat');
});

test('site show page includes github connection details', function () {
    $user = User::factory()->create();
    $site = Site::factory()->forOwner($user)->create();

    GitHubConnection::factory()->for($user)->create([
        'username' => 'octocat',
    ]);

    $this->actingAs($user)
        ->get(route('sites.show', $site))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('sites/Show')
            ->where('githubConnected', true)
            ->where('githubUsername', 'octocat'));
});
