<?php

namespace Drupal\depcalc\EventSubscriber\DependencyCollector;

use Drupal\depcalc\DependencyCalculatorEvents;
use Drupal\depcalc\DependentEntityWrapper;
use Drupal\depcalc\Event\CalculateEntityDependenciesEvent;

class MenuItemContentDependencyCollector extends BaseDependencyCollector {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[DependencyCalculatorEvents::CALCULATE_DEPENDENCIES][] = ['onCalculateDependencies'];
    return $events;
  }

  public function onCalculateDependencies(CalculateEntityDependenciesEvent $event) {
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $event->getEntity();
    if ($entity->getEntityTypeId() !== 'menu_link_content') {
      return;
    }
    $event->getWrapper()->addModuleDependencies(['menu', 'menu_link_content']);
    $menu = $entity->get('menu_name')->first()->getValue()['value'];
    $menu = \Drupal::entityTypeManager()->getStorage('menu')->load($menu);
    if ($menu && !$event->getStack()->hasDependency($menu->uuid())) {
      $menu_wrapper = new DependentEntityWrapper($menu);
      $local_dependencies = [];
      $this->mergeDependencies($menu_wrapper, $event->getStack(), $this->getCalculator()
        ->calculateDependencies($menu_wrapper, $event->getStack(), $local_dependencies));
      $event->addDependency($menu_wrapper);
    }
    $parent = $entity->get('parent')->first() ? $entity->get('parent')->first()->getValue()['value'] : '';
    if (!$parent) {
      return;
    }
    [$parent_type, $uuid] = explode(':', $parent);
    /** @var \Drupal\Core\Entity\EntityInterface $parent_menu */
    $parent_menu = \Drupal::service('entity.repository')->loadEntityByUuid($parent_type, $uuid);
    if ($parent_menu && !$event->getStack()->hasDependency($parent_menu->uuid())) {
      $parent_wrapper = new DependentEntityWrapper($parent_menu);
      $local_dependencies = [];
      $this->mergeDependencies($parent_wrapper, $event->getStack(), $this->getCalculator()
        ->calculateDependencies($parent_wrapper, $event->getStack(), $local_dependencies));
      $event->addDependency($parent_wrapper);

      // Child menu_link_content entity's dependencies already calculated.
      // Adding parent is sufficient.
    }
  }

}
