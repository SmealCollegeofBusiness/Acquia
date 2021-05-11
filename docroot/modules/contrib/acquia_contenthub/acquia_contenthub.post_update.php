<?php

/**
 * @file
 * Post Update functions for Acquia Content Hub module.
 */

/**
 * @addtogroup updates-8.6.x-contenthub-configuration
 * @{
 */

/**
 * Migrate Content Hub configuration.
 *
 * @see acquia_contenthub_update_82001()
 */
function acquia_contenthub_post_update_update_config_entities() {
  // Exit early if we are not doing a migration.
  $database = \Drupal::database();
  if (!$database->schema()->tableExists('acquia_contenthub_entities_tracking')) {
    return;
  }
  // Make sure existing webhook for the site in 1.x is unregistered.
  $state = \Drupal::state();
  $webhook_uuid = $state->get('acquia_contenthub_update_82001_webhook_uuid', NULL);
  if (!empty($webhook_uuid)) {
    /** @var \Drupal\acquia_contenthub\Client\ClientFactory $client_factory */
    $client_factory = \Drupal::service("acquia_contenthub.client.factory");
    $settings = $client_factory->getSettings();
    $client = $client_factory->getClient($settings);
    // Unregister current webhook.
    $client->deleteWebhook($webhook_uuid);
  }
  // Deleting state variables if webhook is successfully registered.
  $state->delete('acquia_contenthub_update_82001_webhook_uuid');
}

/**
 * @} End of "addtogroup updates-8.6.x-contenthub-configuration".
 */
