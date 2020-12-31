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
   * The client factory.
   *
   * @var \Drupal\acquia_contenthub\Client\ClientFactory
   */
  protected $factory;

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
  protected $loggerFactory;

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
  public function __construct(EventDispatcherInterface $dispatcher, EntityTypeManagerInterface $entity_type_manager, ContentHubCommonActions $common, ClientFactory $factory, PublisherTracker $tracker, ConfigFactoryInterface $config_factory, LoggerChannelFactoryInterface $logger_factory, array $configuration, $plugin_id, $plugin_definition) {
    $this->dispatcher = $dispatcher;
    $this->common = $common;
    if (!empty($this->common->getUpdateDbStatus())) {
      throw new \Exception("Site has pending database updates. Apply these updates before exporting content.");
    }

    $this->entityTypeManager = $entity_type_manager;
    $this->factory = $factory;
    $this->tracker = $tracker;
    $this->configFactory = $config_factory;
    $this->loggerFactory = $logger_factory;
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
   */
  public function processItem($data) {

    $client = $this->factory->getClient();
    $storage = $this->entityTypeManager->getStorage($data->type);

    $entity = $storage->loadByProperties(['uuid' => $data->uuid]);

    // Entity missing so remove it from the tracker and stop processing.
    if (!$entity) {
      $this->tracker->delete($data->uuid);
      return TRUE;
    }

    $entity = reset($entity);
    $entities = [];
    $output = $this->common->getEntityCdf($entity, $entities);
    $config = $this->configFactory->get('acquia_contenthub.admin_settings');
    $document = new CDFDocument(...$output);

    // $output is a cdf of ALLLLLL entities that support the entity we wanted
    // to export. What happens if some of the entities which support the entity
    // we want to export were imported initially? We should dispatch an event
    // to look at the output and see if there are entries in our subscriber
    // table and then compare the rest against plexus data.
    $logger = $this->loggerFactory->get('acquia_contenthub');
    $event = new PrunePublishCdfEntitiesEvent($client, $document, $config->get('origin'));
    $this->dispatcher->dispatch(AcquiaContentHubEvents::PRUNE_PUBLISH_CDF_ENTITIES, $event);
    $output = array_values($event->getDocument()->getEntities());
    if (empty($output)) {
      $logger->warning(sprintf('You are trying to export an empty CDF. Triggering entity: %s', $data->uuid));
      return 0;
    }

    // ContentHub backend determines new or update on the PUT endpoint.
    $response = $client->putEntities(...$output);
    if ($response->getStatusCode() == 202) {
      $entity_uuids = [];
      foreach ($output as $item) {
        $wrapper = !empty($entities[$item->getUuid()]) ? $entities[$item->getUuid()] : NULL;
        if ($wrapper) {
          $this->tracker->track($wrapper->getEntity(), $wrapper->getHash());
          $this->tracker->nullifyQueueId($item->getUuid());
        }
        $entity_uuids[] = $item->getUuid();
      }

      // Reinitialize client cdf metrics data.
      $this->factory->getClient();

      $webhook = $config->get('webhook.uuid');

      if (Uuid::isValid($webhook)) {
        try {
          $client->addEntitiesToInterestList($webhook, $entity_uuids);
          $logger->info('Exported entities added to Interest List on Content Hub');
        }
        catch (\Exception $e) {
          $logger->error(sprintf('Message: %s.', $e->getMessage()));
        }
      }
      else {
        $logger->warning('Site does not have a valid registered webhook and it is required to add entities to the site\'s interest list in Content Hub.');
      }

      return count($output);
    }

    return FALSE;
  }

}
