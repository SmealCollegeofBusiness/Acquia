<?php

namespace Drupal\acquia_contenthub_publisher\Plugin\QueueWorker;

use Acquia\ContentHubClient\CDFDocument;
use Acquia\ContentHubClient\Syndication\SyndicationStatus;
use Drupal\acquia_contenthub\AcquiaContentHubEvents;
use Drupal\acquia_contenthub\Client\ClientFactory;
use Drupal\acquia_contenthub\ContentHubCommonActions;
use Drupal\acquia_contenthub\Event\PrunePublishCdfEntitiesEvent;
use Drupal\acquia_contenthub\Libs\InterestList\InterestListTrait;
use Drupal\acquia_contenthub\Libs\Logging\ContentHubLoggerInterface;
use Drupal\acquia_contenthub_publisher\PublisherTracker;
use Drupal\Component\Uuid\Uuid;
use Drupal\Core\Config\Config;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Acquia ContentHub queue worker.
 *
 * @QueueWorker(
 *   id = "acquia_contenthub_publish_export",
 *   title = "Queue Worker to export entities to contenthub."
 * )
 */
class ContentHubExportQueueWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  use InterestListTrait;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $dispatcher;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The common contenthub actions object.
   *
   * @var \Drupal\acquia_contenthub\ContentHubCommonActions
   */
  protected $common;

  /**
   * The published entity tracker.
   *
   * @var \Drupal\acquia_contenthub_publisher\PublisherTracker
   */
  protected $tracker;

  /**
   * Acquia Content Hub settings object.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $config;

  /**
   * The Content Hub Client.
   *
   * @var \Acquia\ContentHubClient\ContentHubClient
   */
  protected $client;

  /**
   * Content Hub logger.
   *
   * @var \Drupal\acquia_contenthub\Libs\Logging\ContentHubLoggerInterface
   */
  protected $chLogger;

  /**
   * ContentHubExportQueueWorker constructor.
   *
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $dispatcher
   *   The event dispatcher.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\acquia_contenthub\ContentHubCommonActions $common
   *   The common contenthub actions object.
   * @param \Drupal\acquia_contenthub\Client\ClientFactory $factory
   *   The client factory.
   * @param \Drupal\acquia_contenthub_publisher\PublisherTracker $tracker
   *   The published entity tracker.
   *   The event dispatcher.
   * @param \Drupal\Core\Config\Config $config
   *   The acquia_contenthub.admin_settings config object.
   * @param \Drupal\acquia_contenthub\Libs\Logging\ContentHubLoggerInterface $ch_logger
   *   Content Hub logger service.
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
    EntityTypeManagerInterface $entity_type_manager,
    ContentHubCommonActions $common,
    ClientFactory $factory,
    PublisherTracker $tracker,
    Config $config,
    ContentHubLoggerInterface $ch_logger,
    array $configuration,
    $plugin_id,
    $plugin_definition) {
    $this->dispatcher = $dispatcher;
    $this->common = $common;
    if (!empty($this->common->getUpdateDbStatus())) {
      throw new \Exception("Site has pending database updates. Apply these updates before exporting content.");
    }

    $this->entityTypeManager = $entity_type_manager;
    $this->client = $factory->getClient();
    $this->tracker = $tracker;
    $this->config = $config;
    $this->chLogger = $ch_logger;
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $container->get('event_dispatcher'),
      $container->get('entity_type.manager'),
      $container->get('acquia_contenthub_common_actions'),
      $container->get('acquia_contenthub.client.factory'),
      $container->get('acquia_contenthub_publisher.tracker'),
      $container->get('acquia_contenthub.config'),
      $container->get('acquia_contenthub_publisher.ch_logger'),
      $configuration,
      $plugin_id,
      $plugin_definition
    );
  }

  /**
   * {@inheritdoc}
   *
   * This method return values will be used within ContentHubExportQueue.
   * Different return values will log different messages and will indicate
   * different behaviours:
   *   return FALSE; => Error processing entities, queue item not deleted.
   *   return 0; => No processing done, queue item is not deleted
   *   return TRUE or return int which is not 0 =>
   *      Entities processed and queue item will be deleted.
   */
  public function processItem($data) {
    if (!$this->client) {
      $this->chLogger->getChannel()->error('Acquia Content Hub client cannot be initialized because connection settings are empty.');
      return FALSE;
    }
    $storage = $this->entityTypeManager->getStorage($data->type);
    $entity = $storage->loadByProperties(['uuid' => $data->uuid]);

    // Entity missing so remove it from the tracker and stop processing.
    if (!$entity) {
      $this->tracker->delete('entity_uuid', $data->uuid);
      $this->chLogger->logWarning(
        'Entity ("@entity_type", "@uuid") being exported no longer exists on the publisher. Deleting item from the publisher queue.',
        [
          '@entity_type' => $data->type,
          '@uuid' => $data->uuid,
        ]
      );
      return TRUE;
    }

    /** @var \Drupal\Core\Entity\EntityInterface $entity */
    $entity = reset($entity);
    $entities = [];
    $calculate_dependencies = $data->calculate_dependencies ?? TRUE;
    try {
      $output = $this->common->getEntityCdf($entity, $entities, TRUE, $calculate_dependencies);
    }
    catch (\Exception $ex) {
      $this->chLogger->logError('Entity: @entity_type - @uuid. Error: @error',
        [
          '@entity_type' => $entity->getEntityType()->getBundleLabel(),
          '@uuid' => $entity->uuid(),
        ]
      );

      throw $ex;
    }

    $document = new CDFDocument(...$output);

    // $output is a cdf of ALLLLLL entities that support the entity we wanted
    // to export. What happens if some of the entities which support the entity
    // we want to export were imported initially? We should dispatch an event
    // to look at the output and see if there are entries in our subscriber
    // table and then compare the rest against plexus data.
    $event = new PrunePublishCdfEntitiesEvent($this->client, $document, $this->config->get('origin'));
    $this->dispatcher->dispatch($event, AcquiaContentHubEvents::PRUNE_PUBLISH_CDF_ENTITIES);
    $output = array_values($event->getDocument()->getEntities());
    if (empty($output)) {
      $this->chLogger->logWarning('You are trying to export an empty CDF. Triggering entity: @entity, @uuid',
        [
          '@entity' => $data->type,
          '@uuid' => $data->uuid,
        ]
      );
      return 0;
    }

    $entity_uuids = [];
    foreach ($output as $item) {
      $wrapper = !empty($entities[$item->getUuid()]) ? $entities[$item->getUuid()] : NULL;
      if ($wrapper) {
        $this->tracker->track($wrapper->getEntity(), $wrapper->getHash());
        $this->tracker->nullifyQueueId($item->getUuid());
      }
      $entity_uuids[] = $item->getUuid();
    }
    $exported_entities = implode(', ', $entity_uuids);
    // ContentHub backend determines new or update on the PUT endpoint.
    $response = $this->client->putEntities(...$output);

    $webhook = $this->config->get('webhook.uuid');
    if (!Uuid::isValid($webhook)) {
      $this->chLogger->getChannel()->warning(
        sprintf(
          'Site does not have a valid registered webhook and it is required to add entities (%s) to the site\'s interest list in Content Hub.',
          $exported_entities
        )
      );
      return FALSE;
    }

    if ($response->getStatusCode() !== 202) {
      $event_ref = $this->chLogger->logError(
        'Request to Content Hub "/entities" endpoint returned with status code = @status_code. Triggering entity: @uuid.',
        [
          '@status_code' => $response->getStatusCode(),
          '@uuid' => $data->uuid,
        ]
      );
      $this->updateInterestList($entity_uuids, $webhook, SyndicationStatus::EXPORT_FAILED, $event_ref);

      return FALSE;
    }

    $this->updateInterestList($entity_uuids, $webhook, SyndicationStatus::EXPORT_SUCCESSFUL);

    return count($output);
  }

  /**
   * The extended interest list to add based on site role.
   *
   * @param array $uuids
   *   The entity uuids to build interest list from.
   * @param string $webhook_uuid
   *   The webhook uuid to register interest items for.
   * @param string $status
   *   The syndication status.
   * @param string|null $event_ref
   *   The id of the event.
   */
  protected function updateInterestList(array $uuids, string $webhook_uuid, string $status, ?string $event_ref = NULL): void {
    $send_update = $this->config->get('send_contenthub_updates') ?? TRUE;
    if (!$send_update) {
      return;
    }
    $interest_list = $this->buildInterestList(
      $uuids,
      $status,
      NULL,
      $event_ref
    );
    $exported_entities = implode(', ', $uuids);
    try {
      $this->client->updateInterestListBySiteRole($webhook_uuid, 'PUBLISHER', $interest_list);

      $this->chLogger->getChannel()
        ->info('The following exported entities have been added to the interest list with status "@syndication_status" for webhook @webhook: [@exported_entities].',
          [
            '@webhook' => $webhook_uuid,
            '@syndication_status' => $status,
            '@exported_entities' => $exported_entities,
          ]
        );
    }
    catch (\Exception $e) {
      $this->chLogger->getChannel()
        ->error('Error adding the following entities to the interest list with status "@syndication_status" for webhook @webhook: [@exported_entities]. Error message: @exception.',
          [
            '@webhook' => $webhook_uuid,
            '@syndication_status' => $status,
            '@exported_entities' => $exported_entities,
            '@exception' => $e->getMessage(),
          ]
        );
    }
  }

}
