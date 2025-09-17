# API Integration Example

This example shows how to integrate directly with the Spyglasses client API for custom use cases.

## Use Cases

- Custom form handlers that need bot detection
- API endpoints that want to log AI traffic
- Custom modules that need to check for bots
- Integration with external services

## Direct Client Usage

```php
<?php

// Get the Spyglasses client service
$client = \Drupal::service('spyglasses.client');

// Detect bot from user agent and referrer
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
$referrer = $_SERVER['HTTP_REFERER'] ?? '';

$detection_result = $client->detect($user_agent, $referrer);

if ($detection_result['source_type'] !== 'none') {
  // This is a bot or AI referrer
  $source_type = $detection_result['source_type']; // 'bot' or 'ai_referrer'
  $should_block = $detection_result['should_block']; // true/false
  $pattern = $detection_result['matched_pattern']; // matched pattern
  $info = $detection_result['info']; // detailed bot/referrer info
  
  if ($should_block) {
    // Handle blocking logic
    return new Response('Access Denied', 403);
  }
  
  // Log the detection (optional - middleware already does this)
  $request_info = [
    'url' => $request->getUri(),
    'user_agent' => $user_agent,
    'ip_address' => $request->getClientIp(),
    'request_method' => $request->getMethod(),
    'request_path' => $request->getPathInfo(),
    'request_query' => $request->getQueryString() ?: '',
    'referrer' => $referrer,
    'response_status' => 200,
    'response_time_ms' => 0,
    'headers' => [],
  ];
  
  $client->logRequest($detection_result, $request_info);
}
```

## Pattern Management

```php
<?php

// Sync patterns manually
$client = \Drupal::service('spyglasses.client');
$result = $client->syncPatterns();

if ($result === TRUE) {
  \Drupal::messenger()->addMessage('Patterns synced successfully');
} else {
  \Drupal::messenger()->addError('Sync failed: ' . $result);
}

// Check last sync time
$config = \Drupal::config('spyglasses.settings');
$last_sync = $config->get('last_pattern_sync');
if ($last_sync) {
  $last_sync_formatted = \Drupal::service('date.formatter')->format($last_sync, 'medium');
  echo "Last synced: $last_sync_formatted";
}
```

## Custom Form Integration

See the `CustomFormHandler.php` file for an example of integrating bot detection into a custom form handler.
