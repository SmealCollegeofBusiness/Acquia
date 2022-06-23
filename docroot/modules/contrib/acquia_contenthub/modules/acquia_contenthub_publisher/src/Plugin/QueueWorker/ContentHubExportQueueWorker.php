<?php

namespace Drupal\acquia_contenthub_publisher\Plugin\QueueWorker;

use Acquia\ContentHubClient\CDFDocument;
use Drupal\acquia_contenthub\AcquiaContentHubEvents;
use Drupal\acquia_contenthub\Client\ClientFactory;
use Drupal\acquia_contenthub\ContentHubCommonActions;
use Drupal\acquia_contenthub\Event\PrunePublishCdfEntitiesEvent;
use Drupal\acquia_contenthub_publisher\PublisherTracker;
use Drupal\Component\Uuid\Uuid;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
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
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The logger channel factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $achLoggerChannel;

  /**
   * The Content Hub Client.
   *
   * @var \Acquia\ContentHubClient\ContentHubClient
   */
  protected $client;

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
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
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
    ConfigFactoryInterface $config_factory,
    LoggerChannelFactoryInterface $logger_factory,
    array $configuration,
    $plugin_id,
    $plugin_definition
  ) {
    $this->dispatcher = $dispatcher;
    $this->common = $common;
    if (!empty($this->common->getUpdateDbStatus())) {
      throw new \Exception("Site has pending database updates. Apply these updates before exporting content.");
    }

    $this->entityTypeManager = $entity_type_manager;
    $this->client = $factory->getClient();
    $this->tracker = $tracker;
    $this->configFactory = $config_factory;
    $this->achLoggerChannel = $logger_factory->get('acquia_contenthub_publisher');
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
      $container->get('config.factory'),
      $container->get('logger.factory'),
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
      $this->achLoggerChannel->error('Acquia Content Hub client cannot be initialized because connection settings are empty.');
      return FALSE;
    }

    $storage = $this->entityTypeManager->getStorage($data->type);
    $entity = $storage->loadByProperties(['uuid' => $data->uuid]);

    // Entity missing so remove it from the tracker and stop processing.
    if (!$entity) {
      $this->tracker->delete('entity_uuid', $data->uuid);
      $this->achLoggerChannel->warning(
        sprintf(
          'Entity ("%s", "%s") being exported no longer exists on the publisher. Deleting item from the publisher queue.',
          $data->type,
          $data->uuid
        )
      );
      return TRUE;
    }

    $entity = reset($entity);
    $entities = [];
    $calculate_dependencies = $data->calculate_dependencies ?? TRUE;
    $output = $this->common->getEntityCdf($entity, $entities, TRUE, $calculate_dependencies);
    $config = $this->configFactory->get('acquia_contenthub.admin_settings');
    $document = new CDFDocument(...$output);

    // $output is a cdf of ALLLLLL entities that support the entity we wanted
    // to export. What happens if some of the entities which support the entity
    // we want to export were imported initially? We should dispatch an event
    // to look at the output and see if there are entries in our subscriber
    // table and then compare the rest against plexus data.
    $event = new PrunePublishCdfEntitiesEvent($this->client, $document, $config->get('origin'));
    $this->dispatcher->dispatch($event, AcquiaContentHubEvents::PRUNE_PUBLISH_CDF_ENTITIES);
    $output = array_values($event->getDocument()->getEntities());
    if (empty($output)) {
      $this->achLoggerChannel->warning(
        sprintf('You are trying to export an empty CDF. Triggering entity: %s, %s',
          $data->type,
          $data->uuid
        )
      );
      return 0;
    }

    // ContentHub backend determines new or update on the PUT endpoint.
    $response = $this->client->putEntities(...$output);
    if ($response->getStatusCode() !== 202) {
      $this->achLoggerChannel->error(
        sprintf(
          'Request to Content Hub "/entities" endpoint returned with status code = %s. Triggering entity: %s.',
          $response->getStatusCode(),
          $data->uuid
        )
      );
      return FALSE;
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

    $webhook = $config->get('webhook.uuid') ?? '';
    $exported_entities = implode(', ', $entity_uuids);

    if (!Uuid::isValid($webhook)) {
      $this->achLoggerChannel->warning(
        sprintf(
          'Site does not have a valid registered webhook and it is required to add entities (%s) to the site\'s interest list in Content Hub.',
          $exported_entities
        )
      );
      return FALSE;
    }

    if (($config->get('send_contenthub_updates') ?? TRUE)) {
      try {
        $this->client->addEntitiesToInterestList($webhook, $entity_uuids);
        $this->achLoggerChannel->info(
          sprintf(
            'The following exported entities have been added to the interest list on Content Hub for webhook "%s": [%s].',
            $webhook,
            $exported_entities
          )
        );
      }
      catch (\Exception $e) {
        $this->achLoggerChannel->error(
          sprintf(
            'Error adding the following entities to the interest list for webhook "%s": [%s]. Error message: "%s".',
            $webhook,
            $exported_entities,
            $e->getMessage()
          )
        );
      }
    }

    return count($output);
  }

}
