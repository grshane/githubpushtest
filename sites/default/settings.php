<?php

/**
 * Load services definition file.
 */
$settings['container_yamls'][] = __DIR__ . '/services.yml';

/**
 * Include the Pantheon-specific settings file.
 *
 * n.b. The settings.pantheon.php file makes some changes
 *      that affect all envrionments that this site
 *      exists in.  Always include this file, even in
 *      a local development environment, to insure that
 *      the site settings remain consistent.
 */
include __DIR__ . "/settings.pantheon.php";

/**
 * If there is a local settings file, then include it
 */
$local_settings = __DIR__ . "/settings.local.php";
if (file_exists($local_settings)) {
  include $local_settings;
}
$settings['install_profile'] = 'standard';

// All Pantheon Environments.
if (defined('PANTHEON_ENVIRONMENT')) {
  // Drupal caching in development environments.
  if (!in_array(PANTHEON_ENVIRONMENT, array('test', 'live'))) {
    // Expiration of cached pages - none.
    $config['system.performance']['cache']['page']['max_age'] = 0;
    // Aggregate and compress CSS files in Drupal - off.
    $config['system.performance']['css']['preprocess'] = false;
    // Aggregate JavaScript files in Drupal - off.
    $config['system.performance']['js']['preprocess'] = false;
  }
  // Drupal caching in test and live environments.
  else {
    // Expiration of cached pages - 15 minutes.
    $config['system.performance']['cache']['page']['max_age'] = 900;
    // Aggregate and compress CSS files in Drupal - on.
    $config['system.performance']['css']['preprocess'] = true;
    // Aggregate JavaScript files in Drupal - on.
    $config['system.performance']['js']['preprocess'] = true;
    // Google Analytics.
    $config['google_analytics.settings']['account'] = 'UA-80484325-1';
  }
}

# Set the $base_url parameter if we are running on Pantheon:

if (defined('PANTHEON_ENVIRONMENT')) {
  if (PANTHEON_ENVIRONMENT == 'live') {
    $domain = 'www.fillyourbike.shop';
  }
  else {
    # Fallback value for multidev or other environments.
    # This covers environment-sitename.pantheonsite.io domains
    # that are generated per environment.
    $domain = $_SERVER['HTTP_HOST'];
  }

  # This global variable determines the base for all URLs in Drupal.
  $base_url = 'https://'. $domain;
}