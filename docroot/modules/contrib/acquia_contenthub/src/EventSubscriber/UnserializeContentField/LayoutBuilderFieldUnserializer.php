<?php

namespace Drupal\acquia_contenthub\EventSubscriber\UnserializeContentField;

use Drupal\acquia_contenthub\AcquiaContentHubEvents;
use Drupal\acquia_contenthub\Event\UnserializeCdfEntityFieldEvent;
use Drupal\acquia_contenthub\LayoutBuilder\LayoutBuilderDataHandlerTrait;
use Drupal\Core\Entity\EntityTypeManager;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Layout builder field unserializer fallback subscriber.
 */
class LayoutBuilderFieldUnserializer implements EventSubscriberInterface {

  use LayoutBuilderDataHandlerTrait;

  /**
   * Layout section field type definition.
   *
   * @var string
   */
  protected $fieldType = 'layout_section';

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
    $events[AcquiaContentHubEvents::UNSERIALIZE_CONTENT_ENTITY_FIELD] = ['onUnserializeContentField'];
    return $events;
  }

  /**
   * Handling for Layout Builder sections.
   *
   * @param \Drupal\acquia_contenthub\Event\UnserializeCdfEntityFieldEvent $event
   *   The unserialize event.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function onUnserializeContentField(UnserializeCdfEntityFieldEvent $event) {
    $event_field_type = $event->getFieldMetadata()['type'];
    if ($event_field_type !== $this->fieldType) {
      return;
    }

    $field = $event->getField();
    $values = [];
    if (!empty($field['value'])) {
      foreach ($field['value'] as $langcode => $sections) {
        foreach ($this->unserializeSections($sections) as $section) {
          $values[$langcode][$event->getFieldName()][] = ['section' => $section];
        }

      }
      $event->setValue($values);
    }
    $event->stopPropagation();
  }

}
