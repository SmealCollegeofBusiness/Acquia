<?php

namespace Drupal\acquia_contenthub\EventSubscriber\ImportFailure;

use Acquia\ContentHubClient\CDFDocument;
use Drupal\acquia_contenthub\AcquiaContentHubEvents;
use Drupal\acquia_contenthub\Event\FailedImportEvent;
use Drupal\acquia_contenthub\Event\LoadLocalEntityEvent;
use Drupal\acquia_contenthub\Event\PreEntitySaveEvent;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\depcalc\DependentEntityWrapper;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Class CreateStubs.
 *
 * Creates stub content entities from field sample values for required fields
 * in order to setup entities with circular dependencies on each other. Once
 * these stubs are all created they'll be saved over with real values and any
 * stub which are inadvertently created during this process will be deleted as
 * the final step of import.
 *
 * @package Drupal\acquia_contenthub\EventSubscriber\ImportFailure
 */
class CreateStubs implements EventSubscriberInterface {

  /**
   * The processed dependency count to prevent infinite loops.
   *
   * @var int
   */
  protected static $count = 0;

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $manager;

  /**
   * The Entity Field Manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * CreateStubs constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $manager
   *   The entity manager.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The Entity Field Manager.
   */
  public function __construct(EntityTypeManagerInterface $manager, EntityFieldManagerInterface $entity_field_manager) {
    $this->manager = $manager;
    $this->entityFieldManager = $entity_field_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[AcquiaContentHubEvents::IMPORT_FAILURE][] = ['onImportFailure', 100];
    return $events;
  }

  /**
   * Generate stub entities for all remaining content entities and reimports.
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
    if (static::$count === $event->getCount()) {
      $exception = new \Exception("Potential infinite recursion call interrupted in CreateStubs event subscriber.");
      $event->setException($exception);
      return;
    }
    static::$count = $event->getCount();
    $unprocessed = array_diff(array_keys($event->getCdf()->getEntities()), array_keys($event->getStack()->getDependencies()));
    if (!$unprocessed) {
      $event->stopPropagation();
      return;
    }
    $cdfs = [];
    // Process config entities first.
    foreach ($unprocessed as $key => $uuid) {
      $cdf = $event->getCdf()->getCdfEntity($uuid);
      if ($cdf->getType() === 'drupal8_config_entity') {
        unset($unprocessed[$key]);
        $cdfs[] = $cdf;
        continue;
      }
    }
    if (!$event->getSerializer()->getTracker()->isTracking()) {
      $event->getSerializer()->getTracker()->setStack($event->getStack());
    }
    // Process content entities and create stubs where necessary.
    foreach ($unprocessed as $key => $uuid) {
      $cdf = $event->getCdf()->getCdfEntity($uuid);
      // This only works on content entities.
      if ($cdf->getType() !== 'drupal8_content_entity') {
        $cdfs[] = $cdf;
        continue;
      }
      $load_event = new LoadLocalEntityEvent($cdf, $event->getStack());
      $dispatcher->dispatch(AcquiaContentHubEvents::LOAD_LOCAL_ENTITY, $load_event);
      $entity = $load_event->getEntity();
      // No entity loaded, so create a stub to populate with data later.
      if (!$entity) {
        $entity_type = $cdf->getAttribute('entity_type')->getValue()['und'];
        $definition = $this->manager->getDefinition($entity_type);
        $storage = $this->manager->getStorage($entity_type);
        $keys = $definition->getKeys();
        $values = [
          $keys['uuid'] => $uuid,
          'langcode' => $cdf->getMetadata()['default_language'],
        ];
        if (!empty($keys['bundle'])) {
          $values[$keys['bundle']] = $cdf->getAttribute('bundle')->getValue()['und'];
        }
        if (!empty($keys['label'])) {
          $field_definitions = !empty($keys['bundle']) ? $this->entityFieldManager->getFieldDefinitions($entity_type, $keys['bundle']) : NULL;
          $field_settings = isset($field_definitions) ? $field_definitions[$keys['label']]->getItemDefinition()->getSettings() : [];
          $size = $field_settings['max_length'] ?? 255;
          $values[$keys['label']] = mb_substr($cdf->getAttribute('label')->getValue()['und'], 0, $size);
        }
        /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
        $entity = $storage->create($values);
        /** @var \Drupal\Core\Field\FieldItemListInterface $field */
        foreach ($entity as $field_name => $field) {
          if ($entity->getEntityType()->getKey('id') === $field_name || $entity->getEntityType()->getKey('revision') === $field_name) {
            continue;
          }
          if ($field->isEmpty() && $this->fieldIsRequired($field)) {
            $field->generateSampleItems();
          }
        }
        $pre_entity_save_event = new PreEntitySaveEvent($entity, $event->getStack(), $cdf);
        $dispatcher->dispatch(AcquiaContentHubEvents::PRE_ENTITY_SAVE, $pre_entity_save_event);
        $entity = $pre_entity_save_event->getEntity();
        $entity->save();
      }
      $wrapper = new DependentEntityWrapper($entity, TRUE);
      $wrapper->setRemoteUuid($uuid);
      $event->getStack()->addDependency($wrapper);
      $cdfs[] = $cdf;
    }
    $document = new CDFDocument(...$cdfs);
    try {
      $event->getSerializer()->unserializeEntities($document, $event->getStack());
      $event->stopPropagation();
    }
    catch (\Exception $e) {
      $event->setException($e);
    }
    static::$count = 0;
  }

  /**
   * Determines if a field or field property is required for the entity.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $field
   *   The field to evaluate.
   *
   * @return bool
   *   Whether or not the field will require sample value generation.
   */
  protected function fieldIsRequired(FieldItemListInterface $field) : bool {
    if (!$field->getFieldDefinition() instanceof BaseFieldDefinition) {
      return FALSE;
    }
    if ($field->getFieldDefinition()->isComputed()) {
      return FALSE;
    }
    if ($field->getFieldDefinition()->isRequired()) {
      return TRUE;
    }
    // Check each field property for its own requirement settings.
    foreach ($field->getFieldDefinition()->getFieldStorageDefinition()->getPropertyDefinitions() as $propertyDefinition) {
      if ($propertyDefinition->isRequired()) {
        return TRUE;
      }
    }
    return FALSE;
  }

}
