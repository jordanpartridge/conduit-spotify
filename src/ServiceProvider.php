<?php

namespace JordanPartridge\ConduitSpotify;

use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use JordanPartridge\ConduitSpotify\Commands\Analytics\Analytics;
use JordanPartridge\ConduitSpotify\Commands\Library\Focus;
use JordanPartridge\ConduitSpotify\Commands\Library\Playlists;
use JordanPartridge\ConduitSpotify\Commands\Library\Queue;
use JordanPartridge\ConduitSpotify\Commands\Library\Search;
use JordanPartridge\ConduitSpotify\Commands\Playback\Current;
use JordanPartridge\ConduitSpotify\Commands\Playback\Next;
use JordanPartridge\ConduitSpotify\Commands\Playback\Pause;
use JordanPartridge\ConduitSpotify\Commands\Playback\Play;
use JordanPartridge\ConduitSpotify\Commands\Playback\Skip;
use JordanPartridge\ConduitSpotify\Commands\Playback\Volume;
use JordanPartridge\ConduitSpotify\Commands\System\Configure;
use JordanPartridge\ConduitSpotify\Commands\System\Devices;
use JordanPartridge\ConduitSpotify\Commands\System\Login;
use JordanPartridge\ConduitSpotify\Commands\System\Logout;
use JordanPartridge\ConduitSpotify\Commands\System\Setup;
use JordanPartridge\ConduitSpotify\Contracts\ApiInterface;
use JordanPartridge\ConduitSpotify\Contracts\AuthInterface;
use JordanPartridge\ConduitSpotify\Services\Api;
use JordanPartridge\ConduitSpotify\Services\Auth;
use JordanPartridge\ConduitSpotify\Services\DeviceManager;
use JordanPartridge\ConduitSpotify\Services\EventDispatcher;

class ServiceProvider extends BaseServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Merge config
        $this->mergeConfigFrom(__DIR__.'/../config/spotify.php', 'spotify');

        // Register services
        $this->app->singleton(AuthInterface::class, Auth::class);
        $this->app->singleton(ApiInterface::class, Api::class);
        $this->app->singleton(DeviceManager::class);
        $this->app->singleton(EventDispatcher::class);

        // Register commands
        $this->commands([
            Setup::class,
            Configure::class, // Backwards compatibility alias
            Login::class,
            Logout::class,
            Queue::class,
            Search::class,
            Play::class,
            Pause::class,
            Skip::class,
            Next::class, // Alias for Skip (next track only)
            Current::class,
            Volume::class,
            Playlists::class,
            Focus::class,
            Analytics::class,
            Devices::class,
        ]);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Publish config if needed
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/spotify.php' => config_path('spotify.php'),
            ], 'spotify-config');
        }
    }
}
