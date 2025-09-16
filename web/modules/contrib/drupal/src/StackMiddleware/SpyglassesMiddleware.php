<?php

namespace Drupal\spyglasses\StackMiddleware;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\spyglasses\SpyglassesClient;
use GuzzleHttp\ClientInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Spyglasses middleware for detecting and handling AI bot traffic.
 *
 * This middleware implements the StackPHP pattern used by Drupal core
 * to intercept HTTP requests and detect AI bots and referrers.
 */
class SpyglassesMiddleware implements HttpKernelInterface {

  /**
   * The wrapped HTTP kernel.
   *
   * @var \Symfony\Component\HttpKernel\HttpKernelInterface
   */
  protected $httpKernel;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * The cache backend.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * The Spyglasses client.
   *
   * @var \Drupal\spyglasses\SpyglassesClient
   */
  protected $spyglassesClient;

  /**
   * Paths to exclude from processing.
   *
   * @var array
   */
  protected $excludePaths = [
    '/admin',
    '/batch',
    '/cron',
    '/update.php',
    '/install.php',
    '/core',
    '/modules',
    '/themes',
    '/sites/default/files',
    '/system/files',
  ];

  /**
   * File extensions to exclude from processing.
   *
   * @var array
   */
  protected $excludeExtensions = [
    'css', 'js', 'png', 'jpg', 'jpeg', 'gif', 'svg', 'ico', 
    'woff', 'woff2', 'ttf', 'eot', 'pdf', 'zip', 'tar', 'gz',
    'xml', 'txt', 'json',
  ];

  /**
   * Constructs a SpyglassesMiddleware object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The cache backend.
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The HTTP client.
   */
  public function __construct(ConfigFactoryInterface $config_factory, LoggerChannelFactoryInterface $logger_factory, CacheBackendInterface $cache, ClientInterface $http_client) {
    $this->configFactory = $config_factory;
    $this->loggerFactory = $logger_factory;
    $this->cache = $cache;
    $this->httpClient = $http_client;
    
    // Initialize the Spyglasses client
    $this->spyglassesClient = new SpyglassesClient($config_factory, $logger_factory, $cache, $http_client);
  }

  /**
   * Set the wrapped HTTP kernel.
   *
   * @param \Symfony\Component\HttpKernel\HttpKernelInterface $http_kernel
   *   The wrapped HTTP kernel.
   */
  public function setHttpKernel(HttpKernelInterface $http_kernel) {
    $this->httpKernel = $http_kernel;
  }

  /**
   * {@inheritdoc}
   */
  public function handle(Request $request, $type = self::MASTER_REQUEST, $catch = TRUE) {
    $request_start_time = microtime(TRUE);
    
    // Get configuration
    $config = $this->configFactory->get('spyglasses.settings');
    $api_key = $config->get('api_key');
    $debug_mode = $config->get('debug_mode', FALSE);
    
    // Skip processing if no API key is configured
    if (empty($api_key)) {
      if ($debug_mode) {
        $this->loggerFactory->get('spyglasses')->debug('Skipping detection - no API key configured');
      }
      return $this->httpKernel->handle($request, $type, $catch);
    }
    
    // Skip excluded paths and file types
    if ($this->shouldExcludeRequest($request)) {
      if ($debug_mode) {
        $this->loggerFactory->get('spyglasses')->debug('Skipping excluded path: @path', ['@path' => $request->getPathInfo()]);
      }
      return $this->httpKernel->handle($request, $type, $catch);
    }
    
    // Extract request information
    $user_agent = $request->headers->get('User-Agent', '');
    $referrer = $request->headers->get('Referer', '');
    
    if ($debug_mode) {
      $this->loggerFactory->get('spyglasses')->debug('Processing request to @path', ['@path' => $request->getPathInfo()]);
      $this->loggerFactory->get('spyglasses')->debug('User-Agent: @ua', ['@ua' => substr($user_agent, 0, 100)]);
      if ($referrer) {
        $this->loggerFactory->get('spyglasses')->debug('Referrer: @referrer', ['@referrer' => $referrer]);
      }
    }
    
    // Detect bot or AI referrer
    $detection_result = $this->spyglassesClient->detect($user_agent, $referrer);
    
    if ($detection_result['source_type'] !== 'none') {
      if ($debug_mode) {
        $this->loggerFactory->get('spyglasses')->debug('Detection result: @result', ['@result' => json_encode($detection_result)]);
      }
      
      // Handle blocking
      if ($detection_result['should_block']) {
        if ($debug_mode) {
          $this->loggerFactory->get('spyglasses')->debug('Blocking request from @type: @pattern', [
            '@type' => $detection_result['source_type'],
            '@pattern' => $detection_result['matched_pattern']
          ]);
        }
        
        // Log the blocked request
        $this->logRequestAsync($detection_result, $request, 403, microtime(TRUE) - $request_start_time);
        
        // Return 403 Forbidden response
        return $this->createForbiddenResponse();
      }
    }
    
    // Process the request normally
    try {
      $response = $this->httpKernel->handle($request, $type, $catch);
      $response_time = microtime(TRUE) - $request_start_time;
      
      // Log successful requests with detection results
      if ($detection_result['source_type'] !== 'none') {
        $this->logRequestAsync($detection_result, $request, $response->getStatusCode(), $response_time);
      }
      
      // Add cache vary headers for detected traffic
      if ($detection_result['source_type'] !== 'none') {
        $this->addVaryHeaders($response);
      }
      
      return $response;
    }
    catch (\Exception $e) {
      // Log error requests if we detected something
      if ($detection_result['source_type'] !== 'none') {
        $response_time = microtime(TRUE) - $request_start_time;
        $this->logRequestAsync($detection_result, $request, 500, $response_time);
      }
      
      throw $e;
    }
  }

  /**
   * Check if the request should be excluded from processing.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return bool
   *   TRUE if the request should be excluded, FALSE otherwise.
   */
  protected function shouldExcludeRequest(Request $request) {
    $path = $request->getPathInfo();
    
    // Check excluded paths
    foreach ($this->excludePaths as $exclude_path) {
      if (strpos($path, $exclude_path) === 0) {
        return TRUE;
      }
    }
    
    // Check file extensions
    $path_extension = pathinfo($path, PATHINFO_EXTENSION);
    if ($path_extension && in_array(strtolower($path_extension), $this->excludeExtensions)) {
      return TRUE;
    }
    
    return FALSE;
  }

  /**
   * Log a request asynchronously to the Spyglasses collector.
   *
   * @param array $detection_result
   *   The detection result.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   * @param int $status_code
   *   The response status code.
   * @param float $response_time
   *   The response time in seconds.
   */
  protected function logRequestAsync(array $detection_result, Request $request, $status_code, $response_time) {
    $config = $this->configFactory->get('spyglasses.settings');
    $api_key = $config->get('api_key');
    
    if (empty($api_key)) {
      return;
    }
    
    // Prepare request information
    $request_info = [
      'url' => $request->getUri(),
      'user_agent' => $request->headers->get('User-Agent', ''),
      'ip_address' => $this->getClientIp($request),
      'request_method' => $request->getMethod(),
      'request_path' => $request->getPathInfo(),
      'request_query' => $request->getQueryString() ?: '',
      'referrer' => $request->headers->get('Referer'),
      'response_status' => $status_code,
      'response_time_ms' => round($response_time * 1000),
      'headers' => $this->extractHeaders($request),
    ];
    
    // Log the request using the client
    $this->spyglassesClient->logRequest($detection_result, $request_info);
    
    if ($config->get('debug_mode', FALSE)) {
      $this->loggerFactory->get('spyglasses')->debug('Logging @type visit: @pattern', [
        '@type' => $detection_result['source_type'],
        '@pattern' => $detection_result['matched_pattern']
      ]);
    }
  }

  /**
   * Extract client IP address from request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return string
   *   The client IP address.
   */
  protected function getClientIp(Request $request) {
    // Try various headers to get the real client IP
    $ip_headers = [
      'HTTP_X_FORWARDED_FOR',
      'HTTP_X_REAL_IP',
      'HTTP_CF_CONNECTING_IP',
      'HTTP_X_CLIENT_IP',
      'REMOTE_ADDR',
    ];
    
    foreach ($ip_headers as $header) {
      $ip = $request->server->get($header);
      if ($ip && $ip !== '127.0.0.1' && filter_var($ip, FILTER_VALIDATE_IP)) {
        // Handle comma-separated lists (X-Forwarded-For)
        if (strpos($ip, ',') !== FALSE) {
          $ip = trim(explode(',', $ip)[0]);
        }
        return $ip;
      }
    }
    
    return $request->getClientIp() ?: '127.0.0.1';
  }

  /**
   * Extract HTTP headers from request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return array
   *   Array of HTTP headers.
   */
  protected function extractHeaders(Request $request) {
    $headers = [];
    foreach ($request->headers->all() as $name => $values) {
      $headers[$name] = is_array($values) ? implode(', ', $values) : $values;
    }
    return $headers;
  }

  /**
   * Create a 403 Forbidden response.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The forbidden response.
   */
  protected function createForbiddenResponse() {
    $response = new Response('Access Denied', 403, [
      'Content-Type' => 'text/plain',
      'Cache-Control' => 'private, no-store, max-age=0',
      'Vary' => 'User-Agent',
    ]);
    
    return $response;
  }

  /**
   * Add vary headers to response for cache management.
   *
   * @param \Symfony\Component\HttpFoundation\Response $response
   *   The response object.
   */
  protected function addVaryHeaders(Response $response) {
    $existing_vary = $response->headers->get('Vary', '');
    
    if ($existing_vary && strpos($existing_vary, 'User-Agent') === FALSE) {
      $response->headers->set('Vary', $existing_vary . ', User-Agent');
    }
    elseif (!$existing_vary) {
      $response->headers->set('Vary', 'User-Agent');
    }
  }

}
