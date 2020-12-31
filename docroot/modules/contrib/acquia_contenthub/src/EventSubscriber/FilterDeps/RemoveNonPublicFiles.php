<?php

namespace Drupal\acquia_contenthub\EventSubscriber\FilterDeps;

use Drupal\acquia_contenthub\AcquiaContentHubEvents;
use Drupal\acquia_contenthub\Event\SerializeCdfEntityFieldEvent;
use Drupal\acquia_contenthub\Plugin\FileSchemeHandler\FileSchemeHandlerManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\depcalc\DependencyCalculatorEvents;
use Drupal\depcalc\Event\FilterDependencyCalculationFieldsEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Class RemoveNonPublicFiles.
 *
 * Removes dependency on non-public files.
 *
 * @package Drupal\acquia_contenthub\EventSubscriber\FilterDeps
 */
class RemoveNonPublicFiles implements EventSubscriberInterface {

  /**
   * The file scheme handler manager.
   *
   * @var \Drupal\acquia_contenthub\Plugin\FileSchemeHandler\FileSchemeHandlerManagerInterface
   */
  protected $manager;

  /**
   * RemoveNonPublicFiles constructor.
   *
   * @param \Drupal\acquia_contenthub\Plugin\FileSchemeHandler\FileSchemeHandlerManagerInterface $manager
   *   The file scheme handler manager service.
   */
  public function __construct(FileSchemeHandlerManagerInterface $manager) {
    $this->manager = $manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[DependencyCalculatorEvents::FILTER_FIELDS][] = [
      'onFilterFields',
      1001,
    ];
    $events[AcquiaContentHubEvents::SERIALIZE_CONTENT_ENTITY_FIELD][] = [
      'onSerializeContentField',
      1001,
    ];
    return $events;
  }

  /**
   * Filter fields.
   *
   * @param \Drupal\depcalc\Event\FilterDependencyCalculationFieldsEvent $event
   *   Filter Dependency Calculation Fields.
   */
  public function onFilterFields(FilterDependencyCalculationFieldsEvent $event) {
    $fields = array_filter($event->getFields(), function ($field) {
      return $this->includeField($field);
    }, ARRAY_FILTER_USE_BOTH);

    $event->setFields(...$fields);
  }

  /**
   * Serialize content field event.
   *
   * @param \Drupal\acquia_contenthub\Event\SerializeCdfEntityFieldEvent $event
   *   Serialized CDF Entity Field event.
   */
  public function onSerializeContentField(SerializeCdfEntityFieldEvent $event) {
    $field = $event->getField();
    if (!$this->includeField($field)) {
      $event->setExcluded();
      $event->stopPropagation();
    }
  }

  /**
   * Whether we should include this field in the dependency calculation.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $field
   *   The entity field.
   *
   * @return bool
   *   TRUE if we should include the field, FALSE otherwise.
   */
  protected function includeField(FieldItemListInterface $field) {
    $definition = $field->getFieldDefinition();
    if (!in_array($definition->getType(), ['file', 'image'], TRUE)) {
      return TRUE;
    }

    return $this->manager->hasDefinition($definition->getFieldStorageDefinition()->getSetting('uri_scheme'));
  }

}
