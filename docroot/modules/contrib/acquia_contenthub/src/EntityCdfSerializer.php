<?php

namespace Drupal\acquia_contenthub;

use Acquia\ContentHubClient\CDF\CDFObject;
use Acquia\ContentHubClient\CDFDocument;
use Drupal\acquia_contenthub\Event\CdfAttributesEvent;
use Drupal\acquia_contenthub\Event\CreateCdfEntityEvent;
use Drupal\acquia_contenthub\Event\EntityDataTamperEvent;
use Drupal\acquia_contenthub\Event\EntityImportEvent;
use Drupal\acquia_contenthub\Event\FailedImportEvent;
use Drupal\acquia_contenthub\Event\LoadLocalEntityEvent;
use Drupal\acquia_contenthub\Event\ParseCdfEntityEvent;
use Drupal\acquia_contenthub\Event\PreEntitySaveEvent;
use Drupal\acquia_contenthub\Event\PruneCdfEntitiesEvent;
use Drupal\acquia_contenthub\Exception\InvalidCdfException;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\SynchronizableInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Extension\ModuleInstallerInterface;
use Drupal\depcalc\DependencyCalculator;
use Drupal\depcalc\DependencyStack;
use Drupal\depcalc\DependentEntityWrapper;
use Drupal\depcalc\DependentEntityWrapperInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Serialize an entity to a CDF format.
 *
 * This class will convert an array of entities into a CDF compatible array of
 * data.
 */
class EntityCdfSerializer {

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $dispatcher;

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The dependency calculator.
   *
   * @var \Drupal\depcalc\DependencyCalculator
   */
  protected $calculator;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The module installer.
   *
   * @var \Drupal\Core\Extension\ModuleInstallerInterface
   */
  protected $moduleInstaller;

  /**
   * The stub tracker to clean up entities that were generated.
   *
   * @var StubTracker
   */
  protected $tracker;

  /**
   * The module extension list.
   *
   * @var \Drupal\Core\Extension\ModuleExtensionList
   */
  protected $moduleList;

  /**
   * EntityCdfSerializer constructor.
   *
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $dispatcher
   *   The event dispatcher.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   * @param \Drupal\depcalc\DependencyCalculator $calculator
   *   The dependency calculator.
   * @param \Drupal\Core\Extension\ModuleInstallerInterface $module_installer
   *   The module installer.
   * @param \Drupal\acquia_contenthub\StubTracker $tracker
   *   The stub tracker.
   * @param \Drupal\Core\Extension\ModuleExtensionList $module_list
   *   The module extension list.
   */
  public function __construct(EventDispatcherInterface $dispatcher, ConfigFactoryInterface $config_factory, DependencyCalculator $calculator, ModuleInstallerInterface $module_installer, StubTracker $tracker, ModuleExtensionList $module_list) {
    $this->dispatcher = $dispatcher;
    $this->configFactory = $config_factory;
    $this->calculator = $calculator;
    $this->moduleInstaller = $module_installer;
    $this->tracker = $tracker;
    $this->moduleList = $module_list;
  }

  /**
   * Serialize an array of entities into CDF format.
   *
   * @param \Drupal\depcalc\DependentEntityWrapperInterface ...$dependencies
   *   The entity dependency wrappers.
   *
   * @return \Acquia\ContentHubClient\CDF\CDFObject[]
   *   List of CDF objects.
   */
  public function serializeEntities(DependentEntityWrapperInterface ...$dependencies) {  //@codingStandardsIgnoreLine
    $output = [];
    foreach ($dependencies as $wrapper) {
      $entity = $wrapper->getEntity();
      $wrapper_dependencies = [];
      if ($entity_dependencies = $wrapper->getDependencies()) {
        $wrapper_dependencies['entity'] = $entity_dependencies;
      }
      if ($module_dependencies = $wrapper->getModuleDependencies()) {
        // Prevent unnecessary string keys.
        $wrapper_dependencies['module'] = array_values($module_dependencies);
      }
      $event = new CreateCdfEntityEvent($entity, $wrapper_dependencies);
      $this->dispatcher->dispatch($event, AcquiaContentHubEvents::CREATE_CDF_OBJECT);
      foreach ($event->getCdfList() as $cdf) {
        $attributesEvent = new CdfAttributesEvent($cdf, $entity, $wrapper);
        $this->dispatcher->dispatch($attributesEvent, AcquiaContentHubEvents::POPULATE_CDF_ATTRIBUTES);
        $output[] = $cdf;
      }
    }
    return $output;
  }

  /**
   * Unserializes a CDF into a list of Drupal entities.
   *
   * @todo add more docs about the expected CDF format.
   *
   * @param \Acquia\ContentHubClient\CDFDocument $cdf
   *   The CDF Document.
   * @param \Drupal\depcalc\DependencyStack $stack
   *   The dependency stack object.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\acquia_contenthub\Exception\InvalidCdfException
   * @throws \Drupal\acquia_contenthub_subscriber\Exception\ContentHubImportException
   * @throws \Exception
   */
  public function unserializeEntities(CDFDocument $cdf, DependencyStack $stack) {
    if (!$cdf->hasEntities()) {
      throw new InvalidCdfException(
        'Missing CDF Entities entry. Not a valid CDF.',
        InvalidCdfException::MISSING_ENTITIES_ENTRY,
      );
    }

    $cdf = $this->preprocessCdf($cdf, $stack);

    // Install required modules.
    $this->handleModules($cdf, $stack);

    $original_stack_size = count($stack->getDependencies());
    // Organize the entities into a dependency chain.
    // Use a while loop to prevent memory expansion due to recursion.
    while (!$stack->hasDependencies(array_keys($cdf->getEntities()))) {
      // @todo add tracking to break out of the while loop when dependencies cannot be further processed.
      $count = count($stack->getDependencies());
      $this->processCdf($cdf, $stack);
      $this->handleImportFailure($count, $original_stack_size, $cdf, $stack);
    }
    $this->tracker->cleanUp();
  }

  /**
   * Get the local StubTracker instance.
   *
   * @return \Drupal\acquia_contenthub\StubTracker
   *   Stub tracker.
   */
  public function getTracker() : StubTracker {
    return $this->tracker;
  }

  /**
   * Checks dependencies of a CDF entry to determine if it can be processed.
   *
   * CDF entries are turned into Drupal entities. This can only be done when
   * all the dependencies of an entry have been created. This method checks
   * dependencies to ensure they've been properly converted into Drupal
   * entities before proceeding with processing an entry.
   *
   * @param \Acquia\ContentHubClient\CDF\CDFObject $object
   *   The CDF Object.
   * @param \Drupal\depcalc\DependencyStack $stack
   *   The dependency stack.
   *
   * @return bool
   *   Whether a CDF entry is processable or is not.
   */
  protected function entityIsProcessable(CDFObject $object, DependencyStack $stack) {
    foreach (array_keys($object->getDependencies()) as $dependency_uuid) {
      if (!$stack->hasDependency($dependency_uuid)) {
        return FALSE;
      }
    }
    return TRUE;
  }

  /**
   * Ensures all required modules of a set of entities are enabled.
   *
   * If modules are missing from the code base, this method will throw an
   * exception before any importing of content can occur which should prevent
   * entities from being in half-operational states.
   *
   * @param \Acquia\ContentHubClient\CDFDocument $cdf
   *   The CDF Document.
   * @param \Drupal\depcalc\DependencyStack $stack
   *   The dependency stack.
   *
   * @throws \Exception
   *   The exception thrown if a module is missing from the code base.
   */
  protected function handleModules(CDFDocument $cdf, DependencyStack $stack) {
    $dependencies = [];
    $unordered_entities = $cdf->getEntities();

    foreach ($unordered_entities as &$entity) {
      // Don't process entities, their dependencies are working.
      if ($stack->hasDependency($entity->getUuid())) {
        continue;
      }
      // Don't process non-entities we've previously processed.
      if ($entity->hasProcessedDependencies()) {
        continue;
      }
      // No need to process entities that don't have module dependencies.
      if (!$entity->getModuleDependencies()) {
        continue;
      }
      $dependencies = NestedArray::mergeDeep($dependencies, $entity->getModuleDependencies());
      $entity->markProcessedDependencies();
    }

    // Check the uniqueness of the module list.
    $dependencies = array_unique($dependencies);
    foreach ($dependencies as $index => $module) {
      // @todo consider a configuration that prevents new module installation.
      // Module isn't installed.
      if (!$this->getModuleHandler()->moduleExists($module)) {
        // Module doesn't exist in the code base, so we can't install.
        if (!$this->moduleList->getPathname($module)) {
          throw new \Exception(sprintf("The %s module code base is not present.", $module));
        }
      }
      else {
        unset($dependencies[$index]);
      }
    }

    if (!empty($dependencies)) {
      $this->moduleInstaller->install(array_values($dependencies));
    }

    unset($unordered_entities, $dependencies);
    // @todo determine if this cache invalidation is necessary.
    \Drupal::cache()->invalidateAll();
    // Using \Drupal::entityTypeManager() do to caching of the instance in
    // some services. Looks like a core bug.
    \Drupal::entityTypeManager()->clearCachedDefinitions();
  }

  /**
   * Get the module handler statically to prevent issues with module install.
   *
   * @return \Drupal\Core\Extension\ModuleHandlerInterface
   *   Module handler.
   */
  protected function getModuleHandler() {
    return \Drupal::moduleHandler();
  }

  /**
   * Gets a list of unprocessed dependencies in a CDFDocument.
   *
   * @param \Acquia\ContentHubClient\CDFDocument $cdf
   *   The CDFDocument to find unprocessed dependencies within.
   * @param \Drupal\depcalc\DependencyStack $stack
   *   The stack of processed dependencies to compare our entities against.
   *
   * @return \Acquia\ContentHubClient\CDF\CDFObject[]
   *   An array of CDFObjects.
   */
  protected function getUnprocessedDependencies(CDFDocument $cdf, DependencyStack $stack) {
    return array_map(
      function ($uuid) use ($cdf) {
        return $cdf->getCdfEntity($uuid);
      },
      array_diff(array_keys($cdf->getEntities()), array_keys($stack->getProcessedDependencies()))
    );
  }

  /**
   * Dispatches events to prune and tamper data from incoming CDF document.
   *
   * @param \Acquia\ContentHubClient\CDFDocument $cdf
   *   The CDF document.
   * @param \Drupal\depcalc\DependencyStack $stack
   *   The dependency stack.
   *
   * @return \Acquia\ContentHubClient\CDFDocument
   *   The preprocessed CDF document.
   */
  protected function preprocessCdf(CDFDocument $cdf, DependencyStack $stack): CDFDocument {
    $prune_cdf_event = new PruneCdfEntitiesEvent($cdf);
    $this->dispatcher->dispatch($prune_cdf_event, AcquiaContentHubEvents::PRUNE_CDF);
    $cdf = $prune_cdf_event->getCdf();

    // Allows entity data to be manipulated before unserialization.
    $entity_data_tamper_event = new EntityDataTamperEvent($cdf, $stack);
    $this->dispatcher->dispatch($entity_data_tamper_event, AcquiaContentHubEvents::ENTITY_DATA_TAMPER);
    return $entity_data_tamper_event->getCdf();
  }

  /**
   * Processes incoming CDF.
   *
   * @param \Acquia\ContentHubClient\CDFDocument $cdf
   *   The CDF document.
   * @param \Drupal\depcalc\DependencyStack $stack
   *   The dependency stack.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function processCdf(CDFDocument $cdf, DependencyStack $stack) {
    foreach ($this->getUnprocessedDependencies($cdf, $stack) as $entity_data) {
      if (!$this->entityIsProcessable($entity_data, $stack)) {
        continue;
      }

      $uuid = $entity_data->getUuid();
      $entity = $this->getEntityFromCdf($entity_data, $stack);
      if (!$entity) {
        // Remove CDF Entities that were processable but didn't resolve into
        // an entity.
        $cdf->removeCdfEntity($uuid);
        continue;
      }

      $pre_entity_save_event = new PreEntitySaveEvent($entity, $stack, $entity_data);
      $this->dispatcher->dispatch($pre_entity_save_event, AcquiaContentHubEvents::PRE_ENTITY_SAVE);
      $entity = $pre_entity_save_event->getEntity();
      // Added to avoid creating new revisions with stubbed data.
      // See \Drupal\content_moderation\Entity\Handler\ModerationHandler.
      if ($entity instanceof SynchronizableInterface) {
        $entity->setSyncing(TRUE);
      }
      $entity->save();

      $this->addToStack($entity, $uuid, $stack);

      $this->dispatchImportEvent($entity, $entity_data);
    }
  }

  /**
   * Dispatches events to get entity from CDF object.
   *
   * @param \Acquia\ContentHubClient\CDF\CDFObject $entity_data
   *   The CDF object.
   * @param \Drupal\depcalc\DependencyStack $stack
   *   The dependency stack.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   Then entity from the CDF.
   */
  protected function getEntityFromCdf(CDFObject $entity_data, DependencyStack $stack): ?EntityInterface {
    $load_local_entity_event = new LoadLocalEntityEvent($entity_data, $stack);
    $this->dispatcher->dispatch($load_local_entity_event, AcquiaContentHubEvents::LOAD_LOCAL_ENTITY);

    $parse_cdf_event = new ParseCdfEntityEvent($entity_data, $stack, $load_local_entity_event->getEntity());
    $this->dispatcher->dispatch($parse_cdf_event, AcquiaContentHubEvents::PARSE_CDF);

    return $parse_cdf_event->getEntity() ?? NULL;
  }

  /**
   * Dispatches entity import event.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity being imported.
   * @param \Acquia\ContentHubClient\CDF\CDFObject $entity_data
   *   The CDF object.
   */
  protected function dispatchImportEvent(EntityInterface $entity, CDFObject $entity_data) {
    $event_name = $entity->isNew() ? AcquiaContentHubEvents::ENTITY_IMPORT_NEW : AcquiaContentHubEvents::ENTITY_IMPORT_UPDATE;
    $entity_import_event = new EntityImportEvent($entity, $entity_data);
    $this->dispatcher->dispatch($entity_import_event, $event_name);
  }

  /**
   * Adds imported entity to stack.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity being imported.
   * @param string $uuid
   *   The remote UUID.
   * @param \Drupal\depcalc\DependencyStack $stack
   *   The dependency stack.
   *
   * @throws \Exception
   */
  protected function addToStack(EntityInterface $entity, string $uuid, DependencyStack $stack) {
    $wrapper = new DependentEntityWrapper($entity);
    // Config uuids can be more fluid since they can match on id.
    if ($wrapper->getUuid() != $uuid) {
      $wrapper->setRemoteUuid($uuid);
    }
    $stack->addDependency($wrapper);
  }

  /**
   * Handles import failure.
   *
   * @param int $count
   *   The previous count from the dependency stack.
   * @param int $original_stack_size
   *   The original dependency stack size.
   * @param \Acquia\ContentHubClient\CDFDocument $cdf
   *   The CDF document.
   * @param \Drupal\depcalc\DependencyStack $stack
   *   The dependency stack.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function handleImportFailure(int $count, int $original_stack_size, CDFDocument $cdf, DependencyStack $stack) {
    $actual_processed_stack_count = $count - $original_stack_size;
    $import_failed = $count === count($stack->getDependencies()) && $actual_processed_stack_count < count($cdf->getEntities());
    if (!$import_failed) {
      return;
    }

    // @todo get import failure logging and tracking working.
    $failed_import_event = new FailedImportEvent($cdf, $stack, $count, $this);
    $this->dispatcher->dispatch($failed_import_event, AcquiaContentHubEvents::IMPORT_FAILURE);
    if ($failed_import_event->hasException()) {
      $this->tracker->cleanUp(TRUE);
      throw $failed_import_event->getException();
    }
  }

}
