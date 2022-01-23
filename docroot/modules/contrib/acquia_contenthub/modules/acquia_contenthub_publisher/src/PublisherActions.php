<?php

namespace Drupal\acquia_contenthub_publisher;

use Drupal\acquia_contenthub\ContentHubCommonActions;
use Drupal\Component\Uuid\Uuid;
use Drupal\Core\Entity\EntityInterface;
use Drupal\depcalc\Cache\DepcalcCacheBackend;

/**
 * Provides basic publisher actions like re-export functionality.
 *
 * @package Drupal\acquia_contenthub_publisher
 */
class PublisherActions {

  /**
   * The Publisher Tracker.
   *
   * @var \Drupal\acquia_contenthub_publisher\PublisherTracker
   */
  protected $publisherTracker;

  /**
   * The Content Hub Common Actions Service.
   *
   * @var \Drupal\acquia_contenthub\ContentHubCommonActions
   */
  protected $commonActions;

  /**
   * The Depcalc Cache Backend.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $backend;

  /**
   * The Content Hub Entity Enqueuer.
   *
   * @var \Drupal\acquia_contenthub_publisher\ContentHubEntityEnqueuer
   */
  protected $entityEnqueuer;

  /**
   * PublisherActions constructor.
   *
   * @param \Drupal\acquia_contenthub_publisher\PublisherTracker $publisher_tracker
   *   The Publisher Tracker.
   * @param \Drupal\acquia_contenthub\ContentHubCommonActions $common_actions
   *   The Content Hub Common Actions Service.
   * @param \Drupal\depcalc\Cache\DepcalcCacheBackend $depcalc_cache
   *   The Depcalc Cache Backend.
   * @param \Drupal\acquia_contenthub_publisher\ContentHubEntityEnqueuer $entity_enqueuer
   *   The Content Hub Entity Enqueuer.
   */
  public function __construct(PublisherTracker $publisher_tracker, ContentHubCommonActions $common_actions, DepcalcCacheBackend $depcalc_cache, ContentHubEntityEnqueuer $entity_enqueuer) {
    $this->publisherTracker = $publisher_tracker;
    $this->commonActions = $common_actions;
    $this->backend = $depcalc_cache;
    $this->entityEnqueuer = $entity_enqueuer;
  }

  /**
   * Re-enqueues Entity by cleaning its dependencies and hashes.
   *
   * This method re-enqueues an entity by first making a calculation of all its
   * dependencies, then deleting the depcalc cache entries and nullifying the
   * hashes for all of them. It ensures that the export will be executed fresh
   * from the start.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to republish.
   * @param array $dependencies
   *   An array of entity dependency UUIDs.
   * @param string $op
   *   The operation to perform: 'insert' or 'update'.
   *
   * @throws \Exception
   */
  public function reExportEntityFull(EntityInterface $entity, array $dependencies = [], string $op = 'update') {
    if (!in_array($op, ['insert', 'update'])) {
      throw new \Exception(sprintf('Wrong operation provided (op = "%s") to re-queue entity %s/%s for export.', $op, $entity->getEntityTypeId(), $entity->id()));
    }

    // Recalculate entity dependencies, if not given.
    if (empty($dependencies) || !Uuid::isValid(reset($dependencies))) {
      $cdfs = $this->commonActions->getEntityCdfFullKeyedByUuids($entity);

      // Using array_keys() to only pass the dependency UUIDs, not the hashes.
      $dependencies = array_keys($cdfs[$entity->uuid()]->getDependencies());
    }

    // Deleting 'depcalc' cache entries for this entity and dependencies.
    $uuids = array_merge([$entity->uuid()], $dependencies);
    $this->backend->deleteMultiple($uuids);

    $this->publisherTracker->nullifyHashes([], [], $uuids);

    $this->entityEnqueuer->enqueueEntity($entity, $op);
  }

}
