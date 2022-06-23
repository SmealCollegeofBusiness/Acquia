<?php

namespace Drupal\acquia_contenthub\Commands;

use Acquia\ContentHubClient\ContentHubClient;
use Drupal\acquia_contenthub\AcquiaContentHubEvents;
use Drupal\acquia_contenthub\Client\ClientFactory;
use Drupal\acquia_contenthub\ContentHubConnectionManager;
use Drupal\acquia_contenthub\Event\AcquiaContentHubUnregisterEvent;
use Drupal\acquia_contenthub\Form\ContentHubSettingsForm;
use Drupal\Core\Form\FormState;
use Drush\Commands\DrushCommands;
use Drush\Log\LogLevel;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

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
   * Event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * ACH connection manager.
   *
   * @var \Drupal\acquia_contenthub\ContentHubConnectionManager
   */
  protected $achConnectionManager;

  /**
   * AcquiaContentHubSiteCommands constructor.
   *
   * @param \Drupal\acquia_contenthub\Client\ClientFactory $client_factory
   *   ACH client factory.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   Symfony event dispatcher.
   * @param \Drupal\acquia_contenthub\ContentHubConnectionManager $achConnectionManager
   *   ACH connection manager.
   */
  public function __construct(ClientFactory $client_factory, EventDispatcherInterface $eventDispatcher, ContentHubConnectionManager $achConnectionManager) {
    $this->clientFactory = $client_factory;
    $this->eventDispatcher = $eventDispatcher;
    $this->achConnectionManager = $achConnectionManager;
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
    if ($provider != 'core_config') {
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
   * @option delete
   *   Flag to delete all the entities from Content Hub.
   * @default delete
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
    if ($provider != 'core_config') {
      $message = dt(
        'Settings are being provided by %provider and cannot be disconnected manually.',
        ['%provider' => $provider]
      );
      $this->logger->log(LogLevel::CANCEL, $message);
      return;
    }

    $client = $this->clientFactory->getClient();
    $settings = $client->getSettings();
    $remote_settings = $client->getRemoteSettings();

    foreach ($remote_settings['webhooks'] as $webhook) {
      // Checks that webhook from settings and url from options are matching.
      $uri_option = $this->input->getOption('uri');
      if ($uri_option && $settings->getWebhook() !== $uri_option) {
        continue;
      }

      if ($webhook['client_name'] === $settings->getName()) {
        $webhook_uuid = $webhook['uuid'];
        break;
      }
    }

    if (empty($webhook_uuid)) {
      $this->logger->log(LogLevel::ERROR, 'Cannot find webhook UUID.');
      return;
    }

    $event = new AcquiaContentHubUnregisterEvent($webhook_uuid);
    $this->eventDispatcher->dispatch($event, AcquiaContentHubEvents::ACH_UNREGISTER);

    try {
      $delete = $this->input->getOption('delete');
      if ($delete === 'all') {
        $warning_message = dt('This command will delete ALL the entities published by this origin before unregistering the client. There is no way back from this action. It might take a while depending how many entities originated from this site. Are you sure you want to proceed (Y/n)?');
        if ($this->io()->confirm($warning_message) === FALSE) {
          $this->logger->log(LogLevel::ERROR, 'Cancelled.');
          return;
        }
        foreach ($event->getOrphanedEntities() as $entity) {
          if ($entity['type'] === 'client') {
            continue;
          }
          $client->deleteEntity($entity['uuid']);
        }
      }

      if ($event->getOrphanedEntitiesAmount() > 0 && $delete !== 'all') {
        $message = $this->output->writeln(dt('There are @count entities published from this origin. You have to delete/reoriginate those entities before proceeding with the unregistration. If you want to delete those entities and unregister the client, use the following drush command "drush ach-disconnect --delete=all".', [
          '@count' => $event->getOrphanedEntitiesAmount(),
        ]));
        $this->logger->log(LogLevel::CANCEL, $message);
        return;
      }

      $this->achConnectionManager->unregister($event);
    }
    catch (\Exception $exception) {
      $this->logger->log(LogLevel::ERROR, $exception->getMessage());
    }

    $config_factory = \Drupal::configFactory();
    $config = $config_factory->getEditable('acquia_contenthub.admin_settings');
    $client_name = $config->get('client_name');
    $config->delete();

    $message = dt(
      'Successfully disconnected site %site from contenthub',
      ['%site' => $client_name]
    );
    $this->logger->log(LogLevel::SUCCESS, $message);
  }

}
