<?php

namespace Drupal\acquia_contenthub_publisher\Commands;

use Drupal\acquia_contenthub\Client\ClientFactory;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Url;
use Drush\Commands\DrushCommands;
use Drush\Log\LogLevel;

/**
 * Drush commands for Acquia Content Hub Publishers.
 *
 * @package Drupal\acquia_contenthub_publisher\Commands
 */
class AcquiaContentHubPublisherCommands extends DrushCommands {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;
  /**
   * The Content Hub Configuration.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;
  /**
   * Content Hub Client Factory.
   *
   * @var \Drupal\acquia_contenthub\Client\ClientFactory
   */
  protected $clientFactory;
  /**
   * Logger Service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;
  /**
   * State Service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;
  /**
   * Module Handler Service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Public Constructor.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The Config Factory.
   * @param \Drupal\acquia_contenthub\Client\ClientFactory $client_factory
   *   The Client Factory.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger
   *   The Logger Service.
   * @param \Drupal\Core\State\StateInterface $state
   *   The State Service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The Module Handler Service.
   */
  public function __construct(Connection $database, ConfigFactoryInterface $config_factory, ClientFactory $client_factory, LoggerChannelFactoryInterface $logger, StateInterface $state, ModuleHandlerInterface $module_handler) {
    $this->database = $database;
    $this->config = $config_factory->get('acquia_contenthub.admin_settings');
    $this->clientFactory = $client_factory;
    $this->logger = $logger->get('acquia_contenthub_publisher');
    $this->state = $state;
    $this->moduleHandler = $module_handler;
  }

  /**
   * Publisher Upgrade Command.
   *
   * @command acquia:contenthub-publisher-upgrade
   * @aliases ach-publisher-upgrade,ach-puup
   */
  public function upgrade() {
    // Only proceed if there still exists a legacy tracking table.
    if (!$this->database->schema()->tableExists('acquia_contenthub_entities_tracking')) {
      $this->logger->log(LogLevel::CANCEL, dt('Legacy tracking table does not exist.'));
      return;
    }

    // Make sure webhook stored is actually registered for this site in Plexus.
    $settings = $this->clientFactory->getSettings();
    $client = $this->clientFactory->getClient($settings);
    if (!$client->getSettings()->getWebhook()) {
      // Proceed to register a webhook in HMAC v2.
      $webhook_url = Url::fromUri('internal:' . '/acquia-contenthub/webhook', [
        'absolute' => TRUE,
      ])->toString();
      $webhook = $client->getWebHook($webhook_url);
      if (empty($webhook)) {
        $connection_manager = \Drupal::service('acquia_contenthub.connection_manager');
        $response = $connection_manager->registerWebhook($webhook_url);
        if (isset($response['success']) && FALSE === $response['success']) {
          $message = dt('Registering webhooks encountered an error (code @code). @reason', [
            '@code' => $response['error']['code'],
            '@reason' => $response['error']['message'],
          ]);
          $this->logger->log(LogLevel::ERROR, $message);
          return;
        }
      }
    }
    // Enqueue all exported entities.
    $path = $this->moduleHandler->getModule('acquia_contenthub_publisher')->getPath();
    $batch = [
      'title' => t('Exporting'),
      'operations' => [
        ['acquia_contenthub_publisher_enqueue_exported_entities', []],
      ],
      'finished' => 'acquia_contenthub_publisher_enqueue_exported_entities_finished',
      'file' => $path . '/acquia_contenthub_publisher.migrate.inc',
    ];
    batch_set($batch);
    drush_backend_batch_process();
  }

}
