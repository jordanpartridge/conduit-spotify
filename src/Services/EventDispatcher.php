<?php

namespace JordanPartridge\ConduitSpotify\Services;

use Illuminate\Support\Facades\Event;

/**
 * Spotify Event Dispatcher Service
 * 
 * Dispatches events for track changes, playback state changes, and other
 * Spotify interactions to enable component interoperability and analytics.
 */
class EventDispatcher
{
    /**
     * Skip threshold percentage - tracks played less than this are considered skipped
     */
    private const SKIP_THRESHOLD_PERCENTAGE = 80;

    /**
     * Default values for unknown track data
     */
    private const DEFAULT_TRACK_NAME = 'Unknown';
    private const DEFAULT_ARTIST_NAME = 'Unknown Artist';
    private const DEFAULT_ALBUM_NAME = 'Unknown Album';
    private const DEFAULT_DEVICE_NAME = 'Unknown Device';

    /**
     * Last known track state for change detection
     */
    private array $lastTrackState = [];

    /**
     * Check for track changes and dispatch events if detected
     *
     * @param array $currentPlayback Current playback data from Spotify API
     */
    public function checkAndDispatchTrackChange(array $currentPlayback): void
    {
        if (!isset($currentPlayback['item'])) {
            return;
        }

        $currentTrack = $currentPlayback['item'];
        $trackId = $currentTrack['id'] ?? null;
        
        if (!$trackId) {
            return;
        }

        $lastTrackId = $this->lastTrackState['id'] ?? null;
        
        // Track changed
        if ($lastTrackId && $lastTrackId !== $trackId) {
            $this->dispatchTrackChanged(
                $this->lastTrackState,
                $currentTrack,
                $currentPlayback
            );
        }

        // Update stored state
        $this->lastTrackState = [
            'id' => $trackId,
            'name' => $currentTrack['name'] ?? self::DEFAULT_TRACK_NAME,
            'artist' => $this->getArtistNames($currentTrack),
            'album' => $currentTrack['album']['name'] ?? self::DEFAULT_ALBUM_NAME,
            'duration_ms' => $currentTrack['duration_ms'] ?? 0,
            'timestamp' => now()->toISOString(),
        ];
    }

    /**
     * Dispatch track changed event
     *
     * @param array $previousTrack Previous track data
     * @param array $currentTrack Current track data from Spotify API
     * @param array $playbackState Current playback state data
     */
    private function dispatchTrackChanged(array $previousTrack, array $currentTrack, array $playbackState): void
    {
        $progressMs = $this->lastTrackState['progress_ms'] ?? 0;
        $durationMs = $previousTrack['duration_ms'] ?? 1;
        $playedPercentage = $durationMs > 0 ? ($progressMs / $durationMs) * 100 : 0;

        $eventData = [
            'previous' => [
                'id' => $previousTrack['id'],
                'name' => $previousTrack['name'],
                'artist' => $previousTrack['artist'],
                'album' => $previousTrack['album'],
                'duration_ms' => $previousTrack['duration_ms'],
            ],
            'current' => [
                'id' => $currentTrack['id'] ?? null,
                'name' => $currentTrack['name'] ?? self::DEFAULT_TRACK_NAME,
                'artist' => $this->getArtistNames($currentTrack),
                'album' => $currentTrack['album']['name'] ?? self::DEFAULT_ALBUM_NAME,
                'duration_ms' => $currentTrack['duration_ms'] ?? 0,
            ],
            'playback' => [
                'played_duration_ms' => $progressMs,
                'played_percentage' => round($playedPercentage, 2),
                'was_skipped' => $playedPercentage < self::SKIP_THRESHOLD_PERCENTAGE,
                'device' => $playbackState['device']['name'] ?? self::DEFAULT_DEVICE_NAME,
                'is_playing' => $playbackState['is_playing'] ?? false,
            ],
            'timestamp' => now()->toISOString(),
        ];

        // Dispatch the main track change event
        Event::dispatch('spotify.track.changed', $eventData);

        // Dispatch additional specific events
        if ($eventData['playback']['was_skipped']) {
            Event::dispatch('spotify.track.skipped', $eventData);
        }
    }

    /**
     * Dispatch playback state change event (play/pause)
     *
     * @param bool $isPlaying Whether playback is currently active
     * @param array|null $currentTrack Current track data from Spotify API
     */
    public function dispatchPlaybackStateChanged(bool $isPlaying, ?array $currentTrack = null): void
    {
        $eventData = [
            'is_playing' => $isPlaying,
            'state' => $isPlaying ? 'playing' : 'paused',
            'track' => $currentTrack ? [
                'id' => $currentTrack['id'] ?? null,
                'name' => $currentTrack['name'] ?? self::DEFAULT_TRACK_NAME,
                'artist' => $this->getArtistNames($currentTrack),
            ] : null,
            'timestamp' => now()->toISOString(),
        ];

        Event::dispatch('spotify.playback.state_changed', $eventData);
        Event::dispatch($isPlaying ? 'spotify.playback.started' : 'spotify.playback.paused', $eventData);
    }

    /**
     * Dispatch volume change event
     *
     * @param int $oldVolume Previous volume level (0-100)
     * @param int $newVolume New volume level (0-100)
     */
    public function dispatchVolumeChanged(int $oldVolume, int $newVolume): void
    {
        $eventData = [
            'old_volume' => $oldVolume,
            'new_volume' => $newVolume,
            'change' => $newVolume - $oldVolume,
            'timestamp' => now()->toISOString(),
        ];

        Event::dispatch('spotify.volume.changed', $eventData);
    }

    /**
     * Dispatch seek event (when user jumps to different position)
     *
     * @param int $positionMs New position in milliseconds
     * @param array|null $currentTrack Current track data from Spotify API
     */
    public function dispatchSeekPerformed(int $positionMs, ?array $currentTrack = null): void
    {
        $eventData = [
            'position_ms' => $positionMs,
            'track' => $currentTrack ? [
                'id' => $currentTrack['id'] ?? null,
                'name' => $currentTrack['name'] ?? self::DEFAULT_TRACK_NAME,
                'duration_ms' => $currentTrack['duration_ms'] ?? 0,
            ] : null,
            'timestamp' => now()->toISOString(),
        ];

        Event::dispatch('spotify.playback.seek', $eventData);
    }

    /**
     * Extract artist names from track data
     *
     * @param array $track Track data from Spotify API
     * @return string Comma-separated artist names
     */
    private function getArtistNames(array $track): string
    {
        if (!isset($track['artists']) || !is_array($track['artists'])) {
            return self::DEFAULT_ARTIST_NAME;
        }

        return collect($track['artists'])
            ->pluck('name')
            ->filter()
            ->join(', ') ?: self::DEFAULT_ARTIST_NAME;
    }

    /**
     * Get the last known track state for external use
     *
     * @return array Last known track state data
     */
    public function getLastTrackState(): array
    {
        return $this->lastTrackState;
    }

    /**
     * Reset the track state (useful for testing)
     */
    public function resetState(): void
    {
        $this->lastTrackState = [];
    }
}