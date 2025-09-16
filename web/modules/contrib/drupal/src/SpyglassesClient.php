<?php

namespace Drupal\spyglasses;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;

/**
 * Spyglasses client for bot and AI referrer detection.
 */
class SpyglassesClient {

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
   * Bot patterns.
   *
   * @var array
   */
  protected $patterns = [];

  /**
   * AI referrer patterns.
   *
   * @var array
   */
  protected $aiReferrers = [];

  /**
   * Property settings from API.
   *
   * @var array
   */
  protected $propertySettings = [
    'block_ai_model_trainers' => FALSE,
    'custom_blocks' => [],
    'custom_allows' => [],
  ];

  /**
   * Default collector endpoint.
   */
  const COLLECTOR_ENDPOINT = 'https://www.spyglasses.io/api/collect';

  /**
   * Default patterns endpoint.
   */
  const PATTERNS_ENDPOINT = 'https://www.spyglasses.io/api/patterns';

  /**
   * Cache key for patterns.
   */
  const PATTERNS_CACHE_KEY = 'spyglasses_patterns';

  /**
   * Cache TTL for patterns (24 hours).
   */
  const PATTERNS_CACHE_TTL = 86400;

  /**
   * Constructs a SpyglassesClient object.
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
    
    $this->loadDefaultPatterns();
    $this->loadCachedPatterns();
  }

  /**
   * Detect bot or AI referrer from user agent and referrer.
   *
   * @param string $user_agent
   *   The user agent string.
   * @param string $referrer
   *   The referrer URL.
   *
   * @return array
   *   Detection result array.
   */
  public function detect($user_agent = '', $referrer = '') {
    $config = $this->configFactory->get('spyglasses.settings');
    $debug_mode = $config->get('debug_mode', FALSE);
    
    if ($debug_mode) {
      $ua_display = $user_agent ? substr($user_agent, 0, 100) . (strlen($user_agent) > 100 ? '...' : '') : 'None';
      $this->loggerFactory->get('spyglasses')->debug('detect() called with user_agent: "@ua", referrer: "@referrer"', [
        '@ua' => $ua_display,
        '@referrer' => $referrer ?: 'None'
      ]);
    }
    
    // Check for bot first
    $bot_result = $this->detectBot($user_agent);
    if ($bot_result['source_type'] === 'bot') {
      if ($debug_mode) {
        $this->loggerFactory->get('spyglasses')->debug('🤖 Final result: BOT detected, returning bot result');
      }
      return $bot_result;
    }
    
    // Check for AI referrer if provided
    if ($referrer) {
      if ($debug_mode) {
        $this->loggerFactory->get('spyglasses')->debug('No bot detected, starting AI referrer detection...');
      }
      $referrer_result = $this->detectAiReferrer($referrer);
      if ($referrer_result['source_type'] === 'ai_referrer') {
        if ($debug_mode) {
          $this->loggerFactory->get('spyglasses')->debug('🧠 Final result: AI REFERRER detected, returning referrer result');
        }
        return $referrer_result;
      }
    }
    elseif ($debug_mode) {
      $this->loggerFactory->get('spyglasses')->debug('No referrer provided, skipping AI referrer detection');
    }
    
    return [
      'source_type' => 'none',
      'should_block' => FALSE,
      'matched_pattern' => NULL,
      'info' => NULL,
    ];
  }

  /**
   * Detect if a user agent is a bot.
   *
   * @param string $user_agent
   *   The user agent string.
   *
   * @return array
   *   Detection result array.
   */
  public function detectBot($user_agent) {
    if (empty($user_agent)) {
      return [
        'source_type' => 'none',
        'should_block' => FALSE,
        'matched_pattern' => NULL,
        'info' => NULL,
      ];
    }
    
    $config = $this->configFactory->get('spyglasses.settings');
    $debug_mode = $config->get('debug_mode', FALSE);
    
    if ($debug_mode) {
      $this->loggerFactory->get('spyglasses')->debug('Checking user agent: "@ua"', ['@ua' => substr($user_agent, 0, 150) . (strlen($user_agent) > 150 ? '...' : '')]);
      $this->loggerFactory->get('spyglasses')->debug('Testing against @count bot patterns', ['@count' => count($this->patterns)]);
    }
    
    foreach ($this->patterns as $pattern) {
      try {
        if ($debug_mode) {
          $this->loggerFactory->get('spyglasses')->debug('Testing pattern: "@pattern" (@type - @company)', [
            '@pattern' => $pattern['pattern'],
            '@type' => $pattern['type'] ?? 'unknown',
            '@company' => $pattern['company'] ?? 'unknown company'
          ]);
        }
        
        // Use preg_match with case-insensitive flag
        if (preg_match('/' . preg_quote($pattern['pattern'], '/') . '/i', $user_agent)) {
          $should_block = $this->shouldBlockPattern($pattern);
          
          if ($debug_mode) {
            $this->loggerFactory->get('spyglasses')->debug('✅ BOT DETECTED! Pattern matched: "@pattern"', ['@pattern' => $pattern['pattern']]);
            $this->loggerFactory->get('spyglasses')->debug('Bot details: type=@type, category=@category, subcategory=@subcategory, company=@company, is_ai_model_trainer=@trainer, should_block=@block', [
              '@type' => $pattern['type'] ?? 'unknown',
              '@category' => $pattern['category'] ?? 'Unknown',
              '@subcategory' => $pattern['subcategory'] ?? 'Unclassified',
              '@company' => $pattern['company'] ?? 'null',
              '@trainer' => ($pattern['is_ai_model_trainer'] ?? FALSE) ? 'true' : 'false',
              '@block' => $should_block ? 'true' : 'false'
            ]);
          }
          
          return [
            'source_type' => 'bot',
            'should_block' => $should_block,
            'matched_pattern' => $pattern['pattern'],
            'info' => $pattern,
          ];
        }
      }
      catch (\Exception $e) {
        if ($debug_mode) {
          $this->loggerFactory->get('spyglasses')->debug('Error with pattern @pattern: @error', [
            '@pattern' => $pattern['pattern'],
            '@error' => $e->getMessage()
          ]);
        }
      }
    }
    
    if ($debug_mode) {
      $this->loggerFactory->get('spyglasses')->debug('No bot patterns matched user agent');
    }
    
    return [
      'source_type' => 'none',
      'should_block' => FALSE,
      'matched_pattern' => NULL,
      'info' => NULL,
    ];
  }

  /**
   * Detect if a referrer is from an AI platform.
   *
   * @param string $referrer
   *   The referrer URL.
   *
   * @return array
   *   Detection result array.
   */
  public function detectAiReferrer($referrer) {
    if (empty($referrer)) {
      return [
        'source_type' => 'none',
        'should_block' => FALSE,
        'matched_pattern' => NULL,
        'info' => NULL,
      ];
    }
    
    $config = $this->configFactory->get('spyglasses.settings');
    $debug_mode = $config->get('debug_mode', FALSE);
    
    if ($debug_mode) {
      $this->loggerFactory->get('spyglasses')->debug('Checking referrer: "@referrer"', ['@referrer' => $referrer]);
    }
    
    // Extract hostname from referrer
    $hostname = $this->extractHostname($referrer);
    if ($debug_mode) {
      $this->loggerFactory->get('spyglasses')->debug('Extracted hostname: "@hostname"', ['@hostname' => $hostname]);
    }
    
    foreach ($this->aiReferrers as $ai_referrer) {
      if ($debug_mode) {
        $this->loggerFactory->get('spyglasses')->debug('Testing AI referrer: "@name" (@company) with patterns: @patterns', [
          '@name' => $ai_referrer['name'],
          '@company' => $ai_referrer['company'],
          '@patterns' => implode(', ', $ai_referrer['patterns'])
        ]);
      }
      
      foreach ($ai_referrer['patterns'] as $pattern) {
        if ($debug_mode) {
          $this->loggerFactory->get('spyglasses')->debug('Testing AI referrer pattern: "@pattern" against hostname: "@hostname"', [
            '@pattern' => $pattern,
            '@hostname' => $hostname
          ]);
        }
        
        if (strpos($hostname, $pattern) !== FALSE) {
          if ($debug_mode) {
            $this->loggerFactory->get('spyglasses')->debug('✅ AI REFERRER DETECTED! Pattern matched: "@pattern"', ['@pattern' => $pattern]);
            $this->loggerFactory->get('spyglasses')->debug('AI referrer details: name=@name, company=@company, id=@id', [
              '@name' => $ai_referrer['name'],
              '@company' => $ai_referrer['company'],
              '@id' => $ai_referrer['id']
            ]);
          }
          
          return [
            'source_type' => 'ai_referrer',
            'should_block' => FALSE, // AI referrers are never blocked
            'matched_pattern' => $pattern,
            'info' => $ai_referrer,
          ];
        }
      }
    }
    
    return [
      'source_type' => 'none',
      'should_block' => FALSE,
      'matched_pattern' => NULL,
      'info' => NULL,
    ];
  }

  /**
   * Log a request to the Spyglasses collector.
   *
   * @param array $detection_result
   *   The detection result.
   * @param array $request_info
   *   Request information array.
   */
  public function logRequest(array $detection_result, array $request_info) {
    $config = $this->configFactory->get('spyglasses.settings');
    $api_key = $config->get('api_key');
    $debug_mode = $config->get('debug_mode', FALSE);
    
    if (empty($api_key) || $detection_result['source_type'] === 'none') {
      return;
    }
    
    if ($debug_mode) {
      $this->loggerFactory->get('spyglasses')->debug('log_request() called for source_type: @type', ['@type' => $detection_result['source_type']]);
      $this->loggerFactory->get('spyglasses')->debug('Preparing to log @type event to collector', ['@type' => $detection_result['source_type']]);
    }
    
    // Prepare metadata
    $metadata = ['was_blocked' => $detection_result['should_block']];
    
    if ($detection_result['source_type'] === 'bot' && $detection_result['info']) {
      $bot_info = $detection_result['info'];
      $metadata = array_merge($metadata, [
        'agent_type' => $bot_info['type'] ?? 'unknown',
        'agent_category' => $bot_info['category'] ?? 'Unknown',
        'agent_subcategory' => $bot_info['subcategory'] ?? 'Unclassified',
        'company' => $bot_info['company'] ?? NULL,
        'is_compliant' => $bot_info['is_compliant'] ?? FALSE,
        'intent' => $bot_info['intent'] ?? 'unknown',
        'confidence' => 0.9,
        'detection_method' => 'pattern_match',
      ]);
    }
    elseif ($detection_result['source_type'] === 'ai_referrer' && $detection_result['info']) {
      $referrer_info = $detection_result['info'];
      $metadata = array_merge($metadata, [
        'source_type' => 'ai_referrer',
        'referrer_id' => $referrer_info['id'],
        'referrer_name' => $referrer_info['name'],
        'company' => $referrer_info['company'],
      ]);
    }
    
    $payload = [
      'url' => $request_info['url'] ?? '',
      'user_agent' => $request_info['user_agent'] ?? '',
      'ip_address' => $request_info['ip_address'] ?? '',
      'request_method' => $request_info['request_method'] ?? 'GET',
      'request_path' => $request_info['request_path'] ?? '/',
      'request_query' => $request_info['request_query'] ?? '',
      'request_body' => '',
      'referrer' => $request_info['referrer'] ?? NULL,
      'response_status' => $request_info['response_status'] ?? ($detection_result['should_block'] ? 403 : 200),
      'response_time_ms' => $request_info['response_time_ms'] ?? 0,
      'headers' => $request_info['headers'] ?? [],
      'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
      'platform_type' => 'drupal',
      'metadata' => $metadata,
    ];
    
    // Send request in background using Drupal's queue system or direct HTTP
    $this->sendCollectorRequest($payload, $detection_result['source_type']);
  }

  /**
   * Sync patterns from the API.
   *
   * @return bool|string
   *   TRUE on success, error message on failure.
   */
  public function syncPatterns() {
    $config = $this->configFactory->get('spyglasses.settings');
    $api_key = $config->get('api_key');
    $debug_mode = $config->get('debug_mode', FALSE);
    
    if (empty($api_key)) {
      $message = 'No API key set for pattern sync';
      if ($debug_mode) {
        $this->loggerFactory->get('spyglasses')->debug($message);
      }
      return $message;
    }
    
    try {
      if ($debug_mode) {
        $this->loggerFactory->get('spyglasses')->debug('Requesting patterns from API: @endpoint', ['@endpoint' => self::PATTERNS_ENDPOINT]);
      }
      
      $response = $this->httpClient->request('GET', self::PATTERNS_ENDPOINT, [
        'headers' => [
          'Content-Type' => 'application/json',
          'x-api-key' => $api_key,
        ],
        'timeout' => 30,
      ]);
      
      if ($response->getStatusCode() !== 200) {
        $message = 'Pattern sync HTTP error ' . $response->getStatusCode() . ': ' . $response->getReasonPhrase();
        if ($debug_mode) {
          $this->loggerFactory->get('spyglasses')->debug($message);
        }
        return $message;
      }
      
      $data = json_decode($response->getBody(), TRUE);
      
      if (!is_array($data) || !isset($data['patterns']) || empty($data['patterns'])) {
        $message = 'Invalid or empty pattern response';
        if ($debug_mode) {
          $this->loggerFactory->get('spyglasses')->debug($message);
        }
        return $message;
      }
      
      // Update patterns and property settings
      $this->patterns = $data['patterns'];
      $this->aiReferrers = $data['aiReferrers'] ?? [];
      
      if (isset($data['propertySettings'])) {
        $this->propertySettings = [
          'block_ai_model_trainers' => !empty($data['propertySettings']['blockAiModelTrainers']),
          'custom_blocks' => $data['propertySettings']['customBlocks'] ?? [],
          'custom_allows' => $data['propertySettings']['customAllows'] ?? [],
        ];
      }
      
      // Cache the patterns
      $cache_data = [
        'patterns' => $this->patterns,
        'ai_referrers' => $this->aiReferrers,
        'property_settings' => $this->propertySettings,
        'version' => $data['version'] ?? '1.0.0',
        'synced_at' => time(),
      ];
      
      $this->cache->set(self::PATTERNS_CACHE_KEY, $cache_data, time() + self::PATTERNS_CACHE_TTL);
      
      if ($debug_mode) {
        $this->loggerFactory->get('spyglasses')->debug('Agent patterns updated successfully - @count patterns loaded', ['@count' => count($this->patterns)]);
        $this->loggerFactory->get('spyglasses')->debug('Property settings: block_ai_model_trainers=@block, custom_blocks=@blocks, custom_allows=@allows', [
          '@block' => $this->propertySettings['block_ai_model_trainers'] ? 'true' : 'false',
          '@blocks' => count($this->propertySettings['custom_blocks']),
          '@allows' => count($this->propertySettings['custom_allows'])
        ]);
      }
      
      return TRUE;
    }
    catch (RequestException $e) {
      $message = 'Exception in syncPatterns(): ' . $e->getMessage();
      if ($debug_mode) {
        $this->loggerFactory->get('spyglasses')->debug($message);
      }
      return $message;
    }
  }

  /**
   * Load default patterns.
   */
  protected function loadDefaultPatterns() {
    // Default bot patterns similar to other implementations
    $this->patterns = [
      // AI Assistants
      [
        'pattern' => 'ChatGPT-User/[0-9]',
        'url' => 'https://platform.openai.com/docs/bots',
        'type' => 'chatgpt-user',
        'category' => 'AI Agent',
        'subcategory' => 'AI Assistants',
        'company' => 'OpenAI',
        'is_compliant' => TRUE,
        'is_ai_model_trainer' => FALSE,
        'intent' => 'UserQuery',
      ],
      [
        'pattern' => 'Claude-User/[0-9]',
        'url' => 'https://support.anthropic.com/en/articles/8896518-does-anthropic-crawl-data-from-the-web-and-how-can-site-owners-block-the-crawler',
        'type' => 'claude-user',
        'category' => 'AI Agent',
        'subcategory' => 'AI Assistants',
        'company' => 'Anthropic',
        'is_compliant' => TRUE,
        'is_ai_model_trainer' => FALSE,
        'intent' => 'UserQuery',
      ],
      // AI Model Training Crawlers
      [
        'pattern' => 'CCBot/[0-9]',
        'url' => 'https://commoncrawl.org/ccbot',
        'type' => 'ccbot',
        'category' => 'AI Crawler',
        'subcategory' => 'Model Training Crawlers',
        'company' => 'Common Crawl',
        'is_compliant' => TRUE,
        'is_ai_model_trainer' => TRUE,
        'intent' => 'DataCollection',
      ],
      [
        'pattern' => 'GPTBot/[0-9]',
        'url' => 'https://platform.openai.com/docs/gptbot',
        'type' => 'gptbot',
        'category' => 'AI Crawler',
        'subcategory' => 'Model Training Crawlers',
        'company' => 'OpenAI',
        'is_compliant' => TRUE,
        'is_ai_model_trainer' => TRUE,
        'intent' => 'DataCollection',
      ],
      [
        'pattern' => 'ClaudeBot/[0-9]',
        'url' => 'https://support.anthropic.com/en/articles/8896518-does-anthropic-crawl-data-from-the-web-and-how-can-site-owners-block-the-crawler',
        'type' => 'claude-bot',
        'category' => 'AI Crawler',
        'subcategory' => 'Model Training Crawlers',
        'company' => 'Anthropic',
        'is_compliant' => TRUE,
        'is_ai_model_trainer' => TRUE,
        'intent' => 'DataCollection',
      ],
    ];
    
    // Default AI referrers
    $this->aiReferrers = [
      [
        'id' => 'chatgpt',
        'name' => 'ChatGPT',
        'company' => 'OpenAI',
        'url' => 'https://chat.openai.com',
        'patterns' => ['chat.openai.com', 'chatgpt.com'],
        'description' => 'Traffic from ChatGPT users clicking on links',
      ],
      [
        'id' => 'claude',
        'name' => 'Claude',
        'company' => 'Anthropic',
        'url' => 'https://claude.ai',
        'patterns' => ['claude.ai'],
        'description' => 'Traffic from Claude users clicking on links',
      ],
      [
        'id' => 'perplexity',
        'name' => 'Perplexity',
        'company' => 'Perplexity AI',
        'url' => 'https://perplexity.ai',
        'patterns' => ['perplexity.ai'],
        'description' => 'Traffic from Perplexity users clicking on links',
      ],
    ];
  }

  /**
   * Load cached patterns if available.
   */
  protected function loadCachedPatterns() {
    $cached = $this->cache->get(self::PATTERNS_CACHE_KEY);
    if ($cached && $cached->data) {
      $data = $cached->data;
      if (isset($data['patterns']) && !empty($data['patterns'])) {
        $this->patterns = $data['patterns'];
        $this->aiReferrers = $data['ai_referrers'] ?? [];
        $this->propertySettings = $data['property_settings'] ?? $this->propertySettings;
      }
    }
  }

  /**
   * Check if a pattern should be blocked based on settings.
   *
   * @param array $pattern_data
   *   The pattern data.
   *
   * @return bool
   *   TRUE if the pattern should be blocked.
   */
  protected function shouldBlockPattern(array $pattern_data) {
    $pattern = $pattern_data['pattern'] ?? '';
    $category = $pattern_data['category'] ?? 'Unknown';
    $subcategory = $pattern_data['subcategory'] ?? 'Unclassified';
    $type = $pattern_data['type'] ?? 'unknown';
    
    // Check if pattern is explicitly allowed
    if (in_array('pattern:' . $pattern, $this->propertySettings['custom_allows'])) {
      return FALSE;
    }
    
    // Check if any parent is explicitly allowed
    if (in_array('category:' . $category, $this->propertySettings['custom_allows']) ||
        in_array('subcategory:' . $category . ':' . $subcategory, $this->propertySettings['custom_allows']) ||
        in_array('type:' . $category . ':' . $subcategory . ':' . $type, $this->propertySettings['custom_allows'])) {
      return FALSE;
    }
    
    // Check if pattern is explicitly blocked
    if (in_array('pattern:' . $pattern, $this->propertySettings['custom_blocks'])) {
      return TRUE;
    }
    
    // Check if any parent is explicitly blocked
    if (in_array('category:' . $category, $this->propertySettings['custom_blocks']) ||
        in_array('subcategory:' . $category . ':' . $subcategory, $this->propertySettings['custom_blocks']) ||
        in_array('type:' . $category . ':' . $subcategory . ':' . $type, $this->propertySettings['custom_blocks'])) {
      return TRUE;
    }
    
    // Check for AI model trainers global setting
    if ($this->propertySettings['block_ai_model_trainers'] && !empty($pattern_data['is_ai_model_trainer'])) {
      return TRUE;
    }
    
    // Default to not blocking
    return FALSE;
  }

  /**
   * Extract hostname from referrer URL.
   *
   * @param string $referrer
   *   The referrer URL.
   *
   * @return string
   *   The hostname.
   */
  protected function extractHostname($referrer) {
    try {
      $parsed = parse_url($referrer);
      return strtolower($parsed['host'] ?? $referrer);
    }
    catch (\Exception $e) {
      return strtolower($referrer);
    }
  }

  /**
   * Send request to collector API.
   *
   * @param array $payload
   *   The payload to send.
   * @param string $source_type
   *   The source type for logging.
   */
  protected function sendCollectorRequest(array $payload, $source_type) {
    $config = $this->configFactory->get('spyglasses.settings');
    $api_key = $config->get('api_key');
    $debug_mode = $config->get('debug_mode', FALSE);
    
    try {
      if ($debug_mode) {
        $this->loggerFactory->get('spyglasses')->debug('Making POST request to @endpoint', ['@endpoint' => self::COLLECTOR_ENDPOINT]);
        $payload_json = json_encode($payload);
        $this->loggerFactory->get('spyglasses')->debug('Payload size: @size bytes', ['@size' => strlen($payload_json)]);
      }
      
      $response = $this->httpClient->request('POST', self::COLLECTOR_ENDPOINT, [
        'headers' => [
          'Content-Type' => 'application/json',
          'x-api-key' => $api_key,
        ],
        'json' => $payload,
        'timeout' => 10,
      ]);
      
      if ($debug_mode) {
        $this->loggerFactory->get('spyglasses')->debug('Collector response status: @status @reason', [
          '@status' => $response->getStatusCode(),
          '@reason' => $response->getReasonPhrase()
        ]);
        
        if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
          $this->loggerFactory->get('spyglasses')->debug('✅ Successfully logged @type event', ['@type' => $source_type]);
        }
        else {
          $this->loggerFactory->get('spyglasses')->debug('❌ Failed to log @type event', ['@type' => $source_type]);
        }
      }
    }
    catch (RequestException $e) {
      if ($debug_mode) {
        $this->loggerFactory->get('spyglasses')->debug('❌ Exception during collector request for @type: @error', [
          '@type' => $source_type,
          '@error' => $e->getMessage()
        ]);
      }
    }
  }

}
