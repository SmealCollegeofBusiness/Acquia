<?php

namespace Drupal\acquia_contenthub\Commands;

use Acquia\ContentHubClient\ContentHubClient;
use Drupal\acquia_contenthub\Client\ClientFactory;
use Drupal\acquia_contenthub\Form\ContentHubSettingsForm;
use Drupal\Core\Form\FormState;
use Drush\Commands\DrushCommands;
use Drush\Log\LogLevel;

/**
 * Drush commands for interacting with Acquia Content Hub client site.
 *
 * @package Drupal\acquia_contenthub\Commands
 */
class AcquiaContentHubSiteCommands extends DrushCommands {

  /**
   * The client factory.
   *
   * @var \Drupal\acquia_contenthub\Client\ClientFactory
   */
  protected $clientFactory;

  /**
   * AcquiaContentHubSiteCommands constructor.
   *
   * @param \Drupal\acquia_contenthub\Client\ClientFactory $client_factory
   *   The client factory.
   */
  public function __construct(ClientFactory $client_factory) {
    $this->clientFactory = $client_factory;
  }

  /**
   * Connects a site with contenthub.
   *
   * @command acquia:contenthub-connect-site
   * @aliases ach-connect,acquia-contenthub-connect-site
   *
   * @option $hostname
   *   Content Hub API URL.
   * @default $hostname null
   *
   * @option $api_key
   *   Content Hub API Key.
   * @default $api_key null
   *
   * @option $secret_key
   *   Content Hub API Secret.
   * @default $secret_key null
   *
   * @option $client_name
   *   The client name for this site.
   * @default $client_name null
   *
   * @usage ach-connect
   *   hostname, api_key, secret_key, client_name will be requested.
   * @usage ach-connect --hostname=https://us-east-1.content-hub.acquia.com
   *   api_key, secret_key, client_name will be requested.
   * @usage ach-connect --hostname=https://us-east-1.content-hub.acquia.com --api_key=API_KEY
   *   --secret_key=SECRET_KEY --client_name=CLIENT_NAME Connects site with
   *   following credentials.
   */
  public function contenthubConnectSite() {
    $options = $this->input()->getOptions();

    // @todo Revisit initial connection logic with our event subscibers.
    $settings = $this->clientFactory->getSettings();
    $config_origin = $settings->getUuid();

    $provider = $this->clientFactory->getProvider();
    $disabled = $provider != 'core_config';
    if ($disabled) {
      $message = dt('Settings are being provided by @provider, and already connected.', ['@provider' => $provider]);
      $this->logger()->log(LogLevel::CANCEL, $message);
      return;
    }

    if (!empty($config_origin)) {
      $message = dt('Site is already connected to Content Hub. Skipping.');
      $this->logger()->log(LogLevel::CANCEL, $message);
      return;
    }

    $io = $this->io();
    $hostname = $options['hostname'] ?? $io->ask(
        dt('What is the Content Hub API URL?'),
        'https://us-east-1.content-hub.acquia.com'
      );
    $api_key = $options['api_key'] ?? $io->ask(
        dt('What is your Content Hub API Key?')
      );
    $secret_key = $options['secret_key'] ?? $io->ask(
        dt('What is your Content Hub API Secret?')
      );
    $client_uuid = \Drupal::service('uuid')->generate();
    $client_name = $options['client_name'] ?? $io->ask(
        dt('What is the client name for this site?'),
        $client_uuid
      );

    $form_state = (new FormState())->setValues([
      'hostname' => $hostname,
      'api_key' => $api_key,
      'secret_key' => $secret_key,
      'client_name' => sprintf("%s_%s", $client_name, $client_uuid),
      'op' => t('Save configuration'),
    ]);

    // @todo Errors handling can be improved after relocation of registration
    // logic into separate service.
    $form = \Drupal::formBuilder()->buildForm(ContentHubSettingsForm::class, new FormState());
    $form_state->setTriggeringElement($form['actions']['submit']);
    \Drupal::formBuilder()->submitForm(ContentHubSettingsForm::class, $form_state);
  }

  /**
   * Disconnects a site with contenthub.
   *
   * @command acquia:contenthub-disconnect-site
   * @aliases ach-disconnect,acquia-contenthub-disconnect-site
   */
  public function contenthubDisconnectSite() {
    $client = $this->clientFactory->getClient();

    if (!$client instanceof ContentHubClient) {
      $message = "Couldn't instantiate client. Please check connection settings.";
      $this->logger->log(LogLevel::CANCEL, $message);
      return;
    }

    $provider = $this->clientFactory->getProvider();
    $disabled = $provider != 'core_config';
    if ($disabled) {
      $message = dt(
        'Settings are being provided by %provider and cannot be disconnected manually.',
        ['%provider' => $provider]
      );
      $this->logger->log(LogLevel::CANCEL, $message);
      return;
    }

    try {
      $client->deleteClient();
    }
    catch (\Exception $exception) {
      $this->logger->log(LogLevel::ERROR, $exception->getMessage());
    }

    $config_factory = \Drupal::configFactory();
    $config = $config_factory->getEditable('acquia_contenthub.admin_settings');
    $client_name = $config->get('client_name');
    $config->delete();

    // @todo We should disconnect the webhook, but first we need to know its
    // ours.
    $message = dt(
      'Successfully disconnected site %site from contenthub',
      ['%site' => $client_name]
    );
    $this->logger->log(LogLevel::SUCCESS, $message);
  }

}
