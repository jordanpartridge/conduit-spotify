<?php

namespace JordanPartridge\ConduitSpotify\Tests;

use JordanPartridge\ConduitSpotify\ServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            ServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        config()->set('spotify.client_id', 'test_client_id');
        config()->set('spotify.client_secret', 'test_client_secret');
    }
}
