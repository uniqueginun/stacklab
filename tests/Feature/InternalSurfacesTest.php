<?php

use App\Models\Server;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('authenticated users can view internal placeholder surfaces', function () {
    $user = User::factory()->create();
    $server = Server::factory()->for($user)->create();

    $this->actingAs($user);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Dashboard'));

    $this->get(route('servers.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('servers/Index'));

    $this->get(route('servers.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('servers/Create'));

    $this->get(route('servers.show', $server))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('servers/Show'));

    $this->get(route('sites.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('sites/Index'));

    $this->get(route('sites.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('sites/Create'));

    $this->get(route('sites.show'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('sites/Show'));
});
