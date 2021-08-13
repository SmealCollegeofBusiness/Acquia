<?php

namespace Drupal\acquia_contenthub\EventSubscriber\ExcludeContentField;

use Drupal\acquia_contenthub\Event\ExcludeEntityFieldEvent;

/**
 * Subscribes to exclude path alias field.
 */
class RemovePathAliasField extends ExcludeContentFieldBase {

  /**
   * {@inheritdoc}
   */
  public static $priority = 60;

  /**
   * {@inheritDoc}
   */
  public function shouldExclude(ExcludeEntityFieldEvent $event): bool {
    // As we are not supporting Drupal version 8.7 and after 8.8 path alias
    // is an entity that's why prevent it from being added to the
    // serialized output.
    return $event->getEntity()->getEntityTypeId() !== 'path_alias' && $event->getFieldName() === 'path';
  }

}
