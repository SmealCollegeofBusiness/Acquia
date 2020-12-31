<?php

namespace Drupal\acquia_contenthub\EventSubscriber\SerializeContentField;

use Drupal\acquia_contenthub\AcquiaContentHubEvents;
use Drupal\acquia_contenthub\Event\SerializeCdfEntityFieldEvent;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Class PathFieldSerializer.
 *
 * @package Drupal\acquia_contenthub\EventSubscriber\SerializeContentField
 */
class PathFieldSerializer extends FallbackFieldSerializer implements EventSubscriberInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $manager;

  /**
   * PathFieldSerializer constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $manager) {
    $this->manager = $manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[AcquiaContentHubEvents::SERIALIZE_CONTENT_ENTITY_FIELD][] = ['onSerializeContentField', 11];
    return $events;
  }

  /**
   * Manipulate the path properties.
   *
   * @param \Drupal\acquia_contenthub\Event\SerializeCdfEntityFieldEvent $event
   *   The content entity field serialization event.
   */
  public function onSerializeContentField(SerializeCdfEntityFieldEvent $event) {
    if ($event->getEntity()->getEntityTypeId() === 'path_alias' && $event->getFieldName() === 'path') {
      parent::onSerializeContentField($event);
      $values = $event->getFieldData();
      $langcode = $event->getEntity()->language()->getId();
      $path = $values['value'][$langcode]['value'];
      $params = Url::fromUserInput($path)->getRouteParameters();
      if ($params) {
        foreach ($params as $key => $value) {
          if ($this->manager->hasDefinition($key)) {
            $entity = $this->manager->getStorage($key)->load($value);
            if ($path === "/{$entity->toUrl()->getInternalPath()}") {
              $values['value'][$langcode]['value'] = $entity->uuid();
              $event->setFieldData($values);
              $event->stopPropagation();
              return;
            }
          }
        }
      }
    }
    if ('path' !== $event->getField()->getFieldDefinition()->getType()) {
      return;
    }
    parent::onSerializeContentField($event);
    if (version_compare(\Drupal::VERSION, '8.8.0', '>=')) {
      $event->setExcluded();
      $event->stopPropagation();
    }
    // @todo remove this once we don't have to support 8.7.
    $values = $event->getFieldData();
    foreach ($values['value'] as $langcode => $value) {
      // @todo check core's behavior around empty paths.
      if (!empty($value['alias'])) {
        $value['source'] = '';
        $value['pid'] = '';
        $values['value'][$langcode] = $value;
        $event->setFieldData($values);
      }
    }
  }

}
