<?php

namespace JordanPartridge\ConduitSpotify\Contracts;

interface AuthInterface
{
    /**
     * Get the authorization URL for OAuth flow.
     */
    public function getAuthorizationUrl(array $scopes = []): string;

    /**
     * Exchange authorization code for access token.
     */
    public function exchangeCodeForToken(string $code): array;

    /**
     * Refresh an expired access token.
     */
    public function refreshToken(string $refreshToken): array;

    /**
     * Get current access token.
     */
    public function getAccessToken(): ?string;

    /**
     * Check if user is authenticated.
     */
    public function isAuthenticated(): bool;

    /**
     * Ensure user is authenticated with automatic token refresh.
     */
    public function ensureAuthenticated(): bool;

    /**
     * Revoke current authentication.
     */
    public function revoke(): bool;
}
