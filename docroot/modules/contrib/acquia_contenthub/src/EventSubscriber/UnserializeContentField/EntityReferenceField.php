<?php

namespace Drupal\acquia_contenthub\EventSubscriber\UnserializeContentField;

use Drupal\acquia_contenthub\AcquiaContentHubEvents;
use Drupal\acquia_contenthub\Event\UnserializeCdfEntityFieldEvent;
use Drupal\Core\Logger\LoggerChannelInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Entity/image/file field reference handling.
 */
class EntityReferenceField implements EventSubscriberInterface {
  use FieldEntityDependencyTrait;

  /**
   * Field types to use.
   *
   * @var array
   */
  protected $fieldTypes = [
    'file',
    'entity_reference',
    'entity_reference_revisions',
  ];

  /**
   * Acquia ContentHub logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * EntityReferenceField constructor.
   *
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   Acquia ContentHub logger channel.
   */
  public function __construct(LoggerChannelInterface $logger) {
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[AcquiaContentHubEvents::UNSERIALIZE_CONTENT_ENTITY_FIELD] =
      ['onUnserializeContentField', 10];
    return $events;
  }

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
            $this->log($value);
            return;
          }
          $values[$langcode][$event->getFieldName()] = $entity->id();
          // @todo handle single value ERR fields.
        }
        else {
          foreach ($value as $delta => $item) {
            $entity = $this->getEntity($item, $event);
            if (!$entity) {
              $values[$langcode][$event->getFieldName()][]['target_id'] = [];
              $this->log($item);
              return;
            }
            if ($event->getFieldMetadata()['type'] == 'entity_reference_revisions') {
              /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
              $values[$langcode][$event->getFieldName()][] = [
                'target_id' => $entity->id(),
                'target_revision_id' => $entity->getRevisionId(),
              ];
            }
            else {
              $values[$langcode][$event->getFieldName()][]['target_id'] = $entity->id();
            }
          }
        }
      }
    }

    $event->setValue($values);
    $event->stopPropagation();
  }

  /**
   * Log exception.
   *
   * @param string $uuid
   *   Entity uuid.
   */
  protected function log(string $uuid): void {
    $this->logger->error('No entity found with uuid @uuid.', [
      '@uuid' => $uuid,
    ]);
  }

}
