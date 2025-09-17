# Changelog

All notable changes to the Spyglasses Drupal module will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2024-09-11

### Added
- Initial release of Spyglasses for Drupal
- Middleware-based AI bot detection using Drupal's StackPHP middleware API
- Support for detecting AI assistants (ChatGPT, Claude, Perplexity, etc.)
- Support for detecting AI model training crawlers (GPTBot, CCBot, ClaudeBot, etc.)
- AI referrer detection for traffic from AI platforms
- Configurable blocking rules via Spyglasses dashboard
- Admin configuration form with API key management
- Automatic pattern syncing from Spyglasses API
- Debug mode for troubleshooting
- Comprehensive logging to Spyglasses collector API
- Cache-aware implementation with proper Vary headers
- Support for Drupal 8.8+, 9.x, 10.x, and 11.x
- Composer package support
- Extensive documentation and examples
- Event subscriber alternative implementation
- Direct API integration examples
- Performance optimizations for high-traffic sites

### Features
- **High Performance**: Middleware runs before full Drupal bootstrap
- **Smart Caching**: 24-hour pattern cache with configurable TTL
- **Non-blocking**: API calls don't slow down your site
- **Comprehensive**: Detects both direct bot traffic and AI referrers
- **Configurable**: Flexible blocking rules and custom patterns
- **Secure**: All API communication over HTTPS
- **Compatible**: Works with all major Drupal cache systems

### Security
- Input sanitization for all user data
- Secure API key storage
- HTTPS-only API communication
- Proper permission checks for admin functions
