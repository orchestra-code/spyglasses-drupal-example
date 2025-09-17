<?php

namespace Drupal\your_module\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\spyglasses\SpyglassesClient;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Example form that integrates Spyglasses bot detection.
 */
class CustomFormHandler extends FormBase {

  /**
   * The Spyglasses client.
   *
   * @var \Drupal\spyglasses\SpyglassesClient
   */
  protected $spyglassesClient;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Constructs a CustomFormHandler object.
   *
   * @param \Drupal\spyglasses\SpyglassesClient $spyglasses_client
   *   The Spyglasses client.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   */
  public function __construct(SpyglassesClient $spyglasses_client, RequestStack $request_stack) {
    $this->spyglassesClient = $spyglasses_client;
    $this->requestStack = $request_stack;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('spyglasses.client'),
      $container->get('request_stack')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'custom_form_with_bot_detection';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['message'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Message'),
      '#required' => TRUE,
      '#description' => $this->t('Enter your message here.'),
    ];

    $form['email'] = [
      '#type' => 'email',
      '#title' => $this->t('Email'),
      '#required' => TRUE,
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    // Perform bot detection during form validation
    $request = $this->requestStack->getCurrentRequest();
    $user_agent = $request->headers->get('User-Agent', '');
    $referrer = $request->headers->get('Referer', '');

    $detection_result = $this->spyglassesClient->detect($user_agent, $referrer);

    if ($detection_result['source_type'] !== 'none') {
      // Log the detection
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

      $this->spyglassesClient->logRequest($detection_result, $request_info);

      // Handle blocking if configured
      if ($detection_result['should_block']) {
        $form_state->setErrorByName('', $this->t('Access denied.'));
        
        // Log the blocked attempt
        \Drupal::logger('your_module')->warning('Blocked form submission from @type: @pattern', [
          '@type' => $detection_result['source_type'],
          '@pattern' => $detection_result['matched_pattern'],
        ]);
        return;
      }

      // Log allowed bot traffic
      \Drupal::logger('your_module')->info('Form submission from @type: @pattern (allowed)', [
        '@type' => $detection_result['source_type'],
        '@pattern' => $detection_result['matched_pattern'],
      ]);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $message = $form_state->getValue('message');
    $email = $form_state->getValue('email');

    // Process the form submission
    // ... your custom logic here ...

    $this->messenger()->addMessage($this->t('Thank you for your submission.'));
  }

}
