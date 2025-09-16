# Spyglasses for Drupal

[![License: GPL v2+](https://img.shields.io/badge/License-GPL%20v2+-blue.svg)](https://www.gnu.org/licenses/gpl-2.0)
[![Drupal](https://img.shields.io/badge/Drupal-8%20%7C%209%20%7C%2010%20%7C%2011-blue)](https://www.drupal.org)

Spyglasses provides AI traffic analytics for Drupal by detecting, blocking, and logging bot traffic from AI assistants and crawlers using Drupal's middleware API.

## Features

- **AI Bot Detection**: Detect traffic from ChatGPT, Claude, Perplexity, and other AI assistants
- **AI Referrer Tracking**: Track visitors coming from AI platforms
- **Model Training Crawler Detection**: Identify and optionally block AI training crawlers like GPTBot, CCBot, and ClaudeBot
- **Middleware-Based**: Uses Drupal's StackPHP middleware for high-performance request processing
- **Configurable Blocking**: Block or allow specific bots based on your preferences
- **Real-time Analytics**: Log all detected traffic to the Spyglasses platform for analysis
- **Automatic Pattern Updates**: Keep bot detection patterns up-to-date automatically
- **Cache-Aware**: Properly handles caching to avoid false positives

## Installation

### Via Composer (Recommended)

```bash
composer require spyglasses/drupal
```

### Manual Installation

1. Download the module from [GitHub](https://github.com/orchestra-code/spyglasses-drupal)
2. Extract to your `modules/contrib` directory
3. Enable the module via Drupal admin or drush:

```bash
drush en spyglasses
```

## Configuration

### 1. Get Your API Key

Sign up for a free account at [spyglasses.io](https://www.spyglasses.io) to get your API key.

### 2. Configure the Module

1. Navigate to **Configuration > Web Services > Spyglasses** (`/admin/config/services/spyglasses`)
2. Enter your API key
3. Configure your settings:
   - **Debug Mode**: Enable for troubleshooting (logs detailed information)
   - **Auto-sync Patterns**: Automatically update bot patterns daily
   - **Advanced Settings**: Customize endpoints and cache settings if needed

### 3. Sync Patterns

Click "Sync Patterns Now" to fetch the latest bot detection patterns from the Spyglasses API.

## How It Works

Spyglasses uses Drupal's middleware API to intercept HTTP requests early in the request lifecycle. For each request:

1. **Pattern Matching**: Checks the User-Agent against known AI bot patterns
2. **Referrer Detection**: Analyzes the HTTP Referer for AI platform origins
3. **Blocking Decision**: Applies your blocking rules (if configured)
4. **Logging**: Sends detection data to Spyglasses for analytics
5. **Cache Headers**: Sets appropriate cache headers to prevent false positives

### Detected Traffic Types

- **AI Assistants**: ChatGPT-User, Claude-User, Perplexity-User, etc.
- **AI Crawlers**: GPTBot, ClaudeBot, CCBot, Applebot-Extended, etc.
- **AI Referrers**: Traffic from chat.openai.com, claude.ai, perplexity.ai, etc.

## Performance

- **Minimal Overhead**: Middleware runs before full Drupal bootstrap
- **Efficient Caching**: Patterns cached for 24 hours by default
- **Non-blocking Logging**: API calls don't slow down your site
- **Smart Exclusions**: Skips static files and admin pages automatically

## Blocking Configuration

Configure blocking rules in your Spyglasses dashboard:

- **Block AI Model Trainers**: Block crawlers that train AI models
- **Custom Block Rules**: Block specific bots, categories, or patterns
- **Custom Allow Rules**: Always allow specific bots (overrides blocks)

## Debugging

Enable debug mode in the module settings to see detailed logs:

```bash
# View Drupal logs
drush watchdog:show --filter=spyglasses

# Or check the database
SELECT * FROM watchdog WHERE type = 'spyglasses' ORDER BY timestamp DESC;
```

## Requirements

- **Drupal**: 8.8+ | 9.x | 10.x | 11.x
- **PHP**: 7.4+
- **Extensions**: cURL, JSON
- **Permissions**: `administer site configuration` to configure

## Compatibility

### Cache Compatibility

- **Drupal Core Cache**: ✅ Full support
- **Varnish**: ✅ Uses Vary headers
- **Cloudflare**: ✅ Compatible
- **Redis/Memcache**: ✅ Compatible

### Module Compatibility

- **BigPipe**: ✅ Compatible
- **Dynamic Page Cache**: ✅ Compatible
- **Internal Page Cache**: ✅ Compatible
- **Ban Module**: ✅ Can be used together

## API Reference

### Services

```php
// Get the Spyglasses client
$client = \Drupal::service('spyglasses.client');

// Detect bot traffic
$result = $client->detect($user_agent, $referrer);

// Sync patterns manually
$sync_service = \Drupal::service('spyglasses.pattern_sync');
$sync_service->syncIfNeeded();
```

### Configuration

```php
// Get configuration
$config = \Drupal::config('spyglasses.settings');
$api_key = $config->get('api_key');
$debug_mode = $config->get('debug_mode');
```

## Troubleshooting

### Common Issues

**No API Key Error**
- Ensure your API key is set in the configuration
- Check that the key doesn't contain invalid characters

**Patterns Not Syncing**
- Verify your API key is valid
- Check network connectivity to spyglasses.io
- Enable debug mode to see detailed error messages

**High Memory Usage**
- Reduce cache TTL if you have many patterns
- Check for conflicting modules that might interfere

### Debug Information

```bash
# Check module status
drush pm:list | grep spyglasses

# View recent logs
drush watchdog:show --filter=spyglasses --count=20

# Clear caches
drush cache:rebuild

# Test pattern sync
drush php:eval "\Drupal::service('spyglasses.pattern_sync')->syncIfNeeded();"
```

## Development

### Running Tests

```bash
# PHPUnit tests
vendor/bin/phpunit modules/contrib/spyglasses/tests/

# Code standards
vendor/bin/phpcs --standard=Drupal modules/contrib/spyglasses/
```

### Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests if applicable
5. Submit a pull request

## Security

This module processes HTTP requests and makes external API calls. Security considerations:

- **API Key**: Store securely, never commit to version control
- **Input Validation**: All user input is sanitized
- **HTTPS**: All API communication uses HTTPS
- **Permissions**: Requires admin permissions to configure

## Support

- **Documentation**: [docs.spyglasses.io](https://www.spyglasses.io/docs/platforms/drupal)
- **Issues**: [GitHub Issues](https://github.com/orchestra-code/spyglasses-drupal/issues)
- **Email**: support@spyglasses.io
- **Community**: [Drupal.org](https://www.drupal.org/project/spyglasses)

## License

This project is licensed under the GPL-2.0-or-later license. See the [LICENSE](LICENSE) file for details.

---

Made with ❤️ by [Orchestra AI](https://www.spyglasses.io)