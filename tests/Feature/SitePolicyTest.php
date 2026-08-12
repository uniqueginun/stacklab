<?php

use App\Models\Server;
use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

test('authenticated users can view any sites listing', function () {
    $user = User::factory()->create();

    expect(Gate::forUser($user)->allows('viewAny', Site::class))->toBeTrue();
});

test('authenticated users can create sites', function () {
    $user = User::factory()->create();

    expect(Gate::forUser($user)->allows('create', Site::class))->toBeTrue();
});

test('users can manage their own sites', function () {
    $user = User::factory()->create();
    $site = new Site(['user_id' => $user->id]);
    $site->setRelation('user', $user);

    expect(Gate::forUser($user)->allows('view', $site))->toBeTrue()
        ->and(Gate::forUser($user)->allows('update', $site))->toBeTrue()
        ->and(Gate::forUser($user)->allows('delete', $site))->toBeTrue();
});

test('users cannot manage another users sites', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $site = new Site(['user_id' => $owner->id]);
    $site->setRelation('user', $owner);

    expect(Gate::forUser($other)->denies('view', $site))->toBeTrue()
        ->and(Gate::forUser($other)->denies('update', $site))->toBeTrue()
        ->and(Gate::forUser($other)->denies('delete', $site))->toBeTrue();
});

test('site show returns forbidden for another users site', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $server = Server::factory()->for($owner)->create();

    $site = Site::query()->create([
        'uuid' => (string) Str::uuid(),
        'user_id' => $owner->id,
        'server_id' => $server->id,
        'domain' => 'example.test',
        'type' => 'laravel',
        'php_version' => '8.3',
        'web_directory' => 'public',
        'root_path' => '/home/forge/example.test',
        'status' => 'pending',
    ]);

    $this->actingAs($other)
        ->get(route('sites.show', $site))
        ->assertForbidden();
});
