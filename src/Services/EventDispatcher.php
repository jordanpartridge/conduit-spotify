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
    private array $lastTrackState = [];

    /**
     * Check for track changes and dispatch events if detected
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
            'name' => $currentTrack['name'] ?? 'Unknown',
            'artist' => $this->getArtistNames($currentTrack),
            'album' => $currentTrack['album']['name'] ?? 'Unknown Album',
            'duration_ms' => $currentTrack['duration_ms'] ?? 0,
            'timestamp' => now()->toISOString(),
        ];
    }

    /**
     * Dispatch track changed event
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
                'name' => $currentTrack['name'] ?? 'Unknown',
                'artist' => $this->getArtistNames($currentTrack),
                'album' => $currentTrack['album']['name'] ?? 'Unknown Album',
                'duration_ms' => $currentTrack['duration_ms'] ?? 0,
            ],
            'playback' => [
                'played_duration_ms' => $progressMs,
                'played_percentage' => round($playedPercentage, 2),
                'was_skipped' => $playedPercentage < 80, // Consider <80% as skipped
                'device' => $playbackState['device']['name'] ?? 'Unknown Device',
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
     */
    public function dispatchPlaybackStateChanged(bool $isPlaying, array $currentTrack = null): void
    {
        $eventData = [
            'is_playing' => $isPlaying,
            'state' => $isPlaying ? 'playing' : 'paused',
            'track' => $currentTrack ? [
                'id' => $currentTrack['id'] ?? null,
                'name' => $currentTrack['name'] ?? 'Unknown',
                'artist' => $this->getArtistNames($currentTrack),
            ] : null,
            'timestamp' => now()->toISOString(),
        ];

        Event::dispatch('spotify.playback.state_changed', $eventData);
        Event::dispatch($isPlaying ? 'spotify.playback.started' : 'spotify.playback.paused', $eventData);
    }

    /**
     * Dispatch volume change event
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
     */
    public function dispatchSeekPerformed(int $positionMs, array $currentTrack = null): void
    {
        $eventData = [
            'position_ms' => $positionMs,
            'track' => $currentTrack ? [
                'id' => $currentTrack['id'] ?? null,
                'name' => $currentTrack['name'] ?? 'Unknown',
                'duration_ms' => $currentTrack['duration_ms'] ?? 0,
            ] : null,
            'timestamp' => now()->toISOString(),
        ];

        Event::dispatch('spotify.playback.seek', $eventData);
    }

    /**
     * Extract artist names from track data
     */
    private function getArtistNames(array $track): string
    {
        if (!isset($track['artists']) || !is_array($track['artists'])) {
            return 'Unknown Artist';
        }

        return collect($track['artists'])
            ->pluck('name')
            ->filter()
            ->join(', ') ?: 'Unknown Artist';
    }

    /**
     * Get the last known track state for external use
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