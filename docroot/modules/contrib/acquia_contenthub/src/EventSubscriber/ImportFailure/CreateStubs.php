<?php

namespace Drupal\acquia_contenthub\EventSubscriber\ImportFailure;

use Acquia\ContentHubClient\CDF\CDFObject;
use Acquia\ContentHubClient\CDFDocument;
use Drupal\acquia_contenthub\AcquiaContentHubEvents;
use Drupal\acquia_contenthub\Event\FailedImportEvent;
use Drupal\acquia_contenthub\Event\LoadLocalEntityEvent;
use Drupal\acquia_contenthub\Event\PreEntitySaveEvent;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\SynchronizableInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\depcalc\DependencyStack;
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

    if (!$event->getSerializer()->getTracker()->isTracking()) {
      $event->getSerializer()->getTracker()->setStack($event->getStack());
    }

    $this->handleEntityProcessing($unprocessed, $event, $dispatcher);

    static::$count = 0;
  }

  /**
   * Process entities and handle exceptions.
   *
   * @param array $unprocessed
   *   The unprocessed array.
   * @param \Drupal\acquia_contenthub\Event\FailedImportEvent $event
   *   The event object.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $dispatcher
   *   The dispatcher.
   */
  protected function handleEntityProcessing(
    array $unprocessed,
    FailedImportEvent $event,
    EventDispatcherInterface $dispatcher
  ) {
    try {
      $cdfs =
        $this->processConfigEntities($unprocessed, $event) +
        $this->processContentEntities($unprocessed, $event, $dispatcher);
      $document = new CDFDocument(...$cdfs);

      $event->getSerializer()->unserializeEntities($document, $event->getStack());
      $event->stopPropagation();
    }
    catch (\Exception $e) {
      $event->setException($e);
    }
  }

  /**
   * Process config entities.
   *
   * @param array $unprocessed
   *   The unprocessed array.
   * @param \Drupal\acquia_contenthub\Event\FailedImportEvent $event
   *   The event object.
   *
   * @return array
   *   The CDFs.
   */
  protected function processConfigEntities(array &$unprocessed, FailedImportEvent $event): array {
    $cdfs = [];
    foreach ($unprocessed as $key => $uuid) {
      $cdf = $event->getCdf()->getCdfEntity($uuid);
      if ($cdf->getType() !== 'drupal8_config_entity') {
        continue;
      }

      unset($unprocessed[$key]);
      $cdfs[] = $cdf;
    }
    return $cdfs;
  }

  /**
   * Process content entities.
   *
   * @param array $unprocessed
   *   The unprocessed array.
   * @param \Drupal\acquia_contenthub\Event\FailedImportEvent $event
   *   The event object.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $dispatcher
   *   The dispatcher.
   *
   * @return array
   *   The CDFs.
   *
   * @throws \Exception
   */
  protected function processContentEntities(
    array $unprocessed,
    FailedImportEvent $event,
    EventDispatcherInterface $dispatcher
  ): array {
    $cdfs = [];
    foreach ($unprocessed as $key => $uuid) {
      $cdf = $event->getCdf()->getCdfEntity($uuid);
      $stack = $event->getStack();
      $load_event = new LoadLocalEntityEvent($cdf, $stack, TRUE);
      $dispatcher->dispatch($load_event, AcquiaContentHubEvents::LOAD_LOCAL_ENTITY);

      $entity = $load_event->getEntity() ??
        $this->createStub($cdf, $uuid, $stack, $dispatcher);
      $wrapper = new DependentEntityWrapper($entity, TRUE);
      $wrapper->setRemoteUuid($uuid);
      $stack->addDependency($wrapper);
      $cdfs[] = $cdf;
    }

    return $cdfs;
  }

  /**
   * Create stub entity.
   *
   * @param \Acquia\ContentHubClient\CDF\CDFObject $cdf
   *   The CDF object.
   * @param string $uuid
   *   The incoming UUID.
   * @param \Drupal\Depcalc\DependencyStack $stack
   *   The dependency stack.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $dispatcher
   *   The dispatcher.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The stub entity.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function createStub(
    CDFObject $cdf,
    string $uuid,
    DependencyStack $stack,
    EventDispatcherInterface $dispatcher
  ): EntityInterface {
    $entity_type = $cdf->getAttribute('entity_type')->getValue()[CDFObject::LANGUAGE_UNDETERMINED];
    $manager = $this->getEntityTypeManager();
    $definition = $manager->getDefinition($entity_type);
    $storage = $manager->getStorage($entity_type);
    $keys = $definition->getKeys();

    $values = $this->getEntityValues(
      $keys,
      $uuid,
      $entity_type,
      $cdf
    );
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $storage->create($values);
    $this->generateRequiredSampleItems($entity);

    $pre_entity_save_event = new PreEntitySaveEvent($entity, $stack, $cdf);
    $dispatcher->dispatch($pre_entity_save_event, AcquiaContentHubEvents::PRE_ENTITY_SAVE);
    $entity = $pre_entity_save_event->getEntity();
    // Added to avoid creating new revisions with stubbed data.
    // See \Drupal\content_moderation\Entity\Handler\ModerationHandler.
    if ($entity instanceof SynchronizableInterface) {
      $entity->setSyncing(TRUE);
    }

    $entity->save();

    return $entity;
  }

  /**
   * Creates array with the basic values for the stub based on incoming CDF.
   *
   * @param array $keys
   *   Entity keys.
   * @param string $uuid
   *   Incoming UUID.
   * @param string $entity_type
   *   The entity type.
   * @param \Acquia\ContentHubClient\CDF\CDFObject $cdf
   *   The CDF object.
   *
   * @return array
   *   The basic values for the stub entity.
   */
  protected function getEntityValues(
    array $keys,
    string $uuid,
    string $entity_type,
    CDFObject $cdf): array {
    $values = [
      $keys['uuid'] => $uuid,
      // Get the language key from entity keys.
      $keys['langcode'] => $cdf->getMetadata()['default_language'],
    ];
    if (!empty($keys['bundle'])) {
      $values[$keys['bundle']] = $cdf->getAttribute('bundle')->getValue()[CDFObject::LANGUAGE_UNDETERMINED];
    }
    if (!empty($keys['label'])) {
      $field_definitions = !empty($keys['bundle']) ? $this->getEntityFieldManager()->getFieldDefinitions($entity_type, $keys['bundle']) : NULL;
      $field_settings = isset($field_definitions) ? $field_definitions[$keys['label']]->getItemDefinition()->getSettings() : [];
      $size = $field_settings['max_length'] ?? 255;
      $values[$keys['label']] = mb_substr($cdf->getAttribute('label')->getValue()[CDFObject::LANGUAGE_UNDETERMINED], 0, $size);
    }

    return $values;
  }

  /**
   * Generate sample items for fields that require it.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The stub entity.
   */
  protected function generateRequiredSampleItems(ContentEntityInterface $entity) {

    // Fields that either don't require samples.
    $skip_fields = [
      $entity->getEntityType()->getKey('id'),
      $entity->getEntityType()->getKey('revision'),
      'revision_log',
    ];

    /** @var \Drupal\Core\Field\FieldItemListInterface $field */
    foreach ($entity as $field_name => $field) {
      if (in_array($field_name, $skip_fields)) {
        continue;
      }
      if ($field->isEmpty() && $this->fieldIsRequired($field)) {
        $field->generateSampleItems();
      }
    }
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

  /**
   * Returns uncached entity type manager.
   *
   * @return \Drupal\Core\Entity\EntityTypeManagerInterface
   *   The entity type manager.
   */
  protected function getEntityTypeManager(): EntityTypeManagerInterface {
    return \Drupal::entityTypeManager();
  }

  /**
   * Returns uncached entity field manager.
   *
   * @return \Drupal\Core\Entity\EntityFieldManagerInterface
   *   The entity field manager.
   */
  protected function getEntityFieldManager(): EntityFieldManagerInterface {
    return \Drupal::service('entity_field.manager');
  }

}
