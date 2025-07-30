# Contributing to Conduit Spotify Component

## Welcome! 🎵

Thank you for your interest in contributing to the Conduit Spotify component! This component brings rich Spotify integration to the Conduit ecosystem.

## 🚀 Getting Started

### Prerequisites
- PHP 8.2+
- Composer
- Spotify Developer Account
- Conduit framework installed

### Development Setup
```bash
# Clone the repository
git clone https://github.com/jordanpartridge/conduit-spotify.git
cd conduit-spotify

# Install dependencies
composer install

# Set up your environment
cp .env.example .env

# Configure Spotify credentials
conduit spotify:setup
```

## 🧪 Testing

### Running Tests
```bash
# Run all tests
composer test

# Run with coverage
composer test:coverage

# Run specific test suite
./vendor/bin/pest tests/Feature/
```

### Certification Testing
```bash
# Run component certification
conduit component:certify . --level=gold

# Check specific test categories
conduit component:certify . --format=json
```

## 📋 Development Guidelines

### Code Standards
- Follow PSR-12 coding standards
- Maintain >80% test coverage
- Use type hints for all parameters and return types
- Write descriptive commit messages

### Command Development
```php
// All commands should extend the base command class
class NewCommand extends Command 
{
    protected $signature = 'spotify:new {argument} {--option}';
    protected $description = 'Clear description of what this does';
    
    public function handle(): int 
    {
        // Implementation with proper error handling
        try {
            // Command logic
            return 0;
        } catch (\Exception $e) {
            $this->error($e->getMessage());
            return 1;
        }
    }
}
```

### Event System Integration
```php
// Dispatch events for component interoperability
Event::dispatch('spotify.track.changed', [
    'previous' => $previousTrack,
    'current' => $currentTrack,
    'timestamp' => now()
]);
```

## 🏆 Certification Requirements

### Bronze Level
- [ ] Basic functionality tests pass
- [ ] Proper command registration
- [ ] Clean installation/uninstallation

### Silver Level  
- [ ] >80% test coverage
- [ ] Performance benchmarks met
- [ ] Security validation passed
- [ ] Event system integration

### Gold Level
- [ ] Advanced feature integration
- [ ] Community documentation (this file!)
- [ ] Multi-environment testing
- [ ] Ecosystem contribution

## 🔄 Pull Request Process

### Before Submitting
1. Run the full test suite: `composer test`
2. Check code style: `composer cs:check`
3. Run certification: `conduit component:certify . --level=gold`
4. Update documentation if needed

### PR Requirements
- [ ] Clear description of changes
- [ ] Tests for new functionality
- [ ] Documentation updates
- [ ] Certification tests pass
- [ ] No breaking changes (or clearly documented)

### Review Process
1. Automated CI checks
2. Code quality review
3. Manual testing
4. Security review (if applicable)
5. Merge approval

## 🎯 Feature Roadmap

### Current Features
- Basic playback control (play, pause, skip)
- Current track information
- Device management
- Volume control

### Planned Features
- [ ] Library management (#9)
- [ ] Queue management (#5)  
- [ ] Advanced search (#7)
- [ ] Listening analytics (#6)
- [ ] Event dispatching (#1)

## 🐛 Bug Reports

### Before Reporting
- Check existing issues
- Verify it's not a configuration issue
- Test with latest version

### Bug Report Template
```markdown
**Description**: Clear description of the bug

**Steps to Reproduce**:
1. Run command: `conduit spotify:example`
2. Expected behavior
3. Actual behavior

**Environment**:
- Conduit version: 
- PHP version:
- OS:
- Spotify app version:

**Additional Context**: 
Any error messages, logs, or screenshots
```

## 🤝 Community

### Getting Help
- GitHub Issues for bugs/features
- Discussions for general questions
- Discord community (link in main Conduit repo)

### Recognition
Contributors will be:
- Listed in CHANGELOG.md
- Mentioned in release notes
- Added to GitHub contributors

## 📄 License

By contributing, you agree that your contributions will be licensed under the MIT License.

---

*Thank you for helping make the Conduit Spotify component awesome! 🎵✨*