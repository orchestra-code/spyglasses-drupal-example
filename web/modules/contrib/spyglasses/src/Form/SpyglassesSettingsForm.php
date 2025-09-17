<?php

namespace Drupal\spyglasses\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\spyglasses\SpyglassesClient;
use GuzzleHttp\ClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure Spyglasses settings for this site.
 */
class SpyglassesSettingsForm extends ConfigFormBase {

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
   * Constructs a SpyglassesSettingsForm object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The cache backend.
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The HTTP client.
   */
  public function __construct(ConfigFactoryInterface $config_factory, LoggerChannelFactoryInterface $logger_factory, CacheBackendInterface $cache, ClientInterface $http_client) {
    parent::__construct($config_factory);
    $this->loggerFactory = $logger_factory;
    $this->cache = $cache;
    $this->httpClient = $http_client;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('logger.factory'),
      $container->get('cache.default'),
      $container->get('http_client')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'spyglasses_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['spyglasses.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('spyglasses.settings');

    $form['description'] = [
      '#markup' => '<p>' . $this->t('Spyglasses provides AI traffic analytics by detecting and logging bot traffic from AI assistants and crawlers. Configure your API key and settings below.') . '</p>',
    ];

    $form['api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API Key'),
      '#default_value' => $config->get('api_key'),
      '#description' => $this->t('Your Spyglasses API key. Get one at <a href="@url" target="_blank">spyglasses.io</a>.', ['@url' => 'https://www.spyglasses.io']),
      '#required' => TRUE,
    ];

    $form['debug_mode'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable debug mode'),
      '#default_value' => $config->get('debug_mode', FALSE),
      '#description' => $this->t('Log detailed debug information to the Drupal log. Only enable this for troubleshooting.'),
    ];

    $form['auto_sync'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Auto-sync patterns'),
      '#default_value' => $config->get('auto_sync', TRUE),
      '#description' => $this->t('Automatically sync bot patterns from the Spyglasses API daily. Disable this if you prefer manual syncing.'),
    ];

    // Pattern sync section
    $form['patterns'] = [
      '#type' => 'details',
      '#title' => $this->t('Pattern Management'),
      '#open' => FALSE,
    ];

    $last_sync = $config->get('last_pattern_sync');
    $sync_status = $last_sync ? 
      $this->t('Last synced: @time', ['@time' => \Drupal::service('date.formatter')->format($last_sync, 'medium')]) : 
      $this->t('Never synced');

    $form['patterns']['sync_status'] = [
      '#markup' => '<p><strong>' . $this->t('Pattern sync status:') . '</strong> ' . $sync_status . '</p>',
    ];

    $form['patterns']['sync_patterns'] = [
      '#type' => 'submit',
      '#value' => $this->t('Sync Patterns Now'),
      '#submit' => ['::syncPatterns'],
      '#limit_validation_errors' => [['api_key']],
    ];

    // Advanced settings
    $form['advanced'] = [
      '#type' => 'details',
      '#title' => $this->t('Advanced Settings'),
      '#open' => FALSE,
    ];

    $form['advanced']['collector_endpoint'] = [
      '#type' => 'url',
      '#title' => $this->t('Collector Endpoint'),
      '#default_value' => $config->get('collector_endpoint', 'https://www.spyglasses.io/api/collect'),
      '#description' => $this->t('The endpoint for logging detected traffic. Only change this if directed by Spyglasses support.'),
    ];

    $form['advanced']['patterns_endpoint'] = [
      '#type' => 'url',
      '#title' => $this->t('Patterns Endpoint'),
      '#default_value' => $config->get('patterns_endpoint', 'https://www.spyglasses.io/api/patterns'),
      '#description' => $this->t('The endpoint for fetching bot patterns. Only change this if directed by Spyglasses support.'),
    ];

    $form['advanced']['cache_ttl'] = [
      '#type' => 'number',
      '#title' => $this->t('Cache TTL (seconds)'),
      '#default_value' => $config->get('cache_ttl', 86400),
      '#min' => 300,
      '#max' => 604800,
      '#description' => $this->t('How long to cache bot patterns (300 seconds to 7 days). Default is 24 hours.'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    $api_key = $form_state->getValue('api_key');
    if ($api_key && !preg_match('/^[a-zA-Z0-9_-]+$/', $api_key)) {
      $form_state->setErrorByName('api_key', $this->t('API key contains invalid characters.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('spyglasses.settings')
      ->set('api_key', $form_state->getValue('api_key'))
      ->set('debug_mode', $form_state->getValue('debug_mode'))
      ->set('auto_sync', $form_state->getValue('auto_sync'))
      ->set('collector_endpoint', $form_state->getValue('collector_endpoint'))
      ->set('patterns_endpoint', $form_state->getValue('patterns_endpoint'))
      ->set('cache_ttl', $form_state->getValue('cache_ttl'))
      ->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * Submit handler for syncing patterns.
   */
  public function syncPatterns(array &$form, FormStateInterface $form_state) {
    // First save the API key if it changed
    $config = $this->config('spyglasses.settings');
    $current_api_key = $config->get('api_key');
    $new_api_key = $form_state->getValue('api_key');
    
    if ($current_api_key !== $new_api_key) {
      $config->set('api_key', $new_api_key)->save();
    }

    // Create client and sync patterns
    $client = new SpyglassesClient($this->configFactory, $this->loggerFactory, $this->cache, $this->httpClient);
    $result = $client->syncPatterns();

    if ($result === TRUE) {
      $this->config('spyglasses.settings')
        ->set('last_pattern_sync', time())
        ->save();
      
      $this->messenger()->addMessage($this->t('Bot patterns synced successfully.'));
    }
    else {
      $this->messenger()->addError($this->t('Failed to sync patterns: @error', ['@error' => $result]));
    }
  }

}
