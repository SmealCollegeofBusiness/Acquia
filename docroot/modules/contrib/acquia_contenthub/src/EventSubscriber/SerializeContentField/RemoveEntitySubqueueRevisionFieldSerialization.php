<?php

namespace Drupal\acquia_contenthub\EventSubscriber\SerializeContentField;

use Drupal\acquia_contenthub\AcquiaContentHubEvents;
use Drupal\acquia_contenthub\Event\SerializeCdfEntityFieldEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Subscribes to entity subqueue serialization to only remove entity revision.
 */
class RemoveEntitySubqueueRevisionFieldSerialization implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[AcquiaContentHubEvents::SERIALIZE_CONTENT_ENTITY_FIELD][] =
      ['onSerializeContentField', 120];
    return $events;
  }

  /**
   * Prevent entity revision from being added to the serialized output.
   *
   * @param \Drupal\acquia_contenthub\Event\SerializeCdfEntityFieldEvent $event
   *   The content entity field serialization event.
   */
  public function onSerializeContentField(SerializeCdfEntityFieldEvent $event) {
    if ($event->getEntity()->getEntityTypeId() !== 'entity_subqueue') {
      return;
    }

    // In case of entity subqueue, only exclude the "revision_id" field
    // (and not the "id" as id is required for entity subqueue import) and
    // prevent it from being added to the serialized output.
    $entity_type = $event->getEntity()->getEntityType();
    if ($event->getFieldName() === $entity_type->getKey('revision')) {
      $event->setExcluded();
      $event->stopPropagation();
    }
  }

}
