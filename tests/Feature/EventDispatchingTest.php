<?php

use Illuminate\Support\Facades\Event;
use JordanPartridge\ConduitSpotify\Services\EventDispatcher;

describe('Event Dispatching', function () {
    beforeEach(function () {
        Event::fake();
        $this->eventDispatcher = new EventDispatcher;
    });

    it('dispatches track changed event when track changes', function () {
        // Simulate first track
        $firstPlayback = [
            'item' => [
                'id' => 'track_1',
                'name' => 'First Song',
                'artists' => [['name' => 'First Artist']],
                'album' => ['name' => 'First Album'],
                'duration_ms' => 180000,
            ],
            'is_playing' => true,
            'device' => ['name' => 'Test Device'],
        ];

        // First call - establishes baseline
        $this->eventDispatcher->checkAndDispatchTrackChange($firstPlayback);

        // No events should be dispatched on first call
        Event::assertNotDispatched('spotify.track.changed');

        // Simulate second track
        $secondPlayback = [
            'item' => [
                'id' => 'track_2',
                'name' => 'Second Song',
                'artists' => [['name' => 'Second Artist']],
                'album' => ['name' => 'Second Album'],
                'duration_ms' => 200000,
            ],
            'is_playing' => true,
            'device' => ['name' => 'Test Device'],
        ];

        // Second call - should trigger track change event
        $this->eventDispatcher->checkAndDispatchTrackChange($secondPlayback);

        // Assert track change event was dispatched
        Event::assertDispatched('spotify.track.changed', function ($eventName, $eventData) {
            return $eventData['previous']['id'] === 'track_1' &&
                   $eventData['previous']['name'] === 'First Song' &&
                   $eventData['current']['id'] === 'track_2' &&
                   $eventData['current']['name'] === 'Second Song' &&
                   isset($eventData['timestamp']);
        });
    });

    it('dispatches playback state changed events', function () {
        $track = [
            'id' => 'test_track',
            'name' => 'Test Song',
            'artists' => [['name' => 'Test Artist']],
        ];

        // Test play event
        $this->eventDispatcher->dispatchPlaybackStateChanged(true, $track);

        Event::assertDispatched('spotify.playback.state_changed', function ($eventName, $eventData) {
            return $eventData['is_playing'] === true &&
                   $eventData['state'] === 'playing' &&
                   $eventData['track']['name'] === 'Test Song';
        });

        Event::assertDispatched('spotify.playback.started');

        // Reset for pause test
        Event::fake();

        // Test pause event
        $this->eventDispatcher->dispatchPlaybackStateChanged(false, $track);

        Event::assertDispatched('spotify.playback.state_changed', function ($eventName, $eventData) {
            return $eventData['is_playing'] === false &&
                   $eventData['state'] === 'paused';
        });

        Event::assertDispatched('spotify.playback.paused');
    });

    it('detects skipped tracks correctly', function () {
        // Set up a track that was played briefly (less than 80%)
        $firstPlayback = [
            'item' => [
                'id' => 'track_1',
                'name' => 'Skipped Song',
                'artists' => [['name' => 'Artist']],
                'album' => ['name' => 'Album'],
                'duration_ms' => 180000, // 3 minutes
            ],
            'progress_ms' => 30000, // 30 seconds (16.7% - less than 80%)
            'is_playing' => true,
            'device' => ['name' => 'Test Device'],
        ];

        // Establish first track
        $this->eventDispatcher->checkAndDispatchTrackChange($firstPlayback);

        // Simulate track change (new track)
        $secondPlayback = [
            'item' => [
                'id' => 'track_2',
                'name' => 'Next Song',
                'artists' => [['name' => 'Artist']],
                'album' => ['name' => 'Album'],
                'duration_ms' => 200000,
            ],
            'is_playing' => true,
            'device' => ['name' => 'Test Device'],
        ];

        $this->eventDispatcher->checkAndDispatchTrackChange($secondPlayback);

        // Should dispatch both track changed and track skipped events
        Event::assertDispatched('spotify.track.changed');
        Event::assertDispatched('spotify.track.skipped', function ($eventName, $eventData) {
            return $eventData['playback']['was_skipped'] === true &&
                   $eventData['playback']['played_percentage'] < 80;
        });
    });

    it('dispatches volume change events', function () {
        $this->eventDispatcher->dispatchVolumeChanged(50, 75);

        Event::assertDispatched('spotify.volume.changed', function ($eventName, $eventData) {
            return $eventData['old_volume'] === 50 &&
                   $eventData['new_volume'] === 75 &&
                   $eventData['change'] === 25;
        });
    });

    it('dispatches seek events', function () {
        $track = [
            'id' => 'test_track',
            'name' => 'Test Song',
            'duration_ms' => 180000,
        ];

        $this->eventDispatcher->dispatchSeekPerformed(90000, $track);

        Event::assertDispatched('spotify.playback.seek', function ($eventName, $eventData) {
            return $eventData['position_ms'] === 90000 &&
                   $eventData['track']['name'] === 'Test Song';
        });
    });

    it('handles tracks without required data gracefully', function () {
        // Test with incomplete track data
        $incompletePlayback = [
            'item' => [
                'id' => 'track_1',
                // Missing name, artists, etc.
            ],
            'is_playing' => true,
        ];

        // Should not throw exception
        expect(fn () => $this->eventDispatcher->checkAndDispatchTrackChange($incompletePlayback))
            ->not->toThrow(Exception::class);

        $state = $this->eventDispatcher->getLastTrackState();
        expect($state['name'])->toBe('Unknown');
        expect($state['artist'])->toBe('Unknown Artist');
    });

    it('can reset state for testing', function () {
        // Set some state
        $playback = [
            'item' => [
                'id' => 'track_1',
                'name' => 'Test Song',
                'artists' => [['name' => 'Test Artist']],
                'album' => ['name' => 'Test Album'],
                'duration_ms' => 180000,
            ],
        ];

        $this->eventDispatcher->checkAndDispatchTrackChange($playback);

        expect($this->eventDispatcher->getLastTrackState())->not->toBeEmpty();

        // Reset state
        $this->eventDispatcher->resetState();

        expect($this->eventDispatcher->getLastTrackState())->toBeEmpty();
    });
});
