<?php

namespace Drupal\acquia_contenthub\EventSubscriber\Cdf;

use Drupal\acquia_contenthub\AcquiaContentHubEvents;
use Drupal\acquia_contenthub\Event\ParseCdfEntityEvent;
use Drupal\acquia_contenthub\LayoutBuilder\LayoutBuilderDataHandlerTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Handles config entities for Layout Builder entities.
 */
class ConfigEntityLayoutBuilderHandler implements EventSubscriberInterface {

  use LayoutBuilderDataHandlerTrait;

  /**
   * ConfigEntityLayoutBuilderHandler constructor.
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
    $events[AcquiaContentHubEvents::PARSE_CDF][] = ['onParseCdf', 90];
    return $events;
  }

  /**
   * Handles layout builder data in 3rd party settings on entity_view_displays.
   *
   * @param \Drupal\acquia_contenthub\Event\ParseCdfEntityEvent $event
   *   The Parse CDF Entity Event.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function onParseCdf(ParseCdfEntityEvent $event) {
    /** @var \Drupal\Core\Entity\Display\EntityViewDisplayInterface $entity */
    $entity = $event->getEntity();
    if ($entity->getEntityTypeId() !== 'entity_view_display') {
      return;
    }
    $sections = $entity->getThirdPartySetting('layout_builder', 'sections', []);
    if ($sections) {
      $entity->setThirdPartySetting('layout_builder', 'sections', $this->unserializeSections($sections));
    }
  }

}
