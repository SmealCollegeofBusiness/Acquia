<?php

namespace Drupal\acquia_contenthub\Libs\Depcalc;

use Drupal\acquia_contenthub_publisher\PublisherTracker;
use Drupal\acquia_contenthub_subscriber\SubscriberTracker;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\depcalc\DependencyCalculator;
use Drupal\depcalc\DependencyStack;
use Drupal\depcalc\DependentEntityWrapper;

/**
 * Provides a method to rebuild depcalc cache based on the context.
 */
trait DepcalcCacheRebuildTrait {

  /**
   * Rebuilds depcalc cache using the tracking table data.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Exception
   */
  public function rebuildDepalcCache(): void {
    $entity_repository = $this->getEntityRepository();
    $depcalc = $this->getDepcalc();
    $entities = $this->getTrackedEntitiesFromContext();
    $stack = new DependencyStack();
    foreach ($entities as $entity) {
      $entity = $entity_repository->loadEntityByUuid($entity['entity_type'], $entity['entity_uuid']);
      $wrapper = new DependentEntityWrapper($entity);
      $depcalc->calculateDependencies($wrapper, $stack);
    }
  }

  /**
   * Returns the entity repository service.
   *
   * @return \Drupal\Core\Entity\EntityRepositoryInterface
   *   The service instance.
   */
  protected function getEntityRepository(): EntityRepositoryInterface {
    return \Drupal::service('entity.repository');
  }

  /**
   * Returns the dependency calculator service.
   *
   * @return \Drupal\depcalc\DependencyCalculator
   *   The service instance.
   */
  protected function getDepcalc(): DependencyCalculator {
    return \Drupal::service('entity.dependency.calculator');
  }

  /**
   * Returns tracked entities based on the site's role.
   *
   * @todo Refactor trackers, use a common interface.
   *
   * @return array
   *   The array of tracked entities.
   */
  protected function getTrackedEntitiesFromContext(): array {
    $entities = [];
    if (\Drupal::hasService('acquia_contenthub_publisher.tracker')) {
      $tracker = \Drupal::service('acquia_contenthub_publisher.tracker');
      $entities = $tracker->listTrackedEntities([
        PublisherTracker::CONFIRMED,
        PublisherTracker::EXPORTED,
      ]);
    }

    if (\Drupal::hasService('acquia_contenthub_subscriber.tracker')) {
      $tracker = \Drupal::service('acquia_contenthub_subscriber.tracker');
      $entities = array_merge($tracker->listTrackedEntities([
        SubscriberTracker::IMPORTED,
      ]), $entities);
    }

    return $entities;
  }

}
