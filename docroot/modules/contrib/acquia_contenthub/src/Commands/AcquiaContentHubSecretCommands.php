<?php

namespace Drupal\acquia_contenthub\Commands;

use Drupal\acquia_contenthub\Client\ClientFactory;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drush\Commands\DrushCommands;

/**
 * Drush commands for the Content Hub Service secret key.
 *
 * @package Drupal\acquia_contenthub\Commands
 */
class AcquiaContentHubSecretCommands extends DrushCommands {

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The client factory.
   *
   * @var \Drupal\acquia_contenthub\Client\ClientFactory
   */
  protected $clientFactory;

  /**
   * AcquiaContentHubSecretCommands constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   * @param \Drupal\acquia_contenthub\Client\ClientFactory $client_factory
   *   The client factory.
   */
  public function __construct(ConfigFactoryInterface $config_factory, ClientFactory $client_factory) {
    $this->configFactory = $config_factory;
    $this->clientFactory = $client_factory;
  }

  /**
   * Regenerates the Shared Secret used for Webhook Verification.
   *
   * @command acquia:contenthub-regenerate-secret
   * @aliases ach-regsec,acquia-contenthub-regenerate-secret
   *
   * @throws \Exception
   */
  public function contenthubRegenerateSecret() {
    $client = $this->clientFactory->getClient();
    $warning_message = "Are you sure you want to REGENERATE your shared-secret in the Content Hub?\n" .
      "*************************************************************************************\n" .
      "PROCEED WITH CAUTION. THIS COULD POTENTIALLY LEAD TO HAVING SOME SITES OUT OF SYNC.\n" .
      "Make sure you have ALL your sites correctly configured to receive webhooks before attempting to do this.\n" .
      "For more information, check https://docs.acquia.com/content-hub/known-issues.\n" .
      "*************************************************************************************\n";
    if ($this->io()->confirm($warning_message)) {
      if (!$client) {
        throw new \Exception(dt('Error trying to connect to the Content Hub. Make sure this site is registered to Content hub.'));
      }
      $output = $client->regenerateSharedSecret();

      if ($output) {
        $this->output()->writeln("Your Shared Secret has been regenerated. All clients who have registered to received webhooks are being notified of this change.\n");
      }
      else {
        throw new \Exception(dt("Error trying to regenerate the shared-secret in your subscription. Try again later."));
      }
    }
  }

  /**
   * Updates the Shared Secret used for Webhook Verification.
   *
   * @command acquia:contenthub-update-secret
   * @aliases ach-upsec,acquia-contenthub-update-secret
   */
  public function contenthubUpdateSecret() {
    $client = $this->clientFactory->getClient();

    if (!$client) {
      throw new \Exception(dt('The Content Hub client is not connected so the shared secret can not be updated.'));
    }

    $remote = $client->getRemoteSettings();
    $provider = $this->clientFactory->getProvider();
    if (!empty($remote['shared_secret']) && $provider === 'core_config') {
      $config = $this->configFactory->getEditable('acquia_contenthub.admin_settings');
      $config->set('shared_secret', $remote['shared_secret']);
      $config->save();
      $this->output()->writeln(dt('The shared secret has been updated to: @secret', ['@secret' => $remote['shared_secret']]));
      return;
    }
    $this->output()->writeln(dt('The settings object is read only. Your remote shared secret is: @secret Please update your settings object if necessary.', ['@secret' => $remote['shared_secret']]));
  }

}
