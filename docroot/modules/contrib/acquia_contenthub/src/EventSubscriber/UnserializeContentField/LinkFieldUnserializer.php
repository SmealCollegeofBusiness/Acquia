<?php

namespace Drupal\acquia_contenthub\EventSubscriber\UnserializeContentField;

use Drupal\acquia_contenthub\AcquiaContentHubEvents;
use Drupal\acquia_contenthub\Event\UnserializeCdfEntityFieldEvent;
use Drupal\Core\Entity\EntityInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Link Field Unserializer.
 *
 * This class handles the unserialization of menu_link entities.
 */
class LinkFieldUnserializer implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[AcquiaContentHubEvents::UNSERIALIZE_CONTENT_ENTITY_FIELD] =
      ['onUnserializeContentField', 10];
    return $events;
  }

  /**
   * On unserialize field event function.
   *
   * Handles the unserialization of menu_link entities.
   *
   * @param \Drupal\acquia_contenthub\Event\UnserializeCdfEntityFieldEvent $event
   *   The unserialize event.
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function onUnserializeContentField(UnserializeCdfEntityFieldEvent $event): void {
    $meta = $event->getFieldMetadata();
    if ($meta['type'] !== 'link') {
      return;
    }

    $field = $event->getField();
    if (empty($field['value'])) {
      return;
    }

    $values = [];
    foreach ($field['value'] as $langcode => $field_values) {
      foreach ($field_values as $value) {
        $values[$langcode][$event->getFieldName()][] = $this->getUnserializedValue($value, $event);
      }
    }
    // Set updated event values.
    $event->setValue($values);
    $event->stopPropagation();
  }

  /**
   * Returns the unserialized link field array.
   *
   * @param array|null $value
   *   The value of the unserializeable link field.
   * @param \Drupal\acquia_contenthub\Event\UnserializeCdfEntityFieldEvent $event
   *   The event to get the data from.
   *
   * @return array
   *   The unserialized link field array.
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  protected function getUnserializedValue(?array $value, UnserializeCdfEntityFieldEvent $event): array {
    if ($value === NULL) {
      return [];
    }

    if ($value['uri_type'] === 'entity') {
      $value['uri'] = $this->constructEntityUri($value['uri'], $event);
    }

    if ($value['uri_type'] === 'internal') {
      $internal_type = $value['internal_type'] ?? '';

      if ($internal_type === 'internal_entity') {
        $entity = $this->getEntityFromDependencyStack($value['uri'], $event);
        $value['uri'] = $this->constructInternalUri($entity);
      }

      if ($internal_type !== '') {
        unset($value['internal_type']);
      }
    }

    unset($value['uri_type']);
    return $value;
  }

  /**
   * Constructs an entity uri based on the provided uuid using event data.
   *
   * @param string $uuid
   *   The uuid of the entity.
   * @param \Drupal\acquia_contenthub\Event\UnserializeCdfEntityFieldEvent $event
   *   The event to get the corresponding entity from.
   *
   * @return string
   *   The constructed uri.
   */
  protected function constructEntityUri(string $uuid, UnserializeCdfEntityFieldEvent $event): string {
    $entity = $this->getEntityFromDependencyStack($uuid, $event);
    return sprintf('entity:%s/%s', $entity->getEntityTypeId(), $entity->id());
  }

  /**
   * Returns an entity from the dependency stack by uuid.
   *
   * @param string $uuid
   *   The uuid of the entity.
   * @param \Drupal\acquia_contenthub\Event\UnserializeCdfEntityFieldEvent $event
   *   The event which holds a reference to the dependency stack.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The found entity.
   */
  protected function getEntityFromDependencyStack(string $uuid, UnserializeCdfEntityFieldEvent $event): EntityInterface {
    return $event->getStack()->getDependency($uuid)->getEntity();
  }

  /**
   * Returns the internal link of en entity.
   *
   * Format: internal:/<ENT_TYPE>/<ENT_ID>.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to resolve the internal link of.
   *
   * @return string
   *   The internal link.
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  protected function constructInternalUri(EntityInterface $entity): string {
    if ($entity->getEntityType()->hasLinkTemplate('canonical') && $entity->toUrl('canonical')->isRouted()) {
      return sprintf('internal:/%s', $entity->toUrl('canonical')->getInternalPath());
    }
    return sprintf('internal:/%s/%s', $entity->getEntityTypeId(), $entity->id());
  }

}
