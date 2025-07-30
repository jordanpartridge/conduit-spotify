<?php

namespace JordanPartridge\ConduitSpotify\Commands\Playback;

use Illuminate\Console\Command;
use JordanPartridge\ConduitSpotify\Concerns\ShowsSpotifyStatus;
use JordanPartridge\ConduitSpotify\Contracts\ApiInterface;
use JordanPartridge\ConduitSpotify\Contracts\AuthInterface;
use JordanPartridge\ConduitSpotify\Services\EventDispatcher;

class Pause extends Command
{
    use ShowsSpotifyStatus;

    protected $signature = 'spotify:pause {--device= : Device ID to pause}';

    protected $description = 'Pause Spotify playback';

    public function handle(AuthInterface $auth, ApiInterface $api, EventDispatcher $eventDispatcher): int
    {
        if (! $auth->ensureAuthenticated()) {
            $this->error('❌ Not authenticated with Spotify');
            $this->info('💡 Run: php conduit spotify:login');

            return 1;
        }

        try {
            $deviceId = $this->option('device');

            $success = $api->pause($deviceId);

            if ($success) {
                $this->info('⏸️  Playback paused');
                
                // Dispatch playback paused event
                $currentTrack = $api->getCurrentPlayback()['item'] ?? null;
                $eventDispatcher->dispatchPlaybackStateChanged(false, $currentTrack);
                
                $this->showSpotifyStatusBar();

                return 0;
            } else {
                $this->error('❌ Failed to pause playback');

                return 1;
            }

        } catch (\Exception $e) {
            $this->error("❌ Error: {$e->getMessage()}");

            return 1;
        }
    }
}
