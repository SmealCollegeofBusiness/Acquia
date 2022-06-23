<?php

namespace Drupal\acquia_contenthub\EventSubscriber\ImportFailure;

use Acquia\ContentHubClient\CDF\CDFObject;
use Drupal\acquia_contenthub\AcquiaContentHubEvents;
use Drupal\acquia_contenthub\Event\FailedImportEvent;
use Drupal\acquia_contenthub\Event\PreEntitySaveEvent;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Serialization\Yaml;
use Drupal\depcalc\DependencyStack;
use Drupal\depcalc\DependentEntityWrapper;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Class ProcessHighPriorityEntities.
 *
 * Process an entity that needs to be created with higher priority.
 * Example: Webform config entities need to be created before
 * so we don't have problems importing Webform submissions.
 *
 * @package Drupal\acquia_contenthub\EventSubscriber\ImportFailure
 */
class ProcessHighPriorityEntities implements EventSubscriberInterface {

  /**
   * Entity types that should be processed by this class.
   */
  protected const HIGH_PRIORITY_ENTITY_TYPES = [
    'webform',
  ];

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[AcquiaContentHubEvents::IMPORT_FAILURE][] = ['onImportFailure', 110];
    return $events;
  }

  /**
   * Process high priority entities.
   *
   * @param \Drupal\acquia_contenthub\Event\FailedImportEvent $event
   *   The failure event.
   * @param string $event_name
   *   The event name.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $dispatcher
   *   The event dispatcher.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function onImportFailure(FailedImportEvent $event, string $event_name, EventDispatcherInterface $dispatcher) {

    $stack = $event->getStack();
    $cdfs = [];

    $unprocessed = array_diff(array_keys($event->getCdf()->getEntities()), array_keys($event->getStack()->getDependencies()));
    foreach ($unprocessed as $key => $uuid) {
      $cdf = $event->getCdf()->getCdfEntity($uuid);
      $cdfs[] = $cdf;
      $entity_type = $cdf->getAttribute('entity_type')->getValue()['und'];
      if (in_array($entity_type, self::HIGH_PRIORITY_ENTITY_TYPES)) {
        $this->processHighPriorityEntity($cdf, $event, $dispatcher, $stack);
      }
    }
    // Some entities were processed, update count.
    $event->setCount(count($stack->getDependencies()));
  }

  /**
   * Creates entity if it doesn't exist or returns the existing one.
   *
   * @param \Acquia\ContentHubClient\CDF\CDFObject $cdf
   *   The imported CDF object.
   * @param \Drupal\acquia_contenthub\Event\FailedImportEvent $event
   *   The failure event.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $dispatcher
   *   The event dispatcher.
   * @param \Drupal\depcalc\DependencyStack $stack
   *   The dependency stack from this event.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  protected function processHighPriorityEntity(CDFObject $cdf, FailedImportEvent $event, EventDispatcherInterface $dispatcher, DependencyStack $stack) {
    $entity_type = $cdf->getAttribute('entity_type')->getValue()['und'];
    $entity = $this->getEntityRepository()->loadEntityByUuid($entity_type, $cdf->getUuid()) ??
      $this->createHighPriorityEntity($cdf, $dispatcher, $stack);

    $wrapper = new DependentEntityWrapper($entity, TRUE);
    $wrapper->setRemoteUuid($cdf->getUuid());
    $event->getStack()->addDependency($wrapper);
  }

  /**
   * Creates entity and dispatches necessary events.
   *
   * @param \Acquia\ContentHubClient\CDF\CDFObject $cdf
   *   The imported CDF object.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $dispatcher
   *   The event dispatcher.
   * @param \Drupal\depcalc\DependencyStack $stack
   *   The dependency stack from this event.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The created entity.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  protected function createHighPriorityEntity(CDFObject $cdf, EventDispatcherInterface $dispatcher, DependencyStack $stack): EntityInterface {

    $manager = $this->getEntityTypeManager();
    $entity_type = $cdf->getAttribute('entity_type')->getValue()['und'];
    $storage = $manager->getStorage($entity_type);

    $default_langcode = $cdf->getMetadata()['default_language'];
    $data = Yaml::decode(base64_decode($cdf->getMetadata()['data']));
    $default_values = $data[$default_langcode];

    $entity = $storage->create($default_values);

    $pre_entity_save_event = new PreEntitySaveEvent($entity, $stack, $cdf);
    $dispatcher->dispatch($pre_entity_save_event, AcquiaContentHubEvents::PRE_ENTITY_SAVE);
    $entity = $pre_entity_save_event->getEntity();

    $entity->save();

    return $entity;
  }

  /**
   * Gets the entity repository.
   *
   * @return \Drupal\Core\Entity\EntityRepositoryInterface
   *   The Entity Repository Service.
   */
  protected function getEntityRepository() {
    return \Drupal::service('entity.repository');
  }

  /**
   * Returns uncached entity type manager.
   *
   * @return \Drupal\Core\Entity\EntityTypeManagerInterface
   *   The entity type manager.
   */
  protected function getEntityTypeManager() {
    return \Drupal::entityTypeManager();
  }

}
