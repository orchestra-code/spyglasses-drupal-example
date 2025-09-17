# Event Subscriber Alternative

This example shows how to implement Spyglasses using Drupal's Event Subscriber pattern instead of middleware. While middleware is recommended for performance, event subscribers can be useful in certain scenarios.

## When to Use Event Subscribers

- When you need to integrate with existing event-driven code
- When middleware conflicts with other modules
- When you need more granular control over when detection runs
- For debugging and development purposes

## Implementation

See the `SpyglassesEventSubscriber.php` file for a complete implementation that:

- Subscribes to the `KernelEvents::REQUEST` event
- Performs the same bot detection as the middleware
- Handles blocking and logging
- Works alongside the existing middleware (if both are enabled)

## Performance Considerations

Event subscribers run later in the request lifecycle than middleware, which means:

- Slightly higher overhead (full Drupal bootstrap)
- Less efficient for high-traffic sites
- May not catch all types of requests (e.g., some AJAX calls)

For production sites, middleware is recommended for better performance.

## Configuration

The event subscriber uses the same configuration as the middleware, so no additional setup is required beyond enabling it in your custom module's services.yml.
