# Basic Usage Example

This example shows the simplest way to get started with Spyglasses for Drupal.

## Installation

1. Install the module via Composer:
   ```bash
   composer require spyglasses/drupal
   ```

2. Enable the module:
   ```bash
   drush en spyglasses
   ```

3. Configure your API key:
   ```bash
   drush config:set spyglasses.settings api_key "your-api-key-here"
   ```

4. Sync patterns:
   ```bash
   drush php:eval "\Drupal::service('spyglasses.pattern_sync')->syncIfNeeded();"
   ```

## Configuration via Admin UI

1. Navigate to `/admin/config/services/spyglasses`
2. Enter your API key
3. Enable debug mode for testing
4. Click "Sync Patterns Now"

## Testing

Test bot detection with curl:

```bash
# Test ChatGPT bot detection
curl -H "User-Agent: ChatGPT-User/1.0" http://your-drupal-site.com/

# Test AI referrer detection  
curl -H "Referer: https://chat.openai.com/" http://your-drupal-site.com/

# Test normal visitor (should not be detected)
curl -H "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36" http://your-drupal-site.com/
```

## Viewing Logs

Check the Drupal logs for Spyglasses activity:

```bash
# View recent Spyglasses logs
drush watchdog:show --filter=spyglasses

# View all logs in real-time
drush watchdog:tail --filter=spyglasses
```

## Environment Configuration

For production, set your API key via environment variable:

```bash
# In your .env file
SPYGLASSES_API_KEY=your-api-key-here
```

Then in your `settings.php`:

```php
if ($api_key = getenv('SPYGLASSES_API_KEY')) {
  $config['spyglasses.settings']['api_key'] = $api_key;
}
```
