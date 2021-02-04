<?php

namespace Drupal\acquia_contenthub\Commands;

use Acquia\ContentHubClient\ContentHubClient;
use Acquia\ContentHubClient\Settings;
use Drupal\acquia_contenthub\Client\ClientFactory;
use Drupal\Core\Extension\ModuleExtensionList;
use Drush\Commands\DrushCommands;

/**
 * Drush commands for purging Acquia Content Hub.
 *
 * @package Drupal\acquia_contenthub\Commands
 */
class AcquiaContentHubPurgeCommands extends DrushCommands {

  /**
   * The client factory.
   *
   * @var \Drupal\acquia_contenthub\Client\ClientFactory
   */
  protected $clientFactory;

  /**
   * The module extension list.
   *
   * @var \Drupal\Core\Extension\ModuleExtensionList
   */
  protected $moduleList;

  /**
   * AcquiaContentHubPurgeCommands constructor.
   *
   * @param \Drupal\acquia_contenthub\Client\ClientFactory $client_factory
   *   The client factory.
   * @param \Drupal\Core\Extension\ModuleExtensionList $module_list
   *   The module extension list.
   */
  public function __construct(ClientFactory $client_factory, ModuleExtensionList $module_list) {
    $this->clientFactory = $client_factory;
    $this->moduleList = $module_list;
  }

  /**
   * Purges all entities from Acquia Content Hub.
   *
   * WARNING! Be VERY careful when using this command.
   * This destructive command requires elevated keys. Every
   * subsequent execution of this command will override the backup created
   * by the previous call.
   *
   * @param string $api
   *   The API key.
   * @param string $secret
   *   The secret key.
   *
   * @command acquia:contenthub-purge
   *
   * @aliases ach-purge,acquia-contenthub-purge
   *
   * @throws \Exception
   */
  public function contenthubPurge($api = NULL, $secret = NULL) {

    $client = $this->clientFactory->getClient();

    // Use the keys associated with Drupal config explicitly entered.
    if (!empty($api) && !empty($secret)) {
      $client = $this->resetConnection($client, $api, $secret);
    }

    // Without a client, we cannot purge.
    if (!$client) {
      throw new \Exception(dt('Error trying to connect to the Content Hub. Make sure this site is registered to Content hub.'));
    }

    // Get the remote settings for the UUID (name) of the client.
    $settings = $client->getRemoteSettings();

    // Warning prompt initially.
    $warning_message = "Are you sure you want to PURGE your Content Hub Subscription?\n" .
      "*************************************************************************************\n" .
      "PROCEED WITH CAUTION. THIS ACTION WILL PURGE ALL EXISTING ENTITIES IN YOUR CONTENT HUB SUBSCRIPTION.\n" .
      "While a backup is created for use by the restore command, restoration may not be timely and is not guaranteed. Concurrent or frequent\n" .
      "use of this command may result in an inability to restore. You can always republish your content as a means of 'recovery'.
    For more information, check https://docs.acquia.com/content-hub.\n" .
      "*************************************************************************************\n" .
      "Are you sure you want to proceed?\n";

    // If user aborts, stop the purge.
    if (!$this->io()->confirm($warning_message)) {
      return;
    }

    // Make sure this is the correct account before purging.
    $double_check_message = dt("Are you ABSOLUTELY sure? Purging the subscription !sub will remove all entities from Content Hub. Backups are created but not guaranteed. Please confirm one last time that you would like to continue.",
      [
        '!sub' => $settings['uuid'],
      ]);

    // If user aborts, stop the purge.
    if (!$this->io()->confirm($double_check_message)) {
      return;
    }

    // Execute the 'purge' command.
    $response = $client->purge();

    // Success but not really.
    if (!(isset($response['success'])) || $response['success'] !== TRUE) {
      $message = dt("Error trying to purge your subscription. You might require elevated keys to perform this operation.");

      throw new \Exception($message);
    }

    // Error occurred.
    if (!empty($response['error']['code']) && !empty($response['error']['message'])) {
      $message = dt('Error trying to purge your subscription. Status code !code. !message',
        [
          '!code' => $response['error']['code'],
          '!message' => $response['error']['message'],
        ]);

      throw new \Exception($message);
    }

    $confirmation_message = dt("Your !sub subscription is being purged. All clients who have registered to received webhooks will be notified with purge and reindex webhooks when the purge process has been completed.\n",
      [
        '!sub' => $settings['uuid'] ?? '',
      ]);

    $this->output()->writeln($confirmation_message);
  }

  /**
   * Restores the backup taken by a previous execution of the "purge" command.
   *
   * WARNING! Be VERY careful when using this command. This destructive command
   * requires elevated keys. By restoring a backup you will delete all the
   * existing entities in your subscription.
   *
   * @param string $api
   *   The API key.
   * @param string $secret
   *   The (optional) string secret key.
   *
   * @command acquia:contenthub-restore
   * @aliases ach-restore,acquia-contenthub-restore
   *
   * @throws \Exception
   */
  public function contenthubRestore($api, $secret) {
    $warning_message = "Are you sure you want to RESTORE the latest backup taken after purging your Content Hub Subscription?\n" .
      "*************************************************************************************\n" .
      "PROCEED WITH CAUTION. THIS ACTION WILL ELIMINATE ALL EXISTING ENTITIES IN YOUR CONTENT HUB SUBSCRIPTION.\n" .
      "This restore command should only be used after an accidental purge event has taken place *and* completed. This will attempt to restore\n" .
      "from the last purge-generated backup. In the event this fails, you will need to republish your content to Content Hub.
    For more information, check https://docs.acquia.com/content-hub.\n" .
      "*************************************************************************************\n" .
      "Are you sure you want to proceed?\n";
    if ($this->io()->confirm($warning_message)) {
      if (!empty($api) && !empty($secret)) {
        $client = $this->resetConnection($this->clientFactory->getClient(), $api, $secret);
      }
      else {
        $client = $this->clientFactory->getClient();
      }

      // Execute the 'restore' command.
      if (!$client) {
        throw new \Exception(dt('Error trying to connect to the Content Hub. Make sure this site is registered to Content hub.'));
      }
      $response = $client->restore();

      if (isset($response['success']) && $response['success'] === TRUE) {
        $this->output()->writeln("Your Subscription is being restored. All clients who have registered to received webhooks will be notified with a reindex webhook when the restore process has been completed.\n");
      }
      else {
        throw new \Exception(dt("Error trying to restore your subscription from a backup copy. You might require elevated keys to perform this operation."));
      }
    }
  }

  /**
   * Resets a connection to the client to use a new api and secret key.
   *
   * @param \Acquia\ContentHubClient\ContentHubClient $client
   *   Client.
   * @param string $api_key
   *   API key.
   * @param string $secret_key
   *   Secret key.
   *
   * @return \Acquia\ContentHubClient\ContentHubClient
   *   New client instance.
   */
  protected function resetConnection(ContentHubClient $client, $api_key, $secret_key) {
    $settings = $client->getSettings();
    $new_settings = new Settings($settings->getName(), $settings->getUuid(), $api_key, $secret_key, $settings->getUrl());
    // Find out the module version in use.
    $module_info = $this->moduleList->getExtensionInfo('acquia_contenthub');
    $module_version = (isset($module_info['version'])) ? $module_info['version'] : '0.0.0';
    $drupal_version = (isset($module_info['core'])) ? $module_info['core'] : '0.0.0';
    $client_user_agent = 'AcquiaContentHub/' . $drupal_version . '-' . $module_version;

    // Override configuration.
    $config = [
      'base_url' => $settings->getUrl(),
      'client-user-agent' => $client_user_agent,
    ];

    $dispatcher = \Drupal::service('event_dispatcher');
    return new ContentHubClient($config, $this->logger(), $new_settings, $new_settings->getMiddleware(), $dispatcher);
  }

}
