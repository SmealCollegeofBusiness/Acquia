<?php

namespace Drupal\acquia_contenthub_subscriber\EventSubscriber\PreEntitySave;

use Drupal\acquia_contenthub\AcquiaContentHubEvents;
use Drupal\acquia_contenthub\Event\PreEntitySaveEvent;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Handles config entities with null uuids.
 *
 * @package Drupal\acquia_contenthub_subscriber\EventSubscriber\PreEntitySave
 */
class ConfigEntityWithNullUuid implements EventSubscriberInterface {
  /**
   * The module handler.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * ConfigEntityWithNullUuid constructor.
   */
  public function __construct() {
    $this->entityTypeManager = \Drupal::entityTypeManager();
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[AcquiaContentHubEvents::PRE_ENTITY_SAVE] = 'onPreEntitySave';
    return $events;
  }

  /**
   * Saves a configuration entity to assign the UUID of an imported entity.
   *
   * @param \Drupal\acquia_contenthub\Event\PreEntitySaveEvent $event
   *   The pre entity save event.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function onPreEntitySave(PreEntitySaveEvent $event) {
    $entity = $event->getEntity();
    if ($entity instanceof ConfigEntityInterface) {
      $local_entity = $this->entityTypeManager->getStorage($entity->getEntityTypeId())->load($entity->id());
      if ($local_entity && !$local_entity->uuid()) {
        $config_id = $entity->getConfigDependencyName();
        $config = \Drupal::configFactory()->getEditable($config_id);
        $config->set('uuid', $event->getCdf()->getUuid());
        $config->save();
        $entity->set('uuid', $event->getCdf()->getUuid());
      }
    }
  }

}
