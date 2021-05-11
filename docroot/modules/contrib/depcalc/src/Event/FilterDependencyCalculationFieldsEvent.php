<?php

namespace Drupal\depcalc\Event;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Symfony\Component\EventDispatcher\Event;

/**
 * The FilterDependencyCalculationFieldsEvent event.
 */
class FilterDependencyCalculationFieldsEvent extends Event {

  /**
   * The entity to calculate dependencies.
   *
   * @var \Drupal\Core\Entity\ContentEntityInterface
   */
  protected $entity;

  /**
   * The entity fields.
   *
   * @var \Drupal\Core\Field\FieldItemListInterface[]
   */
  protected $fields;

  /**
   * FilterDependencyCalculationFieldsEvent constructor.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   * @param \Drupal\Core\Field\FieldItemListInterface ...$fields
   *   The fields.
   */
  public function __construct(ContentEntityInterface $entity, FieldItemListInterface ...$fields) {
    $this->entity = $entity;
    $this->fields = $fields;
  }

  /**
   * Retrieve the entity object
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   The entity.
   */
  public function getEntity() {
    return $this->entity;
  }

  /**
   * Get the fields to be filtered.
   *
   * @return \Drupal\Core\Field\FieldItemListInterface[]
   *   The fields.
   */
  public function getFields() {
    return $this->fields;
  }

  /**
   * Set a filtered list of fields.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface ...$fields
   *   The fields.
   */
  public function setFields(FieldItemListInterface ...$fields) {
    $this->fields = $fields;
  }

}
