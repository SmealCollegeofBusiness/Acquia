<?php

namespace Drupal\acquia_contenthub_site_health\Commands;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\Entity\ConfigEntityType;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drush\Commands\DrushCommands;

/**
 * Drush command to fix config entities with null uuids.
 *
 * @package Drupal\acquia_contenthub_config_null_uuids_fix\Commands
 */
class AcquiaContentHubConfigNullUuidsFix extends DrushCommands {

  /**
   * The Entity Type Manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The Configuration Factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The Module Handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The UUID Generator.
   *
   * @var \Drupal\Component\Uuid\UuidInterface
   */
  protected $uuidGenerator;

  /**
   * AcquiaContentHubConfigNullUuidsFix constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The Entity Type Manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The Configuration Factory.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The Module Handler.
   * @param \Drupal\Component\Uuid\UuidInterface $uuidGenerator
   *   The UUID Generator.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ConfigFactoryInterface $config_factory, ModuleHandlerInterface $module_handler, UuidInterface $uuidGenerator) {
    $this->entityTypeManager = $entity_type_manager;
    $this->configFactory = $config_factory;
    $this->moduleHandler = $module_handler;
    $this->uuidGenerator = $uuidGenerator;
  }

  /**
   * Assigns randomly generated UUIDs to configuration entities with NULL UUIDs.
   *
   * @command acquia:contenthub-fix-config-entities-with-null-uuids
   * @aliases ach-fix-null-uuids
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function fixConfigEntitiesWithNullUuids() {
    $this->output()->writeln(sprintf("Checking Drupal configuration entities for compatibility with Content Hub...\n\n"));
    // If this site isn't a publisher, don't generate UUIDs for config entities.
    if (!$this->moduleHandler->moduleExists('acquia_contenthub_publisher')) {
      throw new \Exception("This command should only be run on a publisher site.");
    }
    $missing_uuid_count = 0;
    foreach ($this->entityTypeManager->getDefinitions() as $entity_type) {
      if (!$entity_type instanceof ConfigEntityType) {
        continue;
      }
      $entity_type_id = $entity_type->id();
      $storage = $this->entityTypeManager->getStorage($entity_type_id);
      $entities = $storage->loadMultiple();
      $missing_uuid_count = 0;
      foreach ($entities as $entity) {
        if (!$entity->uuid()) {
          $missing_uuid_count++;
          $config_id = $entity->getConfigDependencyName();
          $config = $this->configFactory->getEditable($config_id);
          $config->set('uuid', $this->uuidGenerator->generate());
          $config->save();
          $entity = $storage->load($entity->id());
          $this->output()->writeln(sprintf("Entity type: %s, Entity id: %s, Entity uuid: %s\n", $entity_type->id(), $entity->id(), $entity->uuid()));
        }
      }
    }
    if ($missing_uuid_count === 0) {
      $this->output()->writeln(sprintf("\n\nAll Drupal configuration entities have proper UUIDs."));
    }
  }

}
