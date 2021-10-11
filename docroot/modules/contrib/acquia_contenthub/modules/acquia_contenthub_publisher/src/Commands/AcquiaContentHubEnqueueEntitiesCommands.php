<?php

namespace Drupal\acquia_contenthub_publisher\Commands;

use Drupal\acquia_contenthub_publisher\PublisherTracker;
use Drupal\Component\Uuid\Uuid;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\depcalc\Cache\DepcalcCacheBackend;
use Drush\Commands\DrushCommands;

/**
 * Drush commands for Acquia Content Hub enqueue entities.
 *
 * @package Drupal\acquia_contenthub_publisher\Commands
 */
class AcquiaContentHubEnqueueEntitiesCommands extends DrushCommands {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity type bundle info.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityTypeBundleInfo;

  /**
   * The Database Connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The Publisher Tracker.
   *
   * @var \Drupal\acquia_contenthub_publisher\PublisherTracker
   */
  protected $publisherTracker;

  /**
   * The Depcalc Cache backend.
   *
   * @var \Drupal\depcalc\Cache\DepcalcCacheBackend
   */
  protected $depcalcCache;

  /**
   * AcquiaContentHubEnqueueEntitiesByBundleCommands constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle info.
   * @param \Drupal\Core\Database\Connection $database
   *   The Connection to the Database.
   * @param \Drupal\acquia_contenthub_publisher\PublisherTracker $publisher_tracker
   *   The Publisher Tracker.
   * @param \Drupal\depcalc\Cache\DepcalcCacheBackend $depcalc_cache
   *   The Depcalc Cache Backend.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, EntityTypeBundleInfoInterface $entity_type_bundle_info, Connection $database, PublisherTracker $publisher_tracker, DepcalcCacheBackend $depcalc_cache) {
    $this->entityTypeManager = $entityTypeManager;
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
    $this->database = $database;
    $this->publisherTracker = $publisher_tracker;
    $this->depcalcCache = $depcalc_cache;
  }

  /**
   * Re-queues entities to Acquia Content Hub.
   *
   * At least one option has to be Provided.
   *
   * @option type
   *   The entity type.
   * @default type
   * @option uuid
   *   The entity UUID.
   * @default uuid
   * @option bundle
   *   The entity bundle.
   * @default bundle
   * @option use-tracking-table
   *   Only re-queue entities that are in the Publisher tracking table
   * If not used, it will enqueue ALL entities of the provided type and bundle.
   * @default false
   * @option only-queued-entities
   *   Only re-queue entities that have "queued" status in the tracking table
   * but are not in the export queue.
   * @default false
   *
   * @usage ach-rq
   *   You have to provide at least one option.
   * @usage acquia:contenthub-re-queue --type=node
   *   Requeues all eligible node entities in the site
   * (disregards what has been published before).
   * @usage acquia:contenthub-re-queue --type=node --bundle=page
   *   Requeues all eligible node pages in the site
   * (disregards what has been published before).
   * @usage acquia:contenthub-re-queue --type=node --uuid=00000aa0-a00a-000a-aa00-aaa000a0a0aa
   *   Requeues entity by UUID
   * (disregards all other options).
   * @usage acquia:contenthub-re-queue --only-queued-entities
   *   Requeues all entities with "queued" status in the publisher tracking
   * table but are not in the export queue anymore.
   * @usage acquia:contenthub-re-queue --use-tracking-table
   *   Requeues all existing entities in the publisher tracking table.
   * @usage acquia:contenthub-re-queue --type=node --bundle=article --use-tracking-table
   *   Requeues all node article entities that have been previously
   * published (they exist in the publisher tracking table).
   *
   * @command acquia:contenthub-re-queue
   * @aliases ach-rq
   *
   * @return int
   *   The exit code.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Exception
   */
  public function enqueueEntities():int {
    $data = [];

    $entity_type = $this->input->getOption('type');
    if ($entity_type) {
      $storage = $this->entityTypeManager->getStorage($entity_type);
    }

    $uuid = $this->input->getOption('uuid');
    if ($storage && $uuid) {
      return $this->enqueueByUuid($storage, $uuid);
    }

    $bundle = $this->input->getOption('bundle');
    if ($bundle) {
      $data = $this->checkBundleKeyAndInfo($entity_type, $bundle);
    }

    $only_queued_entities = $this->input->getOption('only-queued-entities');
    $use_tracking_table = $this->input->getOption('use-tracking-table');
    if (!$only_queued_entities && !$use_tracking_table && !$entity_type) {
      $this->logger()->error(dt('You cannot use the command without any options. Please provide at least one option.'));
      return self::EXIT_FAILURE_WITH_CLARITY;
    }

    // Always enqueue entities that have "QUEUED" status
    // but are not in the queue.
    $queued_entities = $this->publisherTracker->listTrackedEntities(PublisherTracker::QUEUED);
    $exit_code = $this->enqueueTrackedOrQueuedEntities($queued_entities, $entity_type, $bundle);

    if ($only_queued_entities) {
      return $exit_code;
    }

    // Clear the depcalc cache table.
    $this->depcalcCache->deleteAll();

    // We are nullifying hashes for ALL entities to make sure
    // all dependencies are re-exported.
    $this->publisherTracker->nullifyHashes();

    if (!$use_tracking_table) {
      // We are going to re-queue ALL entities that match a particular entity
      // type (and bundle) disregarding whether they have been previously
      // published or not.
      return $this->enqueueWithoutTrackingTable($entity_type, $bundle, $data, $storage);
    }

    // We are going to re-queue ALL entities
    // (if given options of a particular entity type and bundle)
    // that have been previously published.
    $tracked_entities = $this->publisherTracker->listTrackedEntities(
      [PublisherTracker::EXPORTED, PublisherTracker::CONFIRMED],
      $entity_type
    );
    return $this->enqueueTrackedOrQueuedEntities($tracked_entities, $entity_type, $bundle, TRUE);
  }

  /**
   * Check entity bundle key and info.
   *
   * @param string $entity_type
   *   The entity type.
   * @param string $bundle
   *   The entity bundle.
   *
   * @return mixed
   *   Array containing bundle key and bundle info.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Exception
   */
  protected function checkBundleKeyAndInfo(string $entity_type, string $bundle) {
    $bundle_key = $this->entityTypeManager->getDefinition($entity_type)->getKey('bundle');
    if (empty($bundle_key)) {
      throw new \Exception(sprintf('The "%s" entity type does not support bundles.', $entity_type));
    }

    $bundle_info = $this->entityTypeBundleInfo->getBundleInfo($entity_type);
    if (!in_array($bundle, array_keys($bundle_info))) {
      throw new \Exception(sprintf('The bundle "%s" does not exist.', $bundle));
    }

    return [
      $bundle_key => $bundle,
    ];
  }

  /**
   * Enqueue tracked or queued entities.
   *
   * @param array $entities
   *   Entities array.
   * @param string $entity_type
   *   The entity type.
   * @param string $bundle
   *   The entity bundle.
   * @param bool|false $track
   *   Entity is tracked or not.
   *
   * @return int
   *   The exit code.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Exception
   */
  protected function enqueueTrackedOrQueuedEntities(array $entities, string $entity_type, string $bundle, bool $track = FALSE):int {
    $count = 0;
    foreach ($entities as $enqueue_entity) {
      $entity_type_id = ($track && !empty($entity_type)) ? $entity_type : $enqueue_entity['entity_type'];
      // Enqueue entity if it is not already enqueued.
      $entity = $this->entityTypeManager->getStorage($entity_type_id)->load($enqueue_entity['entity_id']);

      if (!$entity) {
        // Entity cannot be loaded, it must have been deleted.
        // Delete it from the tracking table too.
        $this->publisherTracker->delete($enqueue_entity['entity_uuid']);
        $this->logger()->warning(dt('Could not load entity (@entity_type, @entity_id) : "@entity_uuid". Deleted from Tracking Table.', [
          '@entity_type' => $enqueue_entity['entity_type'],
          '@entity_id' => $enqueue_entity['entity_id'],
          '@entity_uuid' => $enqueue_entity['entity_uuid'],
        ]));
        continue;
      }

      if ($track && $bundle && $bundle !== $entity->bundle()) {
        continue;
      }
      _acquia_contenthub_publisher_enqueue_entity($entity, 'update');
      $count++;

    }
    $this->logger()->success(dt('Processed @count "queued" entities for export.', [
      '@count' => $count,
    ]));

    return self::EXIT_SUCCESS;
  }

  /**
   * Requeue entities without using the Tracking Table.
   *
   * @param string $entity_type
   *   The entity type.
   * @param string|null $bundle
   *   The entity bundle.
   * @param array $data
   *   The data array.
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   A storage instance.
   *
   * @return int
   *   The exit code.
   *
   * @throws \Exception
   */
  protected function enqueueWithoutTrackingTable(string $entity_type, ?string $bundle, array $data, EntityStorageInterface $storage):int {
    // We are going to re-queue ALL entities that match a particular entity
    // type (and bundle) disregarding whether they have been previously
    // published or not.
    // Re-enqueue all entities.
    $entities = $storage->loadByProperties($data);
    if (empty($entities)) {
      $this->logger()->error(dt('No entities found for bundle @bundle.', [
        '@bundle' => $bundle,
      ]));
      return self::EXIT_FAILURE_WITH_CLARITY;
    }

    $count = 0;
    foreach ($entities as $entity) {
      _acquia_contenthub_publisher_enqueue_entity($entity, 'update');
      $count++;
    }

    $msg = !empty($bundle) ? "and bundle = \"{$bundle}\"." : '';
    $this->logger()->success(dt('Processed @count entities of type = @entity_type @msg for export.', [
      '@count' => $count,
      '@entity_type' => $entity_type,
      '@msg' => $msg,
    ]));
    return self::EXIT_SUCCESS;
  }

  /**
   * Enqueues by UUID.
   *
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The entity storage.
   * @param string $uuid
   *   The UUID.
   *
   * @return int
   *   The exit code.
   *
   * @throws \Exception
   */
  protected function enqueueByUuid(EntityStorageInterface $storage, string $uuid):int {

    if (!Uuid::isValid($uuid)) {
      $this->logger()->error(dt('Invalid UUID.'));
      return self::EXIT_FAILURE_WITH_CLARITY;
    }

    $entity = $storage->loadByProperties(['uuid' => $uuid]);
    $entity = reset($entity);

    if (!$entity instanceof EntityInterface) {
      $this->logger()->error(dt('Entity with UUID @uuid not found.', [
        '@uuid' => $uuid,
      ]));
      return self::EXIT_FAILURE_WITH_CLARITY;
    }

    // Clear the depcalc cache table for this UUID.
    $this->depcalcCache->delete($uuid);

    // We are nullifying hashes for this UUID.
    $this->publisherTracker->nullifyHashes([], [], [$uuid]);

    _acquia_contenthub_publisher_enqueue_entity($entity, 'update');

    $this->logger()->success(dt('Queued entity with UUID @uuid.', [
      '@uuid' => $uuid,
    ]));

    return self::EXIT_SUCCESS;
  }

}
