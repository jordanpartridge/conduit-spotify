<?php

namespace JordanPartridge\ConduitSpotify\Commands\Playback;

use Illuminate\Console\Command;
use JordanPartridge\ConduitSpotify\Concerns\HandlesSpotifyOutput;
use JordanPartridge\ConduitSpotify\Concerns\ShowsSpotifyStatus;
use JordanPartridge\ConduitSpotify\Contracts\ApiInterface;
use JordanPartridge\ConduitSpotify\Contracts\AuthInterface;
use JordanPartridge\ConduitSpotify\Services\EventDispatcher;

class Pause extends Command
{
    use HandlesSpotifyOutput, ShowsSpotifyStatus;

    protected $signature = 'spotify:pause 
                           {--device= : Device ID to pause}
                           {--format=interactive : Output format (interactive, json)}
                           {--non-interactive : Run without prompts}';

    protected $description = 'Pause Spotify playback';

    public function handle(AuthInterface $auth, ApiInterface $api, EventDispatcher $eventDispatcher): int
    {
        if (! $auth->ensureAuthenticated()) {
            return $this->handleAuthError();
        }

        try {
            $deviceId = $this->option('device');

            $success = $api->pause($deviceId);

            if ($success) {
                // Dispatch playback paused event
                $currentTrack = $api->getCurrentPlayback()['item'] ?? null;
                $eventDispatcher->dispatchPlaybackStateChanged(false, $currentTrack);

                // Handle JSON format output
                if ($this->option('format') === 'json') {
                    $pauseData = [
                        'action' => 'pause',
                        'device_id' => $deviceId,
                        'track' => $currentTrack ? [
                            'id' => $currentTrack['id'] ?? null,
                            'name' => $currentTrack['name'] ?? null,
                            'artist' => isset($currentTrack['artists']) ?
                                collect($currentTrack['artists'])->pluck('name')->join(', ') : null,
                        ] : null,
                    ];

                    return $this->outputJson($pauseData);
                }

                $this->info('⏸️  Playback paused');

                if ($this->isInteractive()) {
                    $this->showSpotifyStatusBar();
                }

                return 0;
            } else {
                $error = ['error' => 'Failed to pause playback'];

                if ($this->option('format') === 'json') {
                    return $this->outputJson($error, 1);
                }

                $this->error('❌ Failed to pause playback');

                return 1;
            }

        } catch (\Exception $e) {
            return $this->handleApiError($e, 'pause');
        }
    }
}
