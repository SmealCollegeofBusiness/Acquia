<?php

/**
 * @file
 * Factory hook implementation for ACMS and memcache.
 *
 * @see https://docs.acquia.com/site-factory/tiers/paas/workflow/hooks
 *
 * phpcs:disable DrupalPractice.CodeAnalysis.VariableAnalysis
 */

// Use ACSF internal settings site flag to apply memcache settings.
if (
  getenv('AH_SITE_ENVIRONMENT') &&
  isset($site_settings['flags']['memcache_enabled']) &&
  isset($settings['memcache']['servers'])
) {
  require DRUPAL_ROOT . '/../vendor/acquia/memcache-settings/memcache.settings.php';
}
