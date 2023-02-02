<?php

namespace Drupal\acquia_contenthub_subscriber\Plugin\QueueWorker;

use Acquia\ContentHubClient\Syndication\SyndicationEvents;
use Acquia\ContentHubClient\Syndication\SyndicationStatus;
use Drupal\acquia_contenthub\Client\CdfMetricsManager;
use Drupal\acquia_contenthub\Client\ClientFactory;
use Drupal\acquia_contenthub\Libs\InterestList\InterestListTrait;
use Drupal\acquia_contenthub\Libs\Logging\ContentHubLoggerInterface;
use Drupal\acquia_contenthub_subscriber\CdfImporter;
use Drupal\acquia_contenthub_subscriber\Exception\ContentHubImportException;
use Drupal\acquia_contenthub_subscriber\SubscriberTracker;
use Drupal\Core\Config\Config;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\depcalc\DependencyStack;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Queue worker for importing entities.
 *
 * @QueueWorker(
 *   id = "acquia_contenthub_subscriber_import",
 *   title = "Queue Worker to import entities from contenthub."
 * )
 */
class ContentHubImportQueueWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  use InterestListTrait;

  /**
   * The role of the site.
   */
  protected const SITE_ROLE = 'SUBSCRIBER';

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $dispatcher;

  /**
   * The CDF importer object.
   *
   * @var \Drupal\acquia_contenthub_subscriber\CdfImporter
   */
  protected $importer;

  /**
   * The Content Hub Client.
   *
   * @var \Acquia\ContentHubClient\ContentHubClient
   */
  protected $client;

  /**
   * The Subscriber Tracker.
   *
   * @var \Drupal\acquia_contenthub_subscriber\SubscriberTracker
   */
  protected $tracker;

  /**
   * Acquia Content Hub settings object.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $config;

  /**
   * Content Hub logger.
   *
   * @var \Drupal\acquia_contenthub\Libs\Logging\ContentHubLoggerInterface
   */
  protected $chLogger;

  /**
   * Cdf Metrics Manager.
   *
   * @var \Drupal\acquia_contenthub\Client\CdfMetricsManager
   */
  protected $cdfMetricsManager;

  /**
   * ContentHubExportQueueWorker constructor.
   *
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $dispatcher
   *   Dispatcher.
   * @param \Drupal\acquia_contenthub_subscriber\CdfImporter $cdf_importer
   *   Cdf Importer.
   * @param \Drupal\acquia_contenthub\Client\ClientFactory $client_factory
   *   The client factory.
   * @param \Drupal\acquia_contenthub_subscriber\SubscriberTracker $tracker
   *   The Subscriber Tracker.
   * @param \Drupal\Core\Config\Config $config
   *   The acquia_contenthub.admin_settings config object.
   * @param \Drupal\acquia_contenthub\Libs\Logging\ContentHubLoggerInterface $ch_logger
   *   Content Hub logger service.
   * @param \Drupal\acquia_contenthub\Client\CdfMetricsManager $cdf_metrics_manager
   *   Cdf metrics manager.
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin id.
   * @param mixed $plugin_definition
   *   The plugin definition.
   *
   * @throws \Exception
   */
  public function __construct(
    EventDispatcherInterface $dispatcher,
    CdfImporter $cdf_importer,
    ClientFactory $client_factory,
    SubscriberTracker $tracker,
    Config $config,
    ContentHubLoggerInterface $ch_logger,
    CdfMetricsManager $cdf_metrics_manager,
    array $configuration,
    $plugin_id,
    $plugin_definition) {
    $this->importer = $cdf_importer;
    if (!empty($this->importer->getUpdateDbStatus())) {
      throw new \Exception("Site has pending database updates. Apply these updates before importing content.");
    }
    $this->dispatcher = $dispatcher;
    $this->client = $client_factory->getClient();
    $this->tracker = $tracker;
    $this->config = $config;
    $this->chLogger = $ch_logger;
    $this->cdfMetricsManager = $cdf_metrics_manager;
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $container->get('event_dispatcher'),
      $container->get('acquia_contenthub_subscriber.cdf_importer'),
      $container->get('acquia_contenthub.client.factory'),
      $container->get('acquia_contenthub_subscriber.tracker'),
      $container->get('acquia_contenthub.config'),
      $container->get('acquia_contenthub_subscriber.ch_logger'),
      $container->get('acquia_contenthub.cdf_metrics_manager'),
      $configuration,
      $plugin_id,
      $plugin_definition
    );
  }

  /**
   * Processes acquia_contenthub_subscriber_import queue items.
   *
   * @param mixed $data
   *   The data in the queue.
   *
   * @throws \Exception
   */
  public function processItem($data): void {
    if (!$this->client) {
      $this->chLogger->getChannel()
        ->error('Acquia Content Hub client cannot be initialized because connection settings are empty.');
      return;
    }

    $settings = $this->client->getSettings();
    $webhook = $settings->getWebhook('uuid');
    if (!$webhook) {
      return;
    }

    $interests = $this->getInterestList($webhook);
    if (is_null($interests)) {
      return;
    }

    $process_items = explode(', ', $data->uuids);
    $uuids = $this->filterDeletedItems($process_items, array_keys($interests));
    $this->logMissingUuids($uuids, $process_items);
    if (!$uuids) {
      return;
    }

    $stack = $this->importEntities($uuids, $webhook);
    if (!$stack) {
      return;
    }
    // Log events irrespective of the fact send_update is true or not.
    $this->logImportedEntities(array_keys($stack->getDependencies()));

    if (!$this->shouldSendUpdate()) {
      return;
    }

    $this->updateInterestList($webhook, $uuids, SyndicationStatus::IMPORT_SUCCESSFUL);

    $filter_uuid = $data->filter_uuid ?? NULL;
    $this->addDependenciesToInterestList(
      $webhook,
      array_keys($stack->getDependencies()),
      $filter_uuid
    );
  }

  /**
   * Returns the interest list related to webhook.
   *
   * @param string $webhook
   *   Webhook uuid.
   *
   * @return array
   *   List of entity uuids.
   */
  protected function getInterestList(string $webhook): ?array {
    try {
      return $this->client->getInterestsByWebhookAndSiteRole($webhook, static::SITE_ROLE);
    }
    catch (\Exception $exception) {
      $this->chLogger->getChannel()->error(
        sprintf(
          'Following error occurred while we were trying to get the interest list: %s',
          $exception->getMessage()
        )
      );
    }
    return NULL;
  }

  /**
   * Imports entities and logs exceptions.
   *
   * @param array $uuids
   *   The entities to import.
   * @param string $webhook
   *   The webhook which the interest list belongs to.
   *
   * @return \Drupal\depcalc\DependencyStack|null
   *   Dependency stack if the process was successful.
   *
   * @throws \Drupal\acquia_contenthub_subscriber\Exception\ContentHubImportException
   */
  protected function importEntities(array $uuids, string $webhook): ?DependencyStack {
    try {
      $stack = $this->importer->importEntities(...$uuids);
      $this->cdfMetricsManager->sendClientCdfUpdates();

      return $stack;
    }
    catch (ContentHubImportException $e) {
      $e_uuids = $e->getUuids();
      $deletable = !array_diff($uuids, $e_uuids) && $e->isEntitiesMissing();
      $note = $e->getCode() === ContentHubImportException::MISSING_ENTITIES ?
        'Check export table, nullify hashes of entities marked as missing and try to re-export original entity/entities.' : 'N/A';
      if (!$deletable) {
        if ($this->shouldSendUpdate()) {
          $event_ref = $this->chLogger->logError('Entity uuids: "@uuids". Error: "@error". Note: @note',
            [
              '@uuids' => implode(',', $e_uuids),
              '@error' => $e->getMessage(),
              '@note' => $note,
            ]
          );
          $this->updateInterestList($webhook, $uuids, SyndicationStatus::IMPORT_FAILED, $event_ref);
        }
        else {
          // There are import problems but probably on dependent entities.
          $this->chLogger->getChannel()
            ->error(sprintf('Import failed: %s.', $e->getMessage()));
        }

        $triggering_uuids = $e->getTriggeringUuids();
        array_walk($triggering_uuids, function (&$msg) use ($note) {
          $msg = sprintf('%s Note: %s', $msg, $note);
        });

        $this->chLogger->getEvent()->logMultipleEntityEvents(
          SyndicationEvents::IMPORT_FAILURE['severity'],
          $triggering_uuids,
          SyndicationEvents::IMPORT_FAILURE['name']
        );

        throw $e;
      }
      // The UUIDs can't be imported since they aren't in the Service.
      // The missing UUIDs are the same as the ones that were sent for import.
      foreach ($uuids as $uuid) {
        $this->deleteFromTrackingTableAndInterestList($uuid, $webhook);
      }
    }
    catch (\Exception $default_exception) {
      $this->chLogger->logError(
        'Error: @error',
        ['@error' => $default_exception->getMessage()]
      );

      throw $default_exception;
    }

    return NULL;
  }

  /**
   * Updates interest list.
   *
   * @param string $webhook
   *   The webhook uuid.
   * @param array $uuids
   *   Entity uuids to update.
   * @param string $status
   *   The status of the syndication.
   * @param string|null $event_ref
   *   The event reference.
   */
  protected function updateInterestList(string $webhook, array $uuids, string $status, ?string $event_ref = NULL): void {
    $interest_list = $this->buildInterestList($uuids, $status, NULL, $event_ref);
    try {
      $this->client->updateInterestListBySiteRole(
        $webhook,
        static::SITE_ROLE,
        $interest_list
      );
    }
    catch (\Exception $e) {
      $this->chLogger->getChannel()->error(
        sprintf('Could not update interest list. Reason: %s', $e->getMessage())
      );
    }
  }

  /**
   * Deletes entity from tracking table and interest list.
   *
   * @param string $uuid
   *   The uuid of the entity.
   * @param string $webhook
   *   The webhook the entity is related to.
   */
  protected function deleteFromTrackingTableAndInterestList(string $uuid, string $webhook): void {
    try {
      if ($this->tracker->getEntityByRemoteIdAndHash($uuid)) {
        return;
      }
      // If we cannot load, delete interest and tracking record.
      if ($this->shouldSendUpdate()) {
        $this->client->deleteInterest($uuid, $webhook);
      }
      $this->tracker->delete('entity_uuid', $uuid);
      $this->chLogger->getChannel()->info(
        sprintf(
          'The following entity was deleted from interest list and tracking table: %s',
          $uuid
        )
      );
    }
    catch (\Exception $ex) {
      $this->chLogger->getChannel()
        ->error(sprintf(
          'Entity deletion from tracking table and interest list failed. Entity: %s. Message: %s',
          $uuid,
          $ex->getMessage()
        ));
    }
  }

  /**
   * Filters potentially deleted items.
   *
   * @param array $process_items
   *   The entities being processed.
   * @param array $interests
   *   The list of entity uuids.
   *
   * @return array
   *   Filtered list of entity uuids.
   */
  protected function filterDeletedItems(array $process_items, array $interests): array {
    $uuids = array_intersect($process_items, $interests);
    if (!$uuids) {
      $this->chLogger->getChannel()
        ->info('There are no matching entities in the queues and the site interest list.');
    }
    return $uuids;
  }

  /**
   * Logs the uuids no longer on the interest list for this webhook.
   *
   * @param array $uuids
   *   Entity uuids.
   * @param array $process_items
   *   The entities being processed.
   */
  protected function logMissingUuids(array $uuids, array $process_items): void {
    if (count($uuids) !== count($process_items)) {
      $missing_uuids = array_diff($process_items, $uuids);
      $this->chLogger->getChannel()->info(
          sprintf(
            'Skipped importing the following missing entities: %s. This occurs when entities are deleted at the Publisher before importing.',
            implode(', ', $missing_uuids))
        );
    }
  }

  /**
   * Sends a log record to events endpoint about every imported entity.
   *
   * @param array $uuids
   *   Array of imported entities.
   */
  protected function logImportedEntities(array $uuids): void {
    $logs = array_fill_keys($uuids, 'Successfully imported UUID');
    $this->chLogger->getEvent()->logMultipleEntityEvents(
      SyndicationEvents::IMPORT_SUCCESS['severity'],
      $logs,
      SyndicationEvents::IMPORT_SUCCESS['name']
    );
  }

  /**
   * Adds dependencies to interest list.
   *
   * @param string $webhook
   *   The webhook to assign entities to.
   * @param array $uuids
   *   The entity uuids to add.
   * @param string|null $filter_uuid
   *   Filter uuid to include in the reason if there's one.
   */
  protected function addDependenciesToInterestList(string $webhook, array $uuids, ?string $filter_uuid): void {
    $interest_list = $this->buildInterestList(
      $uuids,
      SyndicationStatus::IMPORT_SUCCESSFUL,
      $filter_uuid ?? 'manual'
    );
    try {
      $this->client->addEntitiesToInterestListBySiteRole($webhook, static::SITE_ROLE, $interest_list);
      $this->chLogger->getChannel()->info(
        sprintf(
          'The following imported entities have been added to the interest list on Content Hub for webhook "%s": [%s].',
          $webhook,
          implode(', ', $uuids)
        )
      );
    }
    catch (\Exception $e) {
      $this->chLogger->getChannel()->error(
        sprintf(
          'Error adding the following entities to the interest list for webhook "%s": [%s]. Error message: "%s".',
          $webhook,
          implode(', ', $uuids),
          $e->getMessage()
        )
      );
    }
  }

  /**
   * Returns send_contenthub_updates config value.
   *
   * @return bool
   *   Setting of the configuration.
   */
  protected function shouldSendUpdate(): bool {
    return $this->config->get('send_contenthub_updates') ?? TRUE;
  }

}
