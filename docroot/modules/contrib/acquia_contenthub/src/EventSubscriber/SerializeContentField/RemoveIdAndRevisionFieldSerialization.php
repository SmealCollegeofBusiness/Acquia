<?php

namespace Drupal\acquia_contenthub\EventSubscriber\SerializeContentField;

use Drupal\acquia_contenthub\AcquiaContentHubEvents;
use Drupal\acquia_contenthub\Event\SerializeCdfEntityFieldEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Subscribes to entity field serialization to remove entity id and revision.
 */
class RemoveIdAndRevisionFieldSerialization implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[AcquiaContentHubEvents::SERIALIZE_CONTENT_ENTITY_FIELD][] =
      ['onSerializeContentField', 110];
    return $events;
  }

  /**
   * Prevent entity id and revision from being added to the serialized output.
   *
   * @param \Drupal\acquia_contenthub\Event\SerializeCdfEntityFieldEvent $event
   *   The content entity field serialization event.
   */
  public function onSerializeContentField(SerializeCdfEntityFieldEvent $event) {
    $entity = $event->getEntity();
    $entityType = $entity->getEntityType();
    $entityTypes = [
      $entityType->getKey('id'),
      $entityType->getKey('revision'),
    ];

    if (in_array($event->getFieldName(), $entityTypes)) {
      $event->setExcluded();
      $event->stopPropagation();
    }
  }

}
