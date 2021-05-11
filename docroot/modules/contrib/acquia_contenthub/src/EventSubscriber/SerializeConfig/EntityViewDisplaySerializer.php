<?php

namespace Drupal\acquia_contenthub\EventSubscriber\SerializeConfig;

use Drupal\acquia_contenthub\AcquiaContentHubEvents;
use Drupal\acquia_contenthub\Event\ConfigDataEvent;
use Drupal\acquia_contenthub\LayoutBuilder\LayoutBuilderDataHandlerTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Serializer for entity view displays.
 */
class EntityViewDisplaySerializer implements EventSubscriberInterface {

  use LayoutBuilderDataHandlerTrait;

  /**
   * EntityViewDisplaySerializer constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = [];
    $events[AcquiaContentHubEvents::SERIALIZE_CONFIG_ENTITY][] =
      ['onSerializeConfigEntity', 100];
    return $events;
  }

  /**
   * Serializes layout_builder 3rd party settings data on entity_view_displays.
   *
   * @param \Drupal\acquia_contenthub\Event\ConfigDataEvent $event
   *   The config data event object.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function onSerializeConfigEntity(ConfigDataEvent $event) {
    /** @var \Drupal\Core\Entity\Entity\EntityViewDisplay $entity */
    $entity = $event->getEntity();
    if ($entity->getEntityTypeId() !== 'entity_view_display') {
      return;
    }

    $sections = $entity->getThirdPartySetting('layout_builder', 'sections') ?? [];
    if ($sections) {
      $data = $event->getData();
      $data[$entity->language()->getId()]['third_party_settings']['layout_builder']['sections'] = $this->serializeSections(... $sections);
      $event->setData($data);
    }
  }

}
