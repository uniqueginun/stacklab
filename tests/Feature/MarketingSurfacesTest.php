<?php

use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Features;

test('welcome page renders the marketing surface', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Welcome'));
});

test('login page renders the auth surface', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('auth/Login'));
});

test('registration page renders the auth surface', function () {
    $this->skipUnlessFortifyHas(Features::registration());

    $this->get(route('register'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('auth/Register'));
});
