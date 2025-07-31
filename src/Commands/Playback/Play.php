<?php

namespace JordanPartridge\ConduitSpotify\Commands\Playback;

use Illuminate\Console\Command;
use JordanPartridge\ConduitSpotify\Concerns\HandlesSpotifyOutput;
use JordanPartridge\ConduitSpotify\Concerns\SendsNotifications;
use JordanPartridge\ConduitSpotify\Concerns\ShowsSpotifyStatus;
use JordanPartridge\ConduitSpotify\Contracts\ApiInterface;
use JordanPartridge\ConduitSpotify\Contracts\AuthInterface;
use JordanPartridge\ConduitSpotify\Services\EventDispatcher;

class Play extends Command
{
    use HandlesSpotifyOutput, SendsNotifications, ShowsSpotifyStatus;

    protected $signature = 'spotify:play 
                           {uri? : Spotify URI, preset name, or search query}
                           {--device= : Device ID to play on}
                           {--shuffle : Enable shuffle mode}
                           {--volume= : Set volume (0-100)}
                           {--format=interactive : Output format (interactive, json)}
                           {--non-interactive : Run without prompts}';

    protected $description = 'Start playing music on Spotify';

    public function handle(AuthInterface $auth, ApiInterface $api, EventDispatcher $eventDispatcher): int
    {
        if (! $auth->ensureAuthenticated()) {
            // Handle interactive login
            if ($this->isInteractive() && $this->confirm('Would you like to login now?', true)) {
                $this->info('🔐 Starting Spotify login...');

                // Run the login command
                $loginResult = $this->call('spotify:login');

                if ($loginResult === 0) {
                    $this->newLine();
                    $this->info('✅ Login successful! Continuing...');
                    $this->newLine();

                    // Retry auth check
                    if (! $auth->ensureAuthenticated()) {
                        $this->error('❌ Authentication failed. Please try again.');

                        return 1;
                    }
                } else {
                    return $this->handleApiError(new \Exception('Login failed'), 'authentication');
                }
            } else {
                return $this->handleAuthError();
            }
        }

        try {
            $uri = $this->argument('uri');
            $deviceId = $this->option('device');
            $shuffle = $this->option('shuffle');
            $volume = $this->option('volume');

            // Check for available devices if no specific device provided
            if (! $deviceId) {
                // First, check current playback for last active device
                $currentPlayback = $api->getCurrentPlayback();
                if ($currentPlayback && isset($currentPlayback['device'])) {
                    $lastDevice = $currentPlayback['device'];
                    $this->info("🎵 Using last active device: {$lastDevice['name']}");
                    $deviceId = $lastDevice['id'];
                } else {
                    // Fallback to available devices
                    $devices = $api->getAvailableDevices();
                    $activeDevice = collect($devices)->firstWhere('is_active', true);

                    if ($activeDevice) {
                        $deviceId = $activeDevice['id'];
                        $this->info("🎵 Using active device: {$activeDevice['name']}");
                    } elseif (! empty($devices)) {
                        // Try to activate the first available device
                        $firstDevice = $devices[0];
                        $this->info("🔄 Activating device: {$firstDevice['name']}");
                        if ($api->transferPlayback($firstDevice['id'], false)) {
                            $deviceId = $firstDevice['id'];
                            sleep(1); // Give device time to activate
                        }
                    }
                }
            }

            // Handle preset shortcuts and search queries
            if ($uri && ! str_starts_with($uri, 'spotify:')) {
                $presets = config('spotify.presets', []);
                if (isset($presets[$uri])) {
                    $uri = $presets[$uri];
                    $this->info("🎵 Playing preset: {$uri}");
                } else {
                    // Treat as search query
                    $this->info("🔍 Searching for: \"{$uri}\"");
                    $searchResults = $api->search($uri, ['track', 'artist'], 5);

                    // Check if we have a popular track match first
                    if (! empty($searchResults['tracks']['items'])) {
                        $tracks = $searchResults['tracks']['items'];

                        // Look for exact track name match
                        $exactTrackMatch = collect($tracks)->first(function ($track) use ($uri) {
                            return strtolower($track['name']) === strtolower($uri);
                        });

                        if ($exactTrackMatch) {
                            $uri = $exactTrackMatch['uri'];
                            $artist = collect($exactTrackMatch['artists'])->pluck('name')->join(', ');
                            $this->info("🎵 Found track: {$exactTrackMatch['name']} by {$artist}");
                        } else {
                            // No exact track match, try artist
                            if (! empty($searchResults['artists']['items'])) {
                                $artists = $searchResults['artists']['items'];

                                // Look for exact artist match
                                $exactArtistMatch = collect($artists)->first(function ($artist) use ($uri) {
                                    return strtolower($artist['name']) === strtolower($uri);
                                });

                                if ($exactArtistMatch) {
                                    $uri = $exactArtistMatch['uri'];
                                    $this->info("🎵 Found artist: {$exactArtistMatch['name']}");
                                } else {
                                    // Fall back to first track
                                    $track = $tracks[0];
                                    $uri = $track['uri'];
                                    $artist = collect($track['artists'])->pluck('name')->join(', ');
                                    $this->info("🎵 Found track: {$track['name']} by {$artist}");
                                }
                            } else {
                                // Fall back to first track
                                $track = $tracks[0];
                                $uri = $track['uri'];
                                $artist = collect($track['artists'])->pluck('name')->join(', ');
                                $this->info("🎵 Found track: {$track['name']} by {$artist}");
                            }
                        }
                    } elseif (! empty($searchResults['artists']['items'])) {
                        $artist = $searchResults['artists']['items'][0];
                        $uri = $artist['uri'];
                        $this->info("🎵 Found artist: {$artist['name']}");
                    } else {
                        $this->error("❌ No results found for: \"{$this->argument('uri')}\"");

                        return 1;
                    }
                }
            }

            // Set volume if specified
            if ($volume !== null) {
                $volume = max(0, min(100, (int) $volume));
                $api->setVolume($volume, $deviceId);
                $this->info("🔊 Volume set to {$volume}%");
            }

            // Enable shuffle if requested
            if ($shuffle) {
                $api->setShuffle(true, $deviceId);
                $this->info('🔀 Shuffle enabled');
            }

            // Start playback
            $success = $api->play($uri, $deviceId);

            if ($success) {
                // Dispatch playback started event
                $currentTrack = $api->getCurrentPlayback()['item'] ?? null;
                $eventDispatcher->dispatchPlaybackStateChanged(true, $currentTrack);

                // Handle JSON format output
                if ($this->option('format') === 'json') {
                    $playbackData = [
                        'action' => 'play',
                        'uri' => $uri,
                        'device_id' => $deviceId,
                        'shuffle' => $shuffle,
                        'volume' => $volume,
                        'track' => $currentTrack ? [
                            'id' => $currentTrack['id'] ?? null,
                            'name' => $currentTrack['name'] ?? null,
                            'artist' => isset($currentTrack['artists']) ?
                                collect($currentTrack['artists'])->pluck('name')->join(', ') : null,
                        ] : null,
                    ];

                    return $this->outputJson($playbackData);
                }

                if ($uri) {
                    $this->info("▶️  Playing: {$uri}");
                } else {
                    $this->info('▶️  Resuming playback');
                    $this->notifyPlaybackResumed();
                }

                // Show status bar after playback starts (only in interactive mode)
                if ($this->isInteractive()) {
                    sleep(1);
                    $this->showSpotifyStatusBar();

                    // Send notification with current track info
                    $this->sendNowPlayingNotification($api);
                }

                return 0;
            } else {
                $error = ['error' => 'Failed to start playback'];

                if ($this->option('format') === 'json') {
                    return $this->outputJson($error, 1);
                }

                $this->error('❌ Failed to start playback');

                return 1;
            }

        } catch (\Exception $e) {
            return $this->handleApiError($e, 'play');
        }
    }

    /**
     * Send notification with current playing track info
     */
    private function sendNowPlayingNotification(ApiInterface $api): void
    {
        try {
            $currentTrack = $api->getCurrentTrack();

            if ($currentTrack && isset($currentTrack['item'])) {
                $this->notifyNowPlaying($currentTrack['item']);
            }
        } catch (\Exception $e) {
            // Silently fail - notifications are not critical
        }
    }
}
