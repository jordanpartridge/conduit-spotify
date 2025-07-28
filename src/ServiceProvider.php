<?php

declare(strict_types=1);

namespace JordanPartridge\ConduitSpotify;

use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use JordanPartridge\ConduitSpotify\Commands\SpotifyinitCommand;

class ServiceProvider extends BaseServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                SpotifyinitCommand::class
            ]);
        }
    }
}