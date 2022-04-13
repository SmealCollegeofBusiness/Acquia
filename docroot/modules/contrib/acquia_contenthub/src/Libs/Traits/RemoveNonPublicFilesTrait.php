<?php

namespace Drupal\acquia_contenthub\Libs\Traits;

use Drupal\acquia_contenthub\Plugin\FileSchemeHandler\FileSchemeHandlerManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;

/**
 * Helper trait to skip non-public files from depcalc and serialization.
 */
trait RemoveNonPublicFilesTrait {

  /**
   * Whether we should include this field in the dependency calculation.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $field
   *   The entity field.
   * @param \Drupal\acquia_contenthub\Plugin\FileSchemeHandler\FileSchemeHandlerManagerInterface $manager
   *   The file scheme handler manager.
   *
   * @return bool
   *   TRUE if we should include the field, FALSE otherwise.
   */
  protected function includeField(FieldItemListInterface $field, FileSchemeHandlerManagerInterface $manager) {
    $definition = $field->getFieldDefinition();
    if (!in_array($definition->getType(), ['file', 'image'], TRUE)) {
      return TRUE;
    }

    return $manager->hasDefinition($definition->getFieldStorageDefinition()->getSetting('uri_scheme'));
  }

}
