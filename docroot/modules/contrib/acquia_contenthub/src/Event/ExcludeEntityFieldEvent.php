<?php

namespace Drupal\acquia_contenthub\Event;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Event fired when serializing individual entity fields for syndication.
 *
 * Subscribers to this event will exclude entity fields for the
 * CDF serialization process.
 * $this->isExcludedField will prevent the key/value from being syndicated.
 *
 * @see \Drupal\acquia_contenthub\AcquiaContentHubEvents
 */
class ExcludeEntityFieldEvent extends Event {

  /**
   * The entity being serialized.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected $entity;

  /**
   * The name of the field being serialized.
   *
   * @var string
   */
  protected $fieldName;

  /**
   * The "exclude" flag.
   *
   * If set to TRUE, the field will not be added to the CDF object.
   *
   * @var bool
   */
  protected $isExcludedField = FALSE;

  /**
   * The field being serialized.
   *
   * @var \Drupal\Core\Field\FieldItemListInterface
   */
  protected $field;

  /**
   * SerializeCdfEntityFieldEvent constructor.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to which the field belongs.
   * @param string $field_name
   *   The name of the field.
   * @param \Drupal\Core\Field\FieldItemListInterface $field
   *   The field item list object.
   */
  public function __construct(EntityInterface $entity, string $field_name, FieldItemListInterface $field) {
    $this->entity = $entity;
    $this->fieldName = $field_name;
    $this->field = $field;
  }

  /**
   * Get the entity being processed.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The entity being processed.
   */
  public function getEntity(): EntityInterface {
    return $this->entity;
  }

  /**
   * The field item list object.
   *
   * @return \Drupal\Core\Field\FieldItemListInterface
   *   Field item list object.
   */
  public function getField(): FieldItemListInterface {
    return $this->field;
  }

  /**
   * The name of the field.
   *
   * @return string
   *   Field name.
   */
  public function getFieldName(): string {
    return $this->fieldName;
  }

  /**
   * Sets the "exclude" flag.
   */
  public function exclude() {
    $this->isExcludedField = TRUE;
  }

  /**
   * Returns the "exclude" flag state.
   *
   * @return bool
   *   "Exclude" flag state.
   */
  public function isExcluded(): bool {
    return $this->isExcludedField;
  }

}
