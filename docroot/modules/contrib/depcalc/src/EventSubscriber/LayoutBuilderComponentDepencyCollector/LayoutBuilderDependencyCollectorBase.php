<?php

namespace Drupal\depcalc\EventSubscriber\LayoutBuilderComponentDepencyCollector;

use Drupal\Core\Layout\LayoutPluginManagerInterface;
use Drupal\depcalc\DependencyCalculatorEvents;
use Drupal\depcalc\DependentEntityWrapper;
use Drupal\depcalc\Event\CalculateEntityDependenciesEvent;
use Drupal\depcalc\Event\SectionComponentDependenciesEvent;
use Drupal\depcalc\EventSubscriber\DependencyCollector\BaseDependencyCollector;
use Drupal\layout_builder\Section;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

abstract class LayoutBuilderDependencyCollectorBase extends BaseDependencyCollector {

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $dispatcher;

  /**
   * The layout plugin manager.
   *
   * @var \Drupal\Core\Layout\LayoutPluginManager
   */
  protected $layoutPluginManager;

  /**
   * The LayoutBuilderDependencyCollectorBase constructor.
   *
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $dispatcher
   *   The event dispatcher.
   * @param \Drupal\Core\Layout\LayoutPluginManagerInterface|null $layoutPluginManager
   *   The LayoutPluginManager
   */
  public function __construct(EventDispatcherInterface $dispatcher, LayoutPluginManagerInterface $layoutPluginManager = NULL) {
    $this->dispatcher = $dispatcher;
    $this->layoutPluginManager = $layoutPluginManager;
  }

  /**
   * Set module dependencies to the event.
   *
   * @param \Drupal\depcalc\Event\CalculateEntityDependenciesEvent $event
   *   The calculate entity dependencies event.
   * @param \Drupal\layout_builder\Section $section
   *   The section.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function addSectionDependencies(CalculateEntityDependenciesEvent $event, Section $section) {
    $layout_id = $section->getLayoutId();
    $layout_plugin_definition = $this->layoutPluginManager->getDefinition($layout_id);
    $event->setModuleDependencies([$layout_plugin_definition->getProvider()]);
  }

  /**
   * Adds dependencies from components.
   *
   * @param \Drupal\depcalc\Event\CalculateEntityDependenciesEvent $event
   *   The calculate entity dependencies event.
   * @param \Drupal\layout_builder\SectionComponent[] $components
   *   The components for this field.
   *
   * @throws \Exception
   */
  protected function addComponentDependencies(CalculateEntityDependenciesEvent $event, array $components) {
    foreach ($components as $component) {
      $componentEvent = new SectionComponentDependenciesEvent($component);
      $this->dispatcher->dispatch(DependencyCalculatorEvents::SECTION_COMPONENT_DEPENDENCIES_EVENT, $componentEvent);
      $this->addSectionComponentEntityDependencies($event, $componentEvent->getEntityDependencies());
      $event->setModuleDependencies($componentEvent->getModuleDependencies());
    }
  }

  /**
   * Adds entity dependencies from this layout builder field to this event
   * dependencies.
   *
   * @param \Drupal\depcalc\Event\CalculateEntityDependenciesEvent $event
   *   The calculate entity dependencies event.
   * @param \Drupal\Core\Entity\EntityInterface[] $entities
   *   The entity dependencies.
   *
   * @throws \Exception
   */
  protected function addSectionComponentEntityDependencies(CalculateEntityDependenciesEvent $event, array $entities) {
    foreach ($entities as $entity) {
      $item_entity_wrapper = new DependentEntityWrapper($entity);
      $local_dependencies = [];
      $this->mergeDependencies($item_entity_wrapper, $event->getStack(), $this->getCalculator()->calculateDependencies($item_entity_wrapper, $event->getStack(), $local_dependencies));
      $event->addDependency($item_entity_wrapper);
    }
  }

}
