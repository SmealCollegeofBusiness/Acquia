<?php

namespace Drupal\entity_clone;

use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\field\FieldConfigInterface;


/**
 * Manage entity clone clonable field.
 */
class EntityCloneClonableField {

  /**
   *
   * Return whether or not a field is clonable
   *
   * @param $field_definition
   *  The field definition
   * @param $field
   *  The field
   *
   * @return bool
   */
  public function isClonable($field_definition, $field){
    return $field_definition instanceof FieldConfigInterface && $field instanceof EntityReferenceFieldItemListInterface && $field->count() > 0;
  }

}
