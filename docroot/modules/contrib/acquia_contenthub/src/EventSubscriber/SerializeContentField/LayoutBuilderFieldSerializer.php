<?php

namespace Drupal\acquia_contenthub\EventSubscriber\SerializeContentField;

use Drupal\acquia_contenthub\AcquiaContentHubEvents;
use Drupal\acquia_contenthub\Event\SerializeCdfEntityFieldEvent;
use Drupal\acquia_contenthub\LayoutBuilder\LayoutBuilderDataHandlerTrait;
use Drupal\Core\Entity\EntityTypeManager;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Subscribes to entity field serialization to handle layout builder fields.
 */
class LayoutBuilderFieldSerializer implements EventSubscriberInterface {

  use ContentFieldMetadataTrait;
  use LayoutBuilderDataHandlerTrait;

  const FIELD_TYPE = 'layout_section';

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * LayoutBuilderFieldSerializer constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManager $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManager $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[AcquiaContentHubEvents::SERIALIZE_CONTENT_ENTITY_FIELD][] = ['onSerializeContentField'];
    return $events;
  }

  /**
   * Prepare layout builder field.
   *
   * @param \Drupal\acquia_contenthub\Event\SerializeCdfEntityFieldEvent $event
   *   The content entity field serialization event.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function onSerializeContentField(SerializeCdfEntityFieldEvent $event) {
    $event_field_type = $event->getField()->getFieldDefinition()->getType();
    if ($event_field_type !== self::FIELD_TYPE) {
      return;
    }

    $this->setFieldMetaData($event);
    $data = [];
    /** @var \Drupal\Core\Entity\TranslatableInterface $entity */
    $entity = $event->getEntity();
    foreach ($entity->getTranslationLanguages() as $langcode => $language) {
      $field = $event->getFieldTranslation($langcode);

      if ($field->isEmpty()) {
        $data['value'][$langcode] = [];
        continue;
      }

      $sections = [];
      foreach ($field as $item) {
        $sections[] = $item->getValue()['section'];
      }
      $sections = $this->serializeSections(... $sections);
      foreach ($sections as $section) {
        $data['value'][$langcode][] = ['section' => $section];
      }
    }
    $event->setFieldData($data);
    $event->stopPropagation();
  }

}
