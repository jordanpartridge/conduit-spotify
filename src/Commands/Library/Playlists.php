<?php

namespace JordanPartridge\ConduitSpotify\Commands\Library;

use Illuminate\Console\Command;
use JordanPartridge\ConduitSpotify\Concerns\ManagesSpotifyDevices;
use JordanPartridge\ConduitSpotify\Contracts\ApiInterface;
use JordanPartridge\ConduitSpotify\Contracts\AuthInterface;

class Playlists extends Command
{
    use ManagesSpotifyDevices;

    protected $signature = 'spotify:playlists 
                           {action? : Action (list, play, search)}
                           {query? : Playlist name or search query}
                           {--limit=20 : Number of playlists to show}
                           {--json : Output as JSON}';

    protected $description = 'Manage and play Spotify playlists';

    public function handle(AuthInterface $auth, ApiInterface $api): int
    {
        if (! $auth->ensureAuthenticated()) {
            $this->error('❌ Not authenticated with Spotify');
            $this->info('💡 Run: php conduit spotify:login');

            return 1;
        }

        $action = $this->argument('action') ?? 'list';

        return match ($action) {
            'list' => $this->listPlaylists($api),
            'play' => $this->playPlaylist($api),
            'search' => $this->searchPlaylists($api),
            default => $this->handleInvalidAction($action)
        };
    }

    private function listPlaylists(ApiInterface $api): int
    {
        try {
            $limit = (int) $this->option('limit');
            $playlists = $api->getUserPlaylists($limit);

            if (empty($playlists)) {
                $this->info('📭 No playlists found');

                return 0;
            }

            if ($this->option('json')) {
                $this->line(json_encode($playlists, JSON_PRETTY_PRINT));

                return 0;
            }

            $this->info("🎵 Your Spotify Playlists ({$limit} shown):");
            $this->newLine();

            foreach ($playlists as $index => $playlist) {
                $number = $index + 1;
                $name = $playlist['name'];
                $trackCount = $playlist['tracks']['total'] ?? 0;
                $owner = $playlist['owner']['display_name'] ?? 'Unknown';

                $this->line("  <info>{$number}.</info> <comment>{$name}</comment>");
                $this->line("      {$trackCount} tracks • by {$owner}");

                if ($index < count($playlists) - 1) {
                    $this->newLine();
                }
            }

            $this->newLine();
            $this->line('💡 To play a playlist: php conduit spotify:playlists play "playlist name"');
            $this->line('   Or use: php conduit spotify:play spotify:playlist:PLAYLIST_ID');

            return 0;

        } catch (\Exception $e) {
            $this->error("❌ Error: {$e->getMessage()}");

            return 1;
        }
    }

    private function playPlaylist(ApiInterface $api): int
    {
        $query = $this->argument('query');

        if (! $query) {
            $this->error('❌ Please specify a playlist name');
            $this->line('💡 Usage: php conduit spotify:playlists play "My Playlist"');

            return 1;
        }

        try {
            // Get all playlists for smart search
            $playlists = $api->getUserPlaylists(50);
            $matchedPlaylist = $this->findBestPlaylistMatch($playlists, $query);

            if (! $matchedPlaylist) {
                $this->error("❌ No playlist found matching: {$query}");

                // Show suggestions for close matches
                $suggestions = $this->getSimilarPlaylists($playlists, $query);
                if (! empty($suggestions)) {
                    $this->line('💡 Did you mean:');
                    foreach (array_slice($suggestions, 0, 3) as $suggestion) {
                        $this->line("   • {$suggestion['name']}");
                    }
                }

                return 1;
            }

            $playlistUri = $matchedPlaylist['uri'];

            // Ensure we have an active device before playing
            $this->ensureActiveDevice($api);

            $success = $api->play($playlistUri);

            if ($success) {
                $name = $matchedPlaylist['name'];
                $trackCount = $matchedPlaylist['tracks']['total'] ?? 0;

                $this->info("▶️  Playing playlist: {$name}");
                $this->line("🎵 {$trackCount} tracks");

                // Show current track after a moment
                sleep(1);
                $current = $api->getCurrentTrack();
                if ($current && isset($current['item'])) {
                    $track = $current['item'];
                    $artist = collect($track['artists'])->pluck('name')->join(', ');
                    $this->line("   <info>{$track['name']}</info> by <comment>{$artist}</comment>");
                }

                return 0;
            } else {
                $this->error('❌ Failed to play playlist');

                return 1;
            }

        } catch (\Exception $e) {
            $this->error("❌ Error: {$e->getMessage()}");

            return 1;
        }
    }

    private function searchPlaylists(ApiInterface $api): int
    {
        $query = $this->argument('query');

        if (! $query) {
            $this->error('❌ Please specify a search query');
            $this->line('💡 Usage: php conduit spotify:playlists search "chill"');

            return 1;
        }

        try {
            $results = $api->search($query, ['playlist'], 20);

            if (! isset($results['playlists']['items']) || empty($results['playlists']['items'])) {
                $this->info("🔍 No playlists found for: {$query}");

                return 0;
            }

            $playlists = $results['playlists']['items'];

            if ($this->option('json')) {
                $this->line(json_encode($playlists, JSON_PRETTY_PRINT));

                return 0;
            }

            $this->info("🔍 Search results for: {$query}");
            $this->newLine();

            foreach ($playlists as $index => $playlist) {
                $number = $index + 1;
                $name = $playlist['name'];
                $trackCount = $playlist['tracks']['total'] ?? 0;
                $owner = $playlist['owner']['display_name'] ?? 'Unknown';
                $description = $playlist['description'] ?? '';

                $this->line("  <info>{$number}.</info> <comment>{$name}</comment>");
                $this->line("      {$trackCount} tracks • by {$owner}");

                if ($description) {
                    $shortDesc = strlen($description) > 60 ? substr($description, 0, 60).'...' : $description;
                    $this->line("      {$shortDesc}");
                }

                if ($index < count($playlists) - 1) {
                    $this->newLine();
                }
            }

            $this->newLine();
            $this->line('💡 To play: php conduit spotify:play '.$playlists[0]['uri']);

            return 0;

        } catch (\Exception $e) {
            $this->error("❌ Error: {$e->getMessage()}");

            return 1;
        }
    }

    private function handleInvalidAction(string $action): int
    {
        $this->error("❌ Invalid action: {$action}");
        $this->line('💡 Available actions: list, play, search');
        $this->line('   Examples:');
        $this->line('     php conduit spotify:playlists list');
        $this->line('     php conduit spotify:playlists play "My Coding Playlist"');
        $this->line('     php conduit spotify:playlists search "chill"');

        return 1;
    }

    /**
     * Find the best matching playlist using fuzzy search logic
     */
    private function findBestPlaylistMatch(array $playlists, string $query): ?array
    {
        $query = strtolower(trim($query));
        $bestMatch = null;
        $bestScore = 0;

        foreach ($playlists as $playlist) {
            $playlistName = strtolower($playlist['name']);
            $score = $this->calculateMatchScore($playlistName, $query);

            if ($score > $bestScore && $score > 0) {
                $bestScore = $score;
                $bestMatch = $playlist;
            }
        }

        // Only return matches with decent confidence (score > 30)
        return $bestScore > 30 ? $bestMatch : null;
    }

    /**
     * Calculate fuzzy match score between playlist name and query
     */
    private function calculateMatchScore(string $playlistName, string $query): int
    {
        $score = 0;

        // Exact match gets highest score
        if ($playlistName === $query) {
            return 100;
        }

        // Exact substring match gets high score
        if (str_contains($playlistName, $query)) {
            $score += 80;
        }

        // Check if query starts the playlist name
        if (str_starts_with($playlistName, $query)) {
            $score += 70;
        }

        // Check if query ends the playlist name
        if (str_ends_with($playlistName, $query)) {
            $score += 60;
        }

        // Word-based matching
        $playlistWords = explode(' ', $playlistName);
        $queryWords = explode(' ', $query);

        foreach ($queryWords as $queryWord) {
            foreach ($playlistWords as $playlistWord) {
                // Exact word match
                if ($queryWord === $playlistWord) {
                    $score += 40;

                    continue 2;
                }

                // Word starts with query word
                if (str_starts_with($playlistWord, $queryWord)) {
                    $score += 30;

                    continue 2;
                }

                // Fuzzy character matching (at least 70% similarity)
                $similarity = $this->calculateStringSimilarity($queryWord, $playlistWord);
                if ($similarity >= 0.7) {
                    $score += (int) ($similarity * 25);
                }
            }
        }

        return $score;
    }

    /**
     * Calculate string similarity using Levenshtein distance
     */
    private function calculateStringSimilarity(string $str1, string $str2): float
    {
        $maxLen = max(strlen($str1), strlen($str2));
        if ($maxLen === 0) {
            return 1.0;
        }

        $distance = levenshtein($str1, $str2);

        return 1 - ($distance / $maxLen);
    }

    /**
     * Get playlists with similar names to the query for suggestions
     */
    private function getSimilarPlaylists(array $playlists, string $query): array
    {
        $query = strtolower(trim($query));
        $suggestions = [];

        foreach ($playlists as $playlist) {
            $playlistName = strtolower($playlist['name']);
            $score = $this->calculateMatchScore($playlistName, $query);

            // Include playlists with moderate similarity for suggestions
            if ($score > 15 && $score <= 30) {
                $suggestions[] = [
                    'name' => $playlist['name'],
                    'score' => $score,
                    'uri' => $playlist['uri'],
                ];
            }
        }

        // Sort by score (highest first)
        usort($suggestions, fn ($a, $b) => $b['score'] <=> $a['score']);

        return $suggestions;
    }
}
