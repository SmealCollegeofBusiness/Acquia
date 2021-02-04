<?php

namespace Drupal\acquia_contenthub\Plugin\Field;

use Drupal\Core\Field\FieldItemList;

/**
 * Generates null field item for sample data.
 *
 * @package Drupal\acquia_contenthub\Plugin\Field
 */
class AcquiaContentHubGenerateNullFieldItemList extends FieldItemList {

  /**
   * {@inheritdoc}
   */
  public function generateSampleItems($count = 1) {
    $values = array_fill(0, $count, NULL);
    $this->setValue($values);
  }

}
