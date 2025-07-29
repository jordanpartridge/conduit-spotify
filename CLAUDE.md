# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is the Spotify integration component for Conduit, providing comprehensive music control during development workflows. It features 16 commands organized into logical groups with rich terminal interfaces and intelligent automation.

## Development Commands

```bash
# Install dependencies
composer install

# Code quality and testing
./vendor/bin/pint                    # Laravel PHP formatter
./vendor/bin/phpstan analyse         # Static analysis (level max)
./vendor/bin/pest                    # Run tests (Pest framework)

# Quality shortcuts
composer lint                       # Run Pint formatter
composer analyse                    # Run PHPStan analysis
composer test                       # Run Pest tests
composer quality                    # Run all quality checks
```

## Architecture

### Command Group Organization
Commands are organized into logical groups within `src/Commands/`:

#### **Playback Group** (`src/Commands/Playback/`)
Core music control commands:
- `PlayCommand` - Start/resume playback with search and preset support
- `PauseCommand` - Pause current track
- `SkipCommand` / `NextCommand` - Skip to next track  
- `CurrentCommand` - Show current track information
- `VolumeCommand` - Control volume (0-100)

#### **Library Group** (`src/Commands/Library/`)
Music discovery and management:
- `SearchCommand` - Search tracks, artists, playlists
- `PlaylistsCommand` - Browse and manage playlists
- `QueueCommand` - View and manage playback queue
- `FocusCommand` - AI-curated focus playlists

#### **System Group** (`src/Commands/System/`)
Authentication and device management:
- `LoginCommand` / `LogoutCommand` - Spotify authentication
- `DevicesCommand` - List and manage playback devices
- `SetupCommand` / `ConfigureCommand` - Initial setup and configuration

#### **Analytics Group** (`src/Commands/Analytics/`)
Intelligence features:
- `AnalyticsCommand` - Music taste analysis and insights

### Service Layer Architecture
- **ApiInterface/Api** - Spotify Web API integration with Guzzle HTTP client
- **AuthInterface/Auth** - OAuth 2.0 authentication flow management
- **DeviceManager** - Spotify Connect device discovery and management
- **IntelligentAnalyticsService** - Music taste analysis and recommendations

### Concerns (Traits)
Reusable functionality across commands:
- `HandlesAuthentication` - Authentication retry logic with automatic login
- `ShowsSpotifyStatus` - Rich terminal status displays
- `SendsNotifications` - Desktop notifications for track changes
- `ManagesSpotifyDevices` - Device selection and activation

## Configuration

### Spotify API Setup
1. Create app at https://developer.spotify.com/dashboard
2. Configure redirect URI: `http://localhost:8888/callback`
3. Set environment variables:
   ```bash
   SPOTIFY_CLIENT_ID=your_client_id
   SPOTIFY_CLIENT_SECRET=your_client_secret
   ```

### Required Scopes
- `user-read-playback-state` - Read current playback
- `user-modify-playback-state` - Control playback
- `user-read-currently-playing` - Get current track
- `playlist-read-private` - Access private playlists
- `playlist-modify-public/private` - Modify playlists

## Command Usage Patterns

### Interactive Mode
Most commands support `--interactive` flag for rich terminal selection:
```bash
spotify:play --interactive        # Browse and select music
spotify:devices --interactive     # Select playback device
spotify:playlists --interactive   # Browse playlists
```

### Search and Presets
The play command supports multiple input types:
```bash
spotify:play "Bohemian Rhapsody"           # Search query
spotify:play spotify:track:4u7EnebtmKWzUH  # Spotify URI
spotify:play focus-music                   # Preset shortcut
```

### Error Handling
Commands provide helpful error messages with actionable suggestions:
- Missing authentication → automatic login prompts
- No active devices → device activation guidance  
- Premium required → subscription upgrade prompts

## Testing Strategy

- **Feature Tests**: Command registration and basic functionality
- **Unit Tests**: Service layer and authentication flows
- **Integration Tests**: Spotify API interactions (with mocking)
- **Command Tests**: Terminal output and interactive prompts