<?php

namespace Drupal\depcalc\Event;

use Drupal\Core\Entity\EntityInterface;
use Drupal\layout_builder\SectionComponent;
use Symfony\Component\EventDispatcher\Event;

/**
 * The SectionComponentDependenciesEvent event.
 */
class SectionComponentDependenciesEvent extends Event {

  /**
   * The component for this event.
   *
   * @var \Drupal\layout_builder\SectionComponent
   */
  protected $component;

  /**
   * The entity dependencies for this event.
   *
   * @var \Drupal\Core\Entity\EntityInterface[]
   */
  protected $entityDependencies;

  /**
   * The module dependencies for this event.
   *
   * @var string[]
   */
  protected $moduleDependencies;

  /**
   * SectionComponentDependenciesEvent constructor.
   *
   * @param \Drupal\layout_builder\SectionComponent $component
   *   The section component.
   */
  public function __construct(SectionComponent $component) {
    $this->component = $component;
  }

  /**
   * Get the event component.
   *
   * @return \Drupal\layout_builder\SectionComponent
   *   The section component.
   */
  public function getComponent() {
    return $this->component;
  }

  /**
   * Get the entity dependencies for this event.
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   *   The entity dependencies.
   */
  public function getEntityDependencies() {
    return $this->entityDependencies ? : [];
  }

  /**
   * Get the module dependencies for this event.
   *
   * @return string[]
   *   The module dependencies.
   */
  public function getModuleDependencies() {
    return $this->moduleDependencies ? : [];
  }

  /**
   * Adds an entity as dependency.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   */
  public function addEntityDependency(EntityInterface $entity) {
    $this->entityDependencies[] = $entity;
  }

  /**
   * Adds a module as dependency.
   *
   * @param string $module
   *   The module.
   */
  public function addModuleDependency(string $module) {
    $this->moduleDependencies[] = $module;
  }
}
