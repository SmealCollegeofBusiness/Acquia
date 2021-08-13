<?php

namespace Drupal\acquia_contenthub\EventSubscriber\ExcludeContentField;

use Drupal\acquia_contenthub\Event\ExcludeEntityFieldEvent;

/**
 * Subscribes to exclude entity revision field.
 */
class RemoveRevisionField extends ExcludeContentFieldBase {

  /**
   * {@inheritdoc}
   */
  public static $priority = 110;

  /**
   * {@inheritDoc}
   */
  public function shouldExclude(ExcludeEntityFieldEvent $event): bool {
    $entity_type = $event->getEntity()->getEntityType();
    return $event->getFieldName() === $entity_type->getKey('revision');
  }

}
