<?php

namespace Drupal\depcalc;

/**
 * Defines events for the depcalc module.
 *
 * @see \Drupal\depcalc\Event\CalculateEntityDependenciesEvent
 */
final class DependencyCalculatorEvents {

  /**
   * Name of the event fired when an entity's dependencies are calculated.
   *
   * This event allows modules to collaborate on entity dependency calculation.
   * The event listener method receives a
   * \Drupal\depcalc\Event\CalculateEntityDependenciesEvent instance.
   *
   * @Event
   *
   * @see \Drupal\depcalc\Event\CalculateEntityDependenciesEvent
   * @see \Drupal\depcalc\DependencyCalculator::calculateDependencies
   *
   * @var string
   */
  const CALCULATE_DEPENDENCIES = "calculate_dependencies";

  /**
   * The event fired against isolated fields for dependency calculation.
   *
   * The event listener method receives a
   * \Drupal\depcalc\Event\FilterDependencyCalculationFieldsEvent instance.
   *
   * @Event
   *
   * @see \Drupal\depcalc\Event\FilterDependencyCalculationFieldsEvent
   * @see \Drupal\depcalc\FieldExtractor::getFieldsFromEntity
   */
  const FILTER_FIELDS = "depcalc_filter_fields";

  /**
   * The event fired against config entities for dependency calculation.
   *
   * The event listener method receives a
   * \Drupal\depcalc\Event\FilterDependencyConfigEntityEvent instance.
   *
   * @Event
   *
   * @see \Drupal\depcalc\Event\FilterDependencyConfigEntityEvent
   * @see \Drupal\depcalc\EventSubscriber\DependencyCollector\ConfigEntityDependencyCollector::onCalculateDependencies
   */
  const FILTER_CONFIG_ENTITIES = "depcalc_filter_config_entities";

  /**
   * Name of the event fired when dependencies from a Layout Builder component are calculated.
   *
   * The event listener method receives a
   * \Drupal\depcalc\Event\CalculateLayoutBuilderComponentDependenciesEvent instance.
   *
   * @Event
   *
   * @see \Drupal\depcalc\Event\SectionComponentDependenciesEvent
   * @see \Drupal\depcalc\EventSubscriber\DependencyCollector\LayoutBuilderFieldDependencyCollector
   *
   * @var string
   */
  const SECTION_COMPONENT_DEPENDENCIES_EVENT = "section_component_dependencies_event";

}
