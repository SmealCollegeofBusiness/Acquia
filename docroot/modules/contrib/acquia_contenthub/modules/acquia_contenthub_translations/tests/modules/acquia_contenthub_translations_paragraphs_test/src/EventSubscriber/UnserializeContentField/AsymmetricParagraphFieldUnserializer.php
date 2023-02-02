<?php

namespace Drupal\acquia_contenthub_translations_paragraphs_test\EventSubscriber\UnserializeContentField;

use Drupal\acquia_contenthub\Event\UnserializeCdfEntityFieldEvent;
use Drupal\acquia_contenthub\EventSubscriber\UnserializeContentField\EntityReferenceField;

/**
 * Asymmetric paragraph field unserializer fallback subscriber.
 */
class AsymmetricParagraphFieldUnserializer extends EntityReferenceField {

  /**
   * Extracts the target storage and retrieves the referenced entity.
   *
   * @param \Drupal\acquia_contenthub\Event\UnserializeCdfEntityFieldEvent $event
   *   The unserialize event.
   *
   * @throws \Exception
   */
  public function onUnserializeContentField(UnserializeCdfEntityFieldEvent $event) {
    $field = $event->getField();
    if (!in_array($event->getFieldMetadata()['type'], $this->fieldTypes)) {
      return;
    }
    if ($event->getFieldMetadata()['target'] !== 'paragraph') {
      parent::onUnserializeContentField($event);
      return;
    }
    $values = [];
    if (!empty($field['value'])) {
      foreach ($field['value'] as $langcode => $value) {
        if (!$value) {
          $values[$langcode][$event->getFieldName()] = [];
          continue;
        }
        if (!is_array($value)) {
          $entity = $this->getEntity($value, $event);
          if (!$entity) {
            $values[$langcode][$event->getFieldName()] = [];
            continue;
          }
          $values[$langcode][$event->getFieldName()] = $entity->id();
          // @todo handle single value ERR fields.
        }
        else {
          foreach ($value as $item) {
            $entity = $this->getEntity($item, $event);
            if (!$entity) {
              $values[$langcode][$event->getFieldName()][]['target_id'] = [];
              continue;
            }
            /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
            $values[$langcode][$event->getFieldName()][] = [
              'target_id' => $entity->id(),
              'target_revision_id' => $entity->getRevisionId(),
            ];
          }
        }
      }
    }
    $event->setValue($values);
    $event->stopPropagation();
  }

}
