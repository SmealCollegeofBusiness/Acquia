<?php

/**
 * @file
 * ACSF post-settings-php hook.
 *
 * @see https://docs.acquia.com/site-factory/extend/hooks/settings-php/
 *
 * phpcs:disable DrupalPractice.CodeAnalysis.VariableAnalysis
 */

// Set config directories to default location.
$config_directories['vcs'] = '../config/default';
$config_directories['sync'] = '../config/default';
$settings['config_sync_directory'] = '../config/default';

// Temporary workaround to override the default MySQL wait_timeout setting.
$databases['default']['default']['init_commands'] = array(
  'wait_timeout' => "SET SESSION wait_timeout=1200",
);
if (function_exists('acquia_hosting_db_choose_active')) {
acquia_hosting_db_choose_active();
}
