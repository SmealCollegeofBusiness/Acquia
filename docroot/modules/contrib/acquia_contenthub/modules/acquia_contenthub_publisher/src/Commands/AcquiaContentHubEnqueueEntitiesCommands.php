<?php

namespace Drupal\acquia_contenthub_publisher\Commands;

use Drupal\acquia_contenthub_publisher\PublisherTracker;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\depcalc\Cache\DepcalcCacheBackend;
use Drush\Commands\DrushCommands;

/**
 * Drush commands for Acquia Content Hub enqueue entities.
 *
 * @package Drupal\acquia_contenthub_publisher\Commands
 */
class AcquiaContentHubEnqueueEntitiesCommands extends DrushCommands {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity type bundle info.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityTypeBundleInfo;

  /**
   * The Database Connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The Publisher Tracker.
   *
   * @var \Drupal\acquia_contenthub_publisher\PublisherTracker
   */
  protected $publisherTracker;

  /**
   * The Depcalc Cache backend.
   *
   * @var \Drupal\depcalc\Cache\DepcalcCacheBackend
   */
  protected $depcalcCache;

  /**
   * AcquiaContentHubEnqueueEntitiesByBundleCommands constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle info.
   * @param \Drupal\Core\Database\Connection $database
   *   The Connection to the Database.
   * @param \Drupal\acquia_contenthub_publisher\PublisherTracker $publisher_tracker
   *   The Publisher Tracker.
   * @param \Drupal\depcalc\Cache\DepcalcCacheBackend $depcalc_cache
   *   The Depcalc Cache Backend.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, EntityTypeBundleInfoInterface $entity_type_bundle_info, Connection $database, PublisherTracker $publisher_tracker, DepcalcCacheBackend $depcalc_cache) {
    $this->entityTypeManager = $entityTypeManager;
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
    $this->database = $database;
    $this->publisherTracker = $publisher_tracker;
    $this->depcalcCache = $depcalc_cache;
  }

  /**
   * Re-queues entities to Acquia Content Hub.
   *
   * At least one option has to be Provided.
   *
   * @option type
   *   The entity type.
   * @default type
   * @option bundle
   *   The entity bundle.
   * @default bundle
   * @option use-tracking-table
   *   Only re-queue entities that are in the Publisher tracking table
   * If not used, it will enqueue ALL entities of the provided type and bundle.
   * @default false
   * @option only-queued-entities
   *   Only re-queue entities that have "queued" status in the tracking table
   * but are not in the export queue.
   * @default false
   *
   * @usage ach-rq
   *   You have to provide at least one option.
   * @usage acquia:contenthub-re-queue --type=node
   *   Requeues all eligible node entities in the site
   * (disregards what has been published before).
   * @usage acquia:contenthub-re-queue --type=node --bundle=page
   *   Requeues all eligible node pages in the site
   * (disregards what has been published before).
   * @usage acquia:contenthub-re-queue --only-queued-entities
   *   Requeues all entities with "queued" status in the publisher tracking
   * table but are not in the export queue anymore.
   * @usage acquia:contenthub-re-queue --use-tracking-table
   *   Requeues all existing entities in the publisher tracking table.
   * @usage acquia:contenthub-re-queue --type=node --bundle=article --use-tracking-table
   *   Requeues all node article entities that have been previously
   * published (they exist in the publisher tracking table).
   *
   * @command acquia:contenthub-re-queue
   * @aliases ach-rq
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Exception
   */
  public function enqueueEntities() {
    // Performing validations on user input.
    $entity_type = NULL;
    $data = [];
    try {
      if ($entity_type = $this->input->getOption('type')) {
        $storage = $this->entityTypeManager->getStorage($entity_type);
      }
    }
    catch (PluginNotFoundException $exception) {
      throw $exception;
    }

    if ($bundle = $this->input->getOption('bundle')) {
      $data = $this->checkBundleKeyAndInfo($entity_type, $bundle);
    }

    // Always enqueue entities that have "QUEUED" status
    // but are not in the queue.
    $queued_entities = $this->publisherTracker->listTrackedEntities(PublisherTracker::QUEUED);
    $this->enqueueTrackedOrQueuedEntities($queued_entities, $entity_type, $bundle);

    if ($this->input->getOption('only-queued-entities')) {
      return $this->output->writeln(sprintf('<info>Finished enqueuing all "queued" entities.</info>'));
    }

    // Clear the depcalc cache table.
    $this->depcalcCache->deleteAll();

    // We are nullifying hashes for ALL entities to make sure
    // all dependencies are re-exported.
    $this->publisherTracker->nullifyHashes();

    if ($this->input->getOption('use-tracking-table')) {
      // We are going to re-queue ALL entities
      // (if given options of a particular entity type and bundle)
      // that have been previously published.
      $tracked_entities = $this->publisherTracker->listTrackedEntities(
        [PublisherTracker::EXPORTED, PublisherTracker::CONFIRMED],
        $entity_type
      );
      $this->enqueueTrackedOrQueuedEntities($tracked_entities, $entity_type, $bundle, TRUE);
    }
    else {
      // We are going to re-queue ALL entities that match a particular entity
      // type (and bundle) disregarding whether they have been previously
      // published or not.
      return $this->enqueueWithoutTrackingTable($entity_type, $bundle, $data, $storage);
    }

  }

  /**
   * Check entity bundle key and info.
   *
   * @param string $entity_type
   *   The entity type.
   * @param string $bundle
   *   The entity bundle.
   *
   * @return mixed
   *   Array containing bundle key and bundle info.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Exception
   */
  protected function checkBundleKeyAndInfo(string $entity_type, string $bundle) {
    $bundle_key = $this->entityTypeManager->getDefinition($entity_type)->getKey('bundle');
    if (empty($bundle_key)) {
      throw new \Exception(sprintf('The "%s" entity type does not support bundles.', $entity_type));
    }

    $bundle_info = $this->entityTypeBundleInfo->getBundleInfo($entity_type);
    if (!in_array($bundle, array_keys($bundle_info))) {
      throw new \Exception(sprintf('The bundle "%s" does not exist.', $bundle));
    }

    return [
      $bundle_key => $bundle,
    ];
  }

  /**
   * Enqueue tracked or queued entities.
   *
   * @param array $entities
   *   Entities array.
   * @param string $entity_type
   *   The entity type.
   * @param string $bundle
   *   The entity bundle.
   * @param bool|false $track
   *   Entity is tracked or not.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Exception
   */
  protected function enqueueTrackedOrQueuedEntities(array $entities, string $entity_type, string $bundle, bool $track = FALSE) {
    $count = 0;
    foreach ($entities as $enqueue_entity) {
      $entity_type_id = ($track && !empty($entity_type)) ? $entity_type : $enqueue_entity['entity_type'];
      // Enqueue entity if it is not already enqueued.
      $entity = $this->entityTypeManager->getStorage($entity_type_id)->load($enqueue_entity['entity_id']);

      if ($entity) {
        if ($track && $bundle && $bundle !== $entity->bundle()) {
          continue;
        }
        _acquia_contenthub_publisher_enqueue_entity($entity, 'update');
        $count++;
      }
      else {
        // Entity cannot be loaded, it must have been deleted.
        // Delete it from the tracking table too.
        $this->publisherTracker->delete($enqueue_entity['entity_uuid']);
        $this->output->writeln(sprintf('<warning>Could not load entity (%s,%s) : "%s". Deleted from Tracking Table.</warning>', $enqueue_entity['entity_type'], $enqueue_entity['entity_id'], $enqueue_entity['entity_uuid']));
        return;
      }
    }
    $this->output->writeln(sprintf('<info>Processed %s "queued" entities for export.</info>', $count));
  }

  /**
   * Requeue entities without using the Tracking Table.
   *
   * @param string|bool|null $entity_type
   *   The entity type.
   * @param string|bool|null $bundle
   *   The entity bundle.
   * @param array $data
   *   The data array.
   * @param \Drupal\Core\Entity\EntityStorageInterface|null $storage
   *   A storage instance.
   *
   * @return mixed
   *   The return to print in the screen.
   *
   * @throws \Exception
   */
  protected function enqueueWithoutTrackingTable($entity_type, $bundle, array $data, $storage) {
    if (empty($entity_type)) {
      return $this->output->writeln('<error>You cannot use the command without any options. Please provide at least one option.</error>');
    }
    // We are going to re-queue ALL entities that match a particular entity
    // type (and bundle) disregarding whether they have been previously
    // published or not.
    // Re-enqueue all entities.
    $entities = $storage->loadByProperties($data);
    if (empty($entities)) {
      return $this->output->writeln(sprintf('<error>No entities found for bundle = "%s".</error>', $bundle));
    }

    $count = 0;
    foreach ($entities as $entity) {
      _acquia_contenthub_publisher_enqueue_entity($entity, 'update');
      $count++;
    }

    $msg = !empty($bundle) ? "and bundle = \"{$bundle}\"." : '';
    return $this->output->writeln(sprintf(
      '<info>Processed %s entities of type = "%s" %s for export.</info>',
      $count,
      $entity_type,
      $msg
    ));
  }

}
