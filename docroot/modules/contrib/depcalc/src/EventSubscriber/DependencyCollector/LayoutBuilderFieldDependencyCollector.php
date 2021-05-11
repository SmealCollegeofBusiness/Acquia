<?php

namespace Drupal\depcalc\EventSubscriber\DependencyCollector;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\depcalc\DependencyCalculatorEvents;
use Drupal\depcalc\Event\CalculateEntityDependenciesEvent;
use Drupal\depcalc\EventSubscriber\LayoutBuilderComponentDepencyCollector\LayoutBuilderDependencyCollectorBase;
use Drupal\depcalc\FieldExtractor;

/**
 * Subscribes to dependency collection to extract entities referenced on Layout Builder components.
 */
class LayoutBuilderFieldDependencyCollector extends LayoutBuilderDependencyCollectorBase {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[DependencyCalculatorEvents::CALCULATE_DEPENDENCIES][] = ['onCalculateDependencies'];
    return $events;
  }

  /**
   * Calculates the entities referenced on Layout Builder components.
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
    $entity = $event->getEntity();
    if (!$entity instanceof ContentEntityInterface) {
      return;
    }

    $fields = FieldExtractor::getFieldsFromEntity($entity, [$this, 'fieldCondition']);
    foreach ($fields as $field) {
      foreach ($field as $item) {
        $section = $item->getValue()['section'];
        $this->addSectionDependencies($event, $section);
        $this->addComponentDependencies($event, $section->getComponents());
      }
    }
  }

  /**
   * Determines if the field is of one of the specified types.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   * @param string $field_name
   *   The field name.
   * @param \Drupal\Core\Field\FieldItemListInterface $field
   *   The field.
   *
   * @return bool
   *   Whether the field type is one of the specified ones.
   */
  public function fieldCondition(ContentEntityInterface $entity, $field_name, FieldItemListInterface $field) {
    return in_array($field->getFieldDefinition()->getType(), [
      'layout_section'
    ]);
  }

}
