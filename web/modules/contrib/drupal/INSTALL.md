# Installation Guide

This guide walks you through installing and configuring the Spyglasses module for Drupal.

## Prerequisites

- Drupal 8.8+ | 9.x | 10.x | 11.x
- PHP 7.4+
- cURL and JSON extensions enabled
- A Spyglasses API key from [spyglasses.io](https://www.spyglasses.io)

## Installation Methods

### Method 1: Composer (Recommended)

```bash
# Add the module to your project
composer require spyglasses/drupal

# Enable the module
drush en spyglasses

# Or via Drupal admin UI
# Navigate to Extend (/admin/modules) and enable "Spyglasses - AI Traffic Analytics"
```

### Method 2: Manual Installation

1. Download the latest release from GitHub
2. Extract to `modules/contrib/spyglasses/`
3. Enable via Drush or admin UI:

```bash
drush en spyglasses
```

## Configuration

### 1. Get Your API Key

1. Sign up for a free account at [spyglasses.io](https://www.spyglasses.io)
2. Copy your API key from the dashboard

### 2. Configure the Module

#### Via Admin UI

1. Navigate to **Configuration > Web Services > Spyglasses**
   (`/admin/config/services/spyglasses`)
2. Enter your API key
3. Configure settings:
   - **Debug Mode**: Enable for troubleshooting
   - **Auto-sync Patterns**: Keep enabled for automatic updates
4. Click "Sync Patterns Now" to fetch the latest bot patterns
5. Save configuration

#### Via Drush

```bash
# Set API key
drush config:set spyglasses.settings api_key "your-api-key-here"

# Enable debug mode (optional)
drush config:set spyglasses.settings debug_mode true

# Sync patterns
drush php:eval "\Drupal::service('spyglasses.pattern_sync')->syncIfNeeded();"
```

#### Via settings.php (Production)

```php
// In your settings.php file
$config['spyglasses.settings']['api_key'] = getenv('SPYGLASSES_API_KEY');
$config['spyglasses.settings']['debug_mode'] = FALSE;
$config['spyglasses.settings']['auto_sync'] = TRUE;
```

Then set the environment variable:

```bash
export SPYGLASSES_API_KEY="your-api-key-here"
```

## Verification

### 1. Check Module Status

```bash
# Verify module is enabled
drush pm:list | grep spyglasses

# Check configuration
drush config:get spyglasses.settings
```

### 2. Test Bot Detection

```bash
# Test with ChatGPT user agent (should be detected but not blocked by default)
curl -H "User-Agent: ChatGPT-User/1.0" http://your-drupal-site.com/

# Test with GPTBot (may be blocked if configured)
curl -H "User-Agent: GPTBot/1.0" http://your-drupal-site.com/

# Test AI referrer
curl -H "Referer: https://chat.openai.com/" http://your-drupal-site.com/
```

### 3. Check Logs

```bash
# View Spyglasses logs
drush watchdog:show --filter=spyglasses

# View in real-time
drush watchdog:tail --filter=spyglasses
```

## Troubleshooting

### Common Issues

**"No API key configured" error**
- Ensure your API key is set correctly
- Check that it doesn't contain invalid characters
- Verify the key is active in your Spyglasses dashboard

**Patterns not syncing**
- Check network connectivity to spyglasses.io
- Verify your API key is valid
- Enable debug mode to see detailed error messages

**High memory usage**
- Reduce cache TTL in advanced settings
- Check for module conflicts

### Debug Mode

Enable debug mode to see detailed information:

1. Go to **Configuration > Web Services > Spyglasses**
2. Check "Enable debug mode"
3. Save configuration
4. View logs with `drush watchdog:show --filter=spyglasses`

### Clear Caches

If you're experiencing issues, try clearing caches:

```bash
drush cache:rebuild
```

## Performance Optimization

### Production Settings

```php
// Recommended production settings in settings.php
$config['spyglasses.settings']['debug_mode'] = FALSE;
$config['spyglasses.settings']['cache_ttl'] = 86400; // 24 hours
```

### Cache Considerations

The module automatically sets cache headers to prevent false positives:

- Uses `Vary: User-Agent` headers
- Respects existing cache configurations
- Works with Varnish, Cloudflare, and other CDNs

## Security

- Store API keys securely (use environment variables in production)
- Keep the module updated
- Monitor logs for suspicious activity
- Configure appropriate blocking rules in your Spyglasses dashboard

## Support

- **Documentation**: [docs.spyglasses.io](https://docs.spyglasses.io)
- **Issues**: [GitHub Issues](https://github.com/spyglasses/spyglasses-drupal/issues)
- **Email**: support@spyglasses.io
