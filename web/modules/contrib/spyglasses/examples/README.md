# Spyglasses Drupal Examples

This directory contains examples showing how to use and extend Spyglasses with Drupal.

## Examples

- [`basic-usage/`](./basic-usage/) - Basic installation and configuration
- [`custom-middleware/`](./custom-middleware/) - Creating custom middleware that works with Spyglasses
- [`event-subscriber/`](./event-subscriber/) - Alternative event subscriber implementation
- [`api-integration/`](./api-integration/) - Direct API integration examples
- [`drush-commands/`](./drush-commands/) - Custom Drush commands for Spyglasses

## Configuration

All examples assume you have:

1. A valid Spyglasses API key from [spyglasses.io](https://www.spyglasses.io)
2. Drupal 8.8+ installed
3. The Spyglasses module enabled

## Environment Variables

You can set your API key via environment variable:

```bash
export SPYGLASSES_API_KEY="your-api-key-here"
```

Or in your `settings.php`:

```php
$config['spyglasses.settings']['api_key'] = getenv('SPYGLASSES_API_KEY');
```
