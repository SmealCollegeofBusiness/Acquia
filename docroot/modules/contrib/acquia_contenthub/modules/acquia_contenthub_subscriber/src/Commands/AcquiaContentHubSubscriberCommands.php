<?php

namespace Drupal\acquia_contenthub_subscriber\Commands;

use Drupal\acquia_contenthub\Client\ClientFactory;
use Drupal\Core\Database\Connection;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Url;
use Drush\Commands\DrushCommands;

/**
 * Drush commands for Acquia Content Hub subscribers.
 *
 * @package Drupal\acquia_contenthub_subscriber\Commands
 */
class AcquiaContentHubSubscriberCommands extends DrushCommands {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The Content Hub Client.
   *
   * @var \Acquia\ContentHubClient\ContentHubClient
   */
  protected $client;

  /**
   * Logger Service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $chLogger;

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
   * @param \Drupal\acquia_contenthub\Client\ClientFactory $client_factory
   *   The Client Factory.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger
   *   The Logger Service.
   * @param \Drupal\Core\State\StateInterface $state
   *   The State Service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The Module Handler Service.
   */
  public function __construct(Connection $database, ClientFactory $client_factory, LoggerChannelFactoryInterface $logger, StateInterface $state, ModuleHandlerInterface $module_handler) {
    $this->database = $database;
    $this->client = $client_factory->getClient();
    $this->chLogger = $logger->get('acquia_contenthub_publisher');
    $this->state = $state;
    $this->moduleHandler = $module_handler;
  }

  /**
   * Subscriber Upgrade Command.
   *
   * Migrates filters and adds imported entities to interest list.
   *
   * @command acquia:contenthub-subscriber-upgrade
   * @aliases ach-subscriber-upgrade,ach-suup
   */
  public function upgrade() {
    // Only proceed if there still exists a legacy tracking table.
    if (!$this->database->schema()->tableExists('acquia_contenthub_entities_tracking')) {
      $this->chLogger->warning(dt('Legacy tracking table does not exist.'));
      return;
    }
    // Make sure webhook stored is actually registered for this site in Plexus.
    if (!$this->client->getSettings()->getWebhook()) {
      // Proceed to register a webhook in HMAC v2.
      $webhook_url = Url::fromUri('internal:' . '/acquia-contenthub/webhook', [
        'absolute' => TRUE,
      ])->toString();
      $webhook = $this->client->getWebHook($webhook_url);
      if (empty($webhook)) {
        $connection_manager = \Drupal::service('acquia_contenthub.connection_manager');
        $response = $connection_manager->registerWebhook($webhook_url);
        if (isset($response['success']) && FALSE === $response['success']) {
          $message = dt('Registering webhooks encountered an error (code @code). @reason', [
            '@code' => $response['error']['code'],
            '@reason' => $response['error']['message'],
          ]);
          $this->chLogger->error($message);
          return;
        }
      }
    }
    // Bail out early if legacy filters do not exist.
    $filters = $this->state->get('acquia_contenthub_subscriber_82002_acquia_contenthub_filters', []);
    if (empty($filters)) {
      $this->chLogger->alert('This site has no filters to migrate.');
    }
    else {
      // Migrating filters.
      $this->moduleHandler->loadInclude('acquia_contenthub_subscriber', 'inc', 'acquia_contenthub_subscriber.filters.migrate');
      $unmigrated_filters = [];
      $migrated_filters = [];
      foreach ($filters as $contenthub_filter) {
        $cloud_filter = acquia_contenthub_subscriber_migrate_filter($contenthub_filter);
        if (!acquia_contenthub_subscriber_put_filter($cloud_filter)) {
          // Could not migrate to Cloud Filter. Save filter on a state variable.
          $unmigrated_filters[] = $contenthub_filter;
        }
        else {
          $migrated_filters[] = $contenthub_filter['name'];
        }
      }
      if (!empty($migrated_filters)) {
        $this->chLogger->info(dt('Filters migrated successfully: %filters.', [
          '%filters' => implode(',', $migrated_filters),
        ]));
      }
      if (!empty($unmigrated_filters)) {
        // Saving unmigrated filters in state variable.
        $this->state->set('acquia_contenthub_subscriber_82002_unmigrated_filters', $unmigrated_filters);
        $this->chLogger->warning(dt('The following filters could not be properly migrated: %filters.', [
          '%filters' => implode(',', array_column($unmigrated_filters, 'name')),
        ]));
      }
      // Delete State variable.
      $this->state->delete('acquia_contenthub_subscriber_82002_acquia_contenthub_filters');
    }
    // Adding imported entities to interest list.
    $path = $this->moduleHandler->getModule('acquia_contenthub_subscriber')->getPath();
    $batch = [
      'title' => t('Adding Imported Entities to Interest List'),
      'operations' => [
        ['acquia_contenthub_subscriber_track_imported_linked_entities', []],
        ['acquia_contenthub_subscriber_track_imported_unlinked_entities', []],
      ],
      'file' => $path . '/acquia_contenthub_subscriber.migrate.inc',
    ];
    batch_set($batch);
    drush_backend_batch_process();
  }

}
