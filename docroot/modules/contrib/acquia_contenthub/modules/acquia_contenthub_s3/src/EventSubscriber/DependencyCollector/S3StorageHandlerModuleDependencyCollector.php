<?php

namespace Drupal\acquia_contenthub_s3\EventSubscriber\DependencyCollector;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\depcalc\DependencyCalculatorEvents;
use Drupal\depcalc\Event\CalculateEntityDependenciesEvent;
use Drupal\depcalc\EventSubscriber\DependencyCollector\BaseDependencyCollector;

/**
 * Subscribes to dependency collection to append module dependency information.
 */
class S3StorageHandlerModuleDependencyCollector extends BaseDependencyCollector {

  /**
   * List of modules that implement AWS S3 storage handling.
   *
   * @var string[]
   */
  protected $requiredS3Modules = [
    's3fs',
  ];

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * S3FileDependencyCollector constructor.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler service.
   */
  public function __construct(ModuleHandlerInterface $moduleHandler) {
    $this->moduleHandler = $moduleHandler;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[DependencyCalculatorEvents::CALCULATE_DEPENDENCIES][] = ['onCalculateDependencies'];
    return $events;
  }

  /**
   * Adds the accepted s3 modules to the module dependency list if applicable.
   *
   * @param \Drupal\depcalc\Event\CalculateEntityDependenciesEvent $event
   *   The dependency calculation event.
   */
  public function onCalculateDependencies(CalculateEntityDependenciesEvent $event) {
    /** @var \Drupal\field\FieldStorageConfigInterface $entity */
    $entity = $event->getEntity();
    if ($entity->getEntityTypeId() !== 'field_storage_config') {
      return;
    }

    $uri_scheme = $entity->getSetting('uri_scheme');
    if (!$uri_scheme || $uri_scheme !== 's3') {
      return;
    }

    $modules = $this->getS3ModuleDependencies();
    if (empty($modules)) {
      return;
    }

    $modules = array_merge($event->getModuleDependencies(), $modules);
    $event->setModuleDependencies($modules);
  }

  /**
   * Returns all the s3 module dependencies.
   *
   * @return array
   *   Array of required S3 modules.
   */
  protected function getS3ModuleDependencies(): array {
    $modules = [];
    foreach ($this->requiredS3Modules as $module) {
      if (!$this->moduleHandler->moduleExists($module)) {
        continue;
      }

      $modules[] = $module;
    }

    return $modules;
  }

}
