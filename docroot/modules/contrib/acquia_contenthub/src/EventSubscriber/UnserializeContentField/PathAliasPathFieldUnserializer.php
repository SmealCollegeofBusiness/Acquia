<?php

namespace Drupal\acquia_contenthub\EventSubscriber\UnserializeContentField;

use Drupal\acquia_contenthub\AcquiaContentHubEvents;
use Drupal\acquia_contenthub\Event\UnserializeCdfEntityFieldEvent;
use Drupal\Component\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Generic field unserializer fallback subscriber.
 */
class PathAliasPathFieldUnserializer implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[AcquiaContentHubEvents::UNSERIALIZE_CONTENT_ENTITY_FIELD] =
      ['onUnserializeContentField', 10];
    return $events;
  }

  /**
   * Handling for the PathAlias entity's 'path' field.
   *
   * @param \Drupal\acquia_contenthub\Event\UnserializeCdfEntityFieldEvent $event
   *   The unserialize event.
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function onUnserializeContentField(UnserializeCdfEntityFieldEvent $event) {
    if ($event->getEntityType()->id() === 'path_alias' && $event->getFieldName() === 'path') {
      $field = $event->getField();
      $values = [];
      foreach ($field['value'] as $langcode => $value) {
        if (empty($value['value']) || !Uuid::isValid($value['value'])) {
          continue;
        }
        if ($event->getStack()->hasDependency($value['value'])) {
          $wrapper = $event->getStack()->getDependency($value['value']);
          $path = "/{$wrapper->getEntity()->toUrl()->getInternalPath()}";
          $values[$langcode][$event->getFieldName()] = $path;
        }
      }
      $event->setValue($values);
      $event->stopPropagation();
    }
  }

}
