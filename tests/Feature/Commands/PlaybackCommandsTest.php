<?php

use JordanPartridge\ConduitSpotify\Commands\Playback\Play;
use JordanPartridge\ConduitSpotify\Commands\Playback\Pause;
use JordanPartridge\ConduitSpotify\Commands\Playback\Current;

it('can register play command', function () {
    $this->artisan('spotify:play --help')
        ->assertSuccessful();
});

it('can register pause command', function () {
    $this->artisan('spotify:pause --help')
        ->assertSuccessful();
});

it('can register current command', function () {
    $this->artisan('spotify:current --help')
        ->assertSuccessful();
});