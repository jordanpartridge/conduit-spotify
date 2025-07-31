<?php

namespace JordanPartridge\ConduitSpotify\Concerns;

/**
 * Provides consistent output formatting for Spotify commands
 * Supports both interactive and JSON formats for human and programmatic use
 */
trait HandlesSpotifyOutput
{
    /**
     * Output data in JSON format with consistent structure
     */
    protected function outputJson(array $data, int $exitCode = 0): int
    {
        $response = [
            'timestamp' => now()->toISOString(),
            'command' => $this->getName(),
            'success' => $exitCode === 0,
            'data' => $data,
        ];

        $this->output->writeln(json_encode($response, JSON_PRETTY_PRINT));

        return $exitCode;
    }

    /**
     * Handle API errors with consistent formatting
     */
    protected function handleApiError(\Exception $e, ?string $context = null): int
    {
        $error = [
            'error' => $e->getMessage(),
            'type' => get_class($e),
        ];

        if ($context) {
            $error['context'] = $context;
        }

        if ($this->option('format') === 'json') {
            return $this->outputJson($error, 1);
        }

        $this->error("❌ {$e->getMessage()}");

        return 1;
    }

    /**
     * Handle authentication errors with consistent messaging
     */
    protected function handleAuthError(): int
    {
        $error = [
            'error' => 'Not authenticated with Spotify',
            'action' => 'Run: conduit spotify:login',
        ];

        if ($this->option('format') === 'json') {
            return $this->outputJson($error, 1);
        }

        $this->error('❌ Not authenticated with Spotify');
        $this->info('💡 Run: conduit spotify:login');

        return 1;
    }

    /**
     * Check if command should run in interactive mode
     */
    protected function isInteractive(): bool
    {
        return $this->option('format') === 'interactive' &&
               ! $this->option('non-interactive');
    }
}
