<?php

namespace Drupal\depcalc;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\depcalc\Event\FilterDependencyCalculationFieldsEvent;

/**
 * Class FieldExtractor.
 */
class FieldExtractor {

  /**
   * Extract all fields in all translations that match our criteria.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   * @param callable $condition
   *   The condition.
   *
   * @return \Drupal\Core\Field\FieldItemListInterface[]
   *   The list of fields.
   */
  public static function getFieldsFromEntity(ContentEntityInterface $entity, callable $condition) {
    $fields = [];
    $languages = $entity->getTranslationLanguages();
    /**
     * @var string $field_name
     * @var \Drupal\Core\Field\FieldItemListInterface $field
     */
    foreach ($entity as $field_name => $field) {
      // Check if field definition type is a link.
      if ($condition($entity, $field_name, $field)) {
        // If the field is translatable get all translations of it.
        if ($field->getFieldDefinition()->isTranslatable()) {
          foreach ($languages as $language) {
            $translated = $entity->getTranslation($language->getId());
            $fields[] = $translated->get($field_name);
          }
        }
        else {
          $fields[] = $field;
        }
      }
    }
    if ($fields) {
      $event = new FilterDependencyCalculationFieldsEvent($entity, ...$fields);
      /** @var \Symfony\Component\EventDispatcher\EventDispatcherInterface $dispatcher */
      $dispatcher = \Drupal::service('event_dispatcher');
      $dispatcher->dispatch(DependencyCalculatorEvents::FILTER_FIELDS, $event);
      $fields = $event->getFields();
    }
    return $fields;
  }

}
