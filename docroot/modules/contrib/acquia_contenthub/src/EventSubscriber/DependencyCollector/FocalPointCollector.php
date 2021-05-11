<?php

namespace Drupal\acquia_contenthub\EventSubscriber\DependencyCollector;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\crop\Entity\Crop;
use Drupal\depcalc\Event\CalculateEntityDependenciesEvent;
use Drupal\depcalc\EventSubscriber\DependencyCollector\EmbeddedImagesCollector;

/**
 * Add focal point dependencies.
 *
 * @package Drupal\acquia_contenthub\EventSubscriber\DependencyCollector
 */
class FocalPointCollector extends EmbeddedImagesCollector {

  /**
   * Reacts on CALCULATE_DEPENDENCIES event.
   *
   * @param \Drupal\depcalc\Event\CalculateEntityDependenciesEvent $event
   *   Event.
   *
   * @throws \Exception
   */
  public function onCalculateDependencies(CalculateEntityDependenciesEvent $event) {
    if (!$this->moduleHandler->moduleExists('focal_point')) {
      return;
    }
    elseif (!$this->moduleHandler->moduleExists('file')) {
      return;
    }

    $entity = $event->getEntity();

    if (FALSE === ($entity instanceof ContentEntityInterface)) {
      return;
    }

    $files = $this->getAttachedFiles($entity);
    foreach ($files as $file) {
      $find_crop = Crop::findCrop($file->getFileUri(), 'focal_point');
      if ($find_crop) {
        $crop = Crop::load($find_crop->id());
        $this->addDependency($event, $crop);
      }
    }
  }

}
