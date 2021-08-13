<?php

namespace Drupal\acquia_contenthub_subscriber\EventSubscriber\PreEntitySave;

use Drupal\acquia_contenthub\AcquiaContentHubEvents;
use Drupal\acquia_contenthub\Event\PreEntitySaveEvent;
use Drupal\Core\Database\Connection;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Handles entity subqueue import failure.
 *
 * @package Drupal\acquia_contenthub_subscriber\EventSubscriber\PreEntitySave
 */
class EntitySubqueuePreSave implements EventSubscriberInterface {

  /**
   * The entity subqueue.
   *
   * @var string
   */
  const ENTITY_SUBQUEUE = 'entity_subqueue';

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * EntitySubqueuePreSave constructor.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   Database connection.
   */
  public function __construct(Connection $database) {
    // Using \Drupal::entityTypeManager() do to caching of the instance in
    // some services. Looks like a core bug.
    $this->entityTypeManager = \Drupal::entityTypeManager();
    $this->database = $database;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[AcquiaContentHubEvents::PRE_ENTITY_SAVE] = ['onPreEntitySave', 80];
    return $events;
  }

  /**
   * Deletes entity subqueue if it's already created with diff UUID.
   *
   * @param \Drupal\acquia_contenthub\Event\PreEntitySaveEvent $event
   *   The pre entity save event.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function onPreEntitySave(PreEntitySaveEvent $event) {
    if ($event->getEntity()->getEntityTypeId() !== self::ENTITY_SUBQUEUE) {
      return;
    }

    $uuid = $event->getEntity()->uuid();
    $subqueue_id = $event->getEntity()->id();

    $query = $this->database->select(self::ENTITY_SUBQUEUE, 'es');
    $query->fields('es', ['uuid']);
    $query->condition('name', $subqueue_id);
    $query->condition('queue', $subqueue_id);
    $result = $query->execute()->fetchField();

    if ($result && $uuid !== $result) {
      $entity_subqueue = $this->entityTypeManager
        ->getStorage('entity_subqueue')
        ->load($subqueue_id);
      $entity_subqueue->delete();
      $event->stopPropagation();
    }
  }

}
