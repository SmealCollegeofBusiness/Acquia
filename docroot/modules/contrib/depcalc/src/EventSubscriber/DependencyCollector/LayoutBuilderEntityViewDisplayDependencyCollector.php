<?php

namespace Drupal\depcalc\EventSubscriber\DependencyCollector;

use Drupal\depcalc\DependencyCalculatorEvents;
use Drupal\depcalc\Event\CalculateEntityDependenciesEvent;
use Drupal\depcalc\EventSubscriber\LayoutBuilderComponentDepencyCollector\LayoutBuilderDependencyCollectorBase;

/**
 * Subscribes to dependency collection to extract entities referenced on Layout Builder components.
 */
class LayoutBuilderEntityViewDisplayDependencyCollector extends LayoutBuilderDependencyCollectorBase {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[DependencyCalculatorEvents::CALCULATE_DEPENDENCIES][] = ['onCalculateDependencies'];
    return $events;
  }

  /**
   * Calculates the entities referenced in Layout Builder components.
   *
   * @param \Drupal\depcalc\Event\CalculateEntityDependenciesEvent $event
   *   The dependency calculation event.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function onCalculateDependencies(CalculateEntityDependenciesEvent $event) {
    if (!$this->layoutPluginManager) {
      return;
    }
    /** @var \Drupal\Core\Entity\Entity\EntityViewDisplay $entity */
    $entity = $event->getEntity();
    if ($entity->getEntityTypeId() !== 'entity_view_display') {
      return;
    }

    $sections = $entity->getThirdPartySetting('layout_builder', 'sections') ?? [];
    foreach ($sections as $section) {
      $this->addSectionDependencies($event, $section);
      $this->addComponentDependencies($event, $section->getComponents());
    }
  }

}
