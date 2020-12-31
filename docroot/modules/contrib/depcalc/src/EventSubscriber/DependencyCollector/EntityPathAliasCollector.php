<?php

namespace Drupal\depcalc\EventSubscriber\DependencyCollector;

use Drupal\Core\Entity\EntityMalformedException;
use Drupal\Core\Entity\Exception\UndefinedLinkTemplateException;
use Drupal\depcalc\DependencyCalculatorEvents;
use Drupal\depcalc\DependentEntityWrapper;
use Drupal\depcalc\Event\CalculateEntityDependenciesEvent;

class EntityPathAliasCollector extends BaseDependencyCollector {

  public static function getSubscribedEvents() {
    $events = [];
    $events[DependencyCalculatorEvents::CALCULATE_DEPENDENCIES][] = ['onCalculateDependencies'];
    return $events;
  }

  public function onCalculateDependencies(CalculateEntityDependenciesEvent $event) {
    // @todo remove version condition once 8.7 is no longer supported.
    if ($event->getEntity()->getEntityTypeId() !== 'path_alias' && version_compare(\Drupal::VERSION, '8.8.0', '>=') && \Drupal::moduleHandler()->moduleExists('path_alias')) {
      $entity = $event->getEntity();
      try {
        $uri = "/{$entity->toUrl()->getInternalPath()}";
        /** @var \Drupal\path_alias\PathAliasStorage $storage */
        $storage = \Drupal::entityTypeManager()->getStorage('path_alias');
        $paths = $storage->loadByProperties(['path' => $uri]);
        if ($paths) {
          foreach ($paths as $path) {
            $path_wrapper = new DependentEntityWrapper($path);
            $path_wrapper->addDependency($event->getWrapper(), $event->getStack());
            $local_dependencies = [];
            $this->mergeDependencies($path_wrapper, $event->getStack(), $this->getCalculator()
              ->calculateDependencies($path_wrapper, $event->getStack(), $local_dependencies));
            $event->addDependency($path_wrapper);
          }
        }
      }
      catch (EntityMalformedException | UndefinedLinkTemplateException $e) {
        return;
      }
    }
  }

}
