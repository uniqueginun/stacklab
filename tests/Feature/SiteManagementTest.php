<?php

use App\Enums\ConnectionStatus;
use App\Jobs\DeploySite;
use App\Models\Server;
use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

test('authenticated users can view their sites index', function () {
    $user = User::factory()->create();
    $server = Server::factory()->for($user)->create([
        'connection_status' => ConnectionStatus::CONNECTED,
    ]);

    $site = Site::query()->create([
        'uuid' => (string) Str::uuid(),
        'user_id' => $user->id,
        'server_id' => $server->id,
        'domain' => 'api.example.test',
        'type' => 'laravel',
        'php_version' => '8.3',
        'web_directory' => 'public',
        'root_path' => '/home/forge/api.example.test',
        'status' => 'pending',
    ]);

    Site::query()->create([
        'uuid' => (string) Str::uuid(),
        'user_id' => User::factory()->create()->id,
        'server_id' => Server::factory()->create()->id,
        'domain' => 'other.example.test',
        'type' => 'php',
        'php_version' => '8.4',
        'web_directory' => '.',
        'root_path' => '/home/forge/other.example.test',
        'status' => 'pending',
    ]);

    $this->actingAs($user)
        ->get(route('sites.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('sites/Index')
            ->has('sites', 1)
            ->where('sites.0.uuid', $site->uuid)
            ->where('sites.0.domain', 'api.example.test'));
});

test('create site page lists connected servers', function () {
    $user = User::factory()->create();
    $connected = Server::factory()->for($user)->create([
        'name' => 'ready-01',
        'connection_status' => ConnectionStatus::CONNECTED,
    ]);
    Server::factory()->for($user)->create([
        'name' => 'pending-01',
        'connection_status' => ConnectionStatus::UNVERIFIED,
    ]);

    $this->actingAs($user)
        ->get(route('sites.create', ['server' => $connected->uuid]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('sites/Create')
            ->has('servers', 1)
            ->where('servers.0.uuid', $connected->uuid)
            ->where('selectedServerId', $connected->uuid));
});

test('authenticated users can create a site on a connected server', function () {
    Queue::fake();

    $user = User::factory()->create();
    $server = Server::factory()->for($user)->create([
        'connection_status' => ConnectionStatus::CONNECTED,
        'ssh_user' => 'forge',
    ]);

    $response = $this->actingAs($user)->post(route('sites.store'), [
        'server_id' => $server->uuid,
        'domain' => 'App.Example.Test',
        'type' => 'laravel',
        'php_version' => '8.3',
        'web_directory' => 'public',
    ]);

    $site = Site::query()->where('domain', 'app.example.test')->first();

    expect($site)->not->toBeNull()
        ->and($site->user_id)->toBe($user->id)
        ->and($site->server_id)->toBe($server->id)
        ->and($site->type)->toBe('laravel')
        ->and($site->root_path)->toBe('/home/forge/app.example.test');

    $response->assertRedirect(route('sites.show', $site));

    Queue::assertPushed(DeploySite::class, fn (DeploySite $job) => $job->siteId === $site->id);
});

test('users can view their own site show page', function () {
    $user = User::factory()->create();
    $server = Server::factory()->for($user)->create([
        'connection_status' => ConnectionStatus::CONNECTED,
        'name' => 'production-01',
    ]);

    $site = Site::query()->create([
        'uuid' => (string) Str::uuid(),
        'user_id' => $user->id,
        'server_id' => $server->id,
        'domain' => 'api.example.test',
        'type' => 'laravel',
        'php_version' => '8.3',
        'web_directory' => 'public',
        'root_path' => '/home/forge/api.example.test',
        'status' => 'pending',
    ]);

    $this->actingAs($user)
        ->get(route('sites.show', $site))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('sites/Show')
            ->where('site.uuid', $site->uuid)
            ->where('site.domain', 'api.example.test')
            ->where('site.server.name', 'production-01')
            ->has('releases')
            ->where('activeOperation', null)
            ->where('githubConnected', false)
            ->where('githubUsername', null));
});
