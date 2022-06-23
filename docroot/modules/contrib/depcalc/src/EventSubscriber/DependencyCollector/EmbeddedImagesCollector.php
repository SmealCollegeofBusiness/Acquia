<?php

namespace Drupal\depcalc\EventSubscriber\DependencyCollector;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\Condition;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\depcalc\DependencyCalculatorEvents;
use Drupal\depcalc\DependentEntityWrapper;
use Drupal\depcalc\Event\CalculateEntityDependenciesEvent;
use Drupal\file\Entity\File;

/**
 * Class EmbeddedImagesCollector.
 *
 * @package Drupal\depcalc\EventSubscriber\DependencyCollector
 */
class EmbeddedImagesCollector extends BaseDependencyCollector {

  /**
   * Database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * EmbeddedImagesCollector constructor.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   Database connection.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   Module handler.
   */
  public function __construct(Connection $database, ModuleHandlerInterface $module_handler) {
    $this->database = $database;
    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[DependencyCalculatorEvents::CALCULATE_DEPENDENCIES][] = ['onCalculateDependencies'];
    return $events;
  }

  /**
   * Reacts on CALCULATE_DEPENDENCIES event.
   *
   * @param \Drupal\depcalc\Event\CalculateEntityDependenciesEvent $event
   *   Event.
   *
   * @throws \Exception
   */
  public function onCalculateDependencies(CalculateEntityDependenciesEvent $event) {
    if (!$this->moduleHandler->moduleExists('file')) {
      return;
    }
    $entity = $event->getEntity();

    if (FALSE === ($entity instanceof ContentEntityInterface)) {
      return;
    }

    $files = $this->getAttachedFiles($entity, 'editor');
    foreach ($files as $file) {
      $this->addDependency($event, $file);
    }
  }

  /**
   * Builds list of attached files.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Entity.
   * @param string $module
   *   Module name.
   *
   * @return \Drupal\file\Entity\File[]
   *   Files list.
   */
  protected function getAttachedFiles(EntityInterface $entity, string $module = 'file'): array {
    $criteria = $this->database->condition('AND');
    $criteria->condition('type', $entity->getEntityTypeId())
      ->condition('count', '0', '>')
      ->condition('module', [$module], 'in')
      ->condition('id', $entity->id());

    $rows = $this->database->select('file_usage', 'f')
      ->fields('f', ['fid'])
      ->condition($criteria)
      ->execute()
      ->fetchAllAssoc('fid');

    if (empty($rows)) {
      return [];
    }

    $fids = array_keys($rows);

    return File::loadMultiple($fids);
  }

  /**
   * Add dependency.
   *
   * @param \Drupal\depcalc\Event\CalculateEntityDependenciesEvent $event
   *   Event.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Entity.
   *
   * @throws \Exception
   */
  protected function addDependency(CalculateEntityDependenciesEvent $event, EntityInterface $entity): void {
    if ($event->getStack()->hasDependency($entity->uuid())) {
      return;
    }

    $entity_wrapper = new DependentEntityWrapper($entity);
    $local_dependencies = [];
    $entity_wrapper_dependencies = $this->getCalculator()
      ->calculateDependencies($entity_wrapper, $event->getStack(), $local_dependencies);
    $this->mergeDependencies($entity_wrapper, $event->getStack(), $entity_wrapper_dependencies);
    $event->addDependency($entity_wrapper);
  }

}
