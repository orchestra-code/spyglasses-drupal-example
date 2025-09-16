<?php

/**
 * @file
 * Drupal site-specific configuration file.
 */

// Include the main Drupal settings template
$settings = [];
$databases = [];
$config_directories = [];

/**
 * Salt for one-time login links, cancel links, form tokens, etc.
 */
$settings['hash_salt'] = getenv('DRUPAL_HASH_SALT') ?: 'dev-salt-please-change-in-production';

/**
 * Trusted host configuration.
 *
 * See https://www.drupal.org/docs/installing-drupal/trusted-host-settings
 */
$settings['trusted_host_patterns'] = [
  '^spyglasses-drupal-example\.fly\.dev$',
  '^drupal\.greenvoyagecanoes\.com$',
  '^localhost$',
  '^127\.0\.0\.1$',
];

/**
 * Load services definition file.
 */
$settings['container_yamls'][] = $app_root . '/' . $site_path . '/services.yml';

/**
 * The default list of directories that will be ignored by Drupal's file API.
 */
$settings['file_scan_ignore_directories'] = [
  'node_modules',
  'bower_components',
];

/**
 * The default number of entities to update in a batch process.
 */
$settings['entity_update_batch_size'] = 50;

/**
 * Entity update backup.
 */
$settings['entity_update_backup'] = TRUE;

/**
 * Load local development override configuration, if available.
 */
if (file_exists($app_root . '/' . $site_path . '/settings.local.php')) {
  include $app_root . '/' . $site_path . '/settings.local.php';
}
