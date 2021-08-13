<?php

namespace Drupal\acquia_contenthub\EventSubscriber\ExcludeContentField;

use Drupal\acquia_contenthub\Event\ExcludeEntityFieldEvent;

/**
 * Subscribes to entity field serialization to handle language reference.
 */
class RemoveLanguageField extends ExcludeContentFieldBase {

  /**
   * {@inheritdoc}
   */
  public static $priority = 10;

  /**
   * {@inheritDoc}
   */
  public function shouldExclude(ExcludeEntityFieldEvent $event): bool {
    if ('language' !== $event->getField()->getFieldDefinition()->getType()) {
      return FALSE;
    }

    // Do not syndicate the "langcode" entity type key because Drupal will do
    // its own determination of things like "default_langcode" if values are
    // present in that field.
    return $event->getFieldName() === $event->getEntity()->getEntityType()->getKey('langcode');
  }

}
