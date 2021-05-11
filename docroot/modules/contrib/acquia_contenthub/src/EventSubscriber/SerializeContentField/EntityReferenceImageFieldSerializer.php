<?php

namespace Drupal\acquia_contenthub\EventSubscriber\SerializeContentField;

use Drupal\acquia_contenthub\AcquiaContentHubEvents;
use Drupal\acquia_contenthub\Event\SerializeCdfEntityFieldEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Subscribes to entity field serialization to handle entity references.
 */
class EntityReferenceImageFieldSerializer extends EntityReferenceFieldSerializer implements EventSubscriberInterface {

  /**
   * Use the image field type.
   *
   * @var array
   */
  protected $fieldTypes = ['image'];

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[AcquiaContentHubEvents::SERIALIZE_CONTENT_ENTITY_FIELD][] =
      ['onSerializeContentField', 98];
    return $events;
  }

  /**
   * Extract entity uuids as field values.
   *
   * @param \Drupal\acquia_contenthub\Event\SerializeCdfEntityFieldEvent $event
   *   The content entity field serialization event.
   *
   * @throws \Exception
   */
  public function onSerializeContentField(SerializeCdfEntityFieldEvent $event) {
    if (in_array($event->getField()->getFieldDefinition()->getType(), $this->fieldTypes)) {
      parent::onSerializeContentField($event);
      $values = $event->getFieldData();
      if (!empty($values['value'])) {
        $field_name = $event->getField()->getName();
        $entity = $event->getEntity();
        foreach ($values['value'] as $lang => $language_values) {
          $translation = $entity->getTranslation($lang);
          $field_translation = $translation->get($field_name);
          foreach ($language_values as $delta => $value) {
            $values['value'][$lang][$delta] = ['target_id' => $value];
            $values['value'][$lang][$delta] += $field_translation[$delta]->getValue();
          }
        }
      }
      $event->setFieldData($values);
    }
  }

}
