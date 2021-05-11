<?php

namespace Drupal\acquia_contenthub\EventSubscriber\PreEntitySave;

use Drupal\acquia_contenthub\AcquiaContentHubEvents;
use Drupal\acquia_contenthub\Event\PreEntitySaveEvent;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Disables path auto during pre-entity save.
 *
 * @package Drupal\acquia_contenthub\EventSubscriber\PreEntitySave
 */
class DisablePathauto implements EventSubscriberInterface {

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $handler;

  /**
   * DisablePathauto constructor.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $handler
   *   The module handler.
   */
  public function __construct(ModuleHandlerInterface $handler) {
    $this->handler = $handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[AcquiaContentHubEvents::PRE_ENTITY_SAVE] = 'onPreEntitySave';
    return $events;
  }

  /**
   * Turn off pathauto alias generation for entities being imported.
   *
   * @param \Drupal\acquia_contenthub\PreEntitySaveEvent $event
   *   The pre entity save event.
   */
  public function onPreEntitySave(PreEntitySaveEvent $event) {
    $entity = $event->getEntity();
    if ($this->handler->moduleExists('pathauto') && $entity instanceof ContentEntityInterface && $entity->hasField('path')) {
      $entity->path->pathauto = 0;
    }
  }

}
