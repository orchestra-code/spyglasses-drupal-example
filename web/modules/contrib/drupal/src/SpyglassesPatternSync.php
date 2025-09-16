<?php

namespace Drupal\spyglasses;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * Service for syncing Spyglasses patterns on a schedule.
 */
class SpyglassesPatternSync {

  /**
   * The Spyglasses client.
   *
   * @var \Drupal\spyglasses\SpyglassesClient
   */
  protected $client;

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
   * Constructs a SpyglassesPatternSync object.
   *
   * @param \Drupal\spyglasses\SpyglassesClient $client
   *   The Spyglasses client.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   */
  public function __construct(SpyglassesClient $client, ConfigFactoryInterface $config_factory, LoggerChannelFactoryInterface $logger_factory) {
    $this->client = $client;
    $this->configFactory = $config_factory;
    $this->loggerFactory = $logger_factory;
  }

  /**
   * Sync patterns if auto-sync is enabled and it's time to sync.
   */
  public function syncIfNeeded() {
    $config = $this->configFactory->get('spyglasses.settings');
    
    if (!$config->get('auto_sync', TRUE)) {
      return;
    }
    
    $api_key = $config->get('api_key');
    if (empty($api_key)) {
      return;
    }
    
    $last_sync = $config->get('last_pattern_sync', 0);
    $cache_ttl = $config->get('cache_ttl', 86400);
    
    // Check if it's time to sync (based on cache TTL)
    if (time() - $last_sync < $cache_ttl) {
      return;
    }
    
    $this->loggerFactory->get('spyglasses')->info('Starting scheduled pattern sync');
    
    $result = $this->client->syncPatterns();
    
    if ($result === TRUE) {
      $config = $this->configFactory->getEditable('spyglasses.settings');
      $config->set('last_pattern_sync', time())->save();
      
      $this->loggerFactory->get('spyglasses')->info('Scheduled pattern sync completed successfully');
    }
    else {
      $this->loggerFactory->get('spyglasses')->error('Scheduled pattern sync failed: @error', ['@error' => $result]);
    }
  }

}
