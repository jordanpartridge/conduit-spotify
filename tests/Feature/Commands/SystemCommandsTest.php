<?php

it('can register login command', function () {
    $this->artisan('spotify:login --help')
        ->assertSuccessful();
});

it('can register setup command', function () {
    $this->artisan('spotify:setup --help')
        ->assertSuccessful();
});

it('can register devices command', function () {
    $this->artisan('spotify:devices --help')
        ->assertSuccessful();
});