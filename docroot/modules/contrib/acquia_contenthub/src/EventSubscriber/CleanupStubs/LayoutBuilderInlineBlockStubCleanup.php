<?php

namespace Drupal\acquia_contenthub\EventSubscriber\CleanupStubs;

use Drupal\acquia_contenthub\AcquiaContentHubEvents;
use Drupal\acquia_contenthub\Event\CleanUpStubsEvent;
use Drupal\block_content\BlockContentInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Cleans up LB block stubs after import.
 *
 * @package Drupal\acquia_contenthub\EventSubscriber\CleanupStubs
 */
class LayoutBuilderInlineBlockStubCleanup implements EventSubscriberInterface {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Block content storage.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * LayoutBuilderInlineBlockStubCleanup constructor.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   DB connection service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(Connection $database, EntityTypeManagerInterface $entityTypeManager) {
    $this->database = $database;
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * Get subscribed events and add onStubsCleanup for default stubs.
   *
   * @return array
   *   Array of $events.
   */
  public static function getSubscribedEvents() {
    $events[AcquiaContentHubEvents::CLEANUP_STUBS][] = ['onStubsCleanup', 11];
    return $events;
  }

  /**
   * By default, we delete all stubs.
   *
   * If LB inline block is created. LB inline block creation logic duplicates
   * blocks and ACH ends up referencing the wrong revision. We delete the
   * orphaned block.
   *
   * Wrong reference is only a problem when the original entity has a recursive
   * dependency (AcquiaContentHubEvents::IMPORT_FAILURE gets dispatched) and is
   * being saved at the first time.
   *
   * @param \Drupal\acquia_contenthub\Event\CleanUpStubsEvent $event
   *   The cleanup stubs $event.
   *
   * @see \Drupal\acquia_contenthub\EventSubscriber\CleanupStubs\DefaultStubCleanup
   * @see \Drupal\acquia_contenthub\StubTracker::cleanUp
   */
  public function onStubsCleanup(CleanUpStubsEvent $event) {
    if ($event->getEntity()->getEntityTypeId() !== 'block_content') {
      return;
    }

    if (!$this->database->schema()->tableExists('inline_block_usage')) {
      // If table not exist Layout Builder not installed.
      return;
    }

    /** @var \Drupal\block_content\BlockContentInterface $block */
    $block = $event->getEntity();

    $query = $this
      ->database
      ->select('inline_block_usage', 't')
      ->fields('t', ['block_content_id'])
      ->condition('block_content_id', $block->id());

    $count = $query->countQuery()->execute()->fetchField();

    // If inline block reference exists, then stop propagation and delete
    // duplicate.
    if ($count) {
      $this->deleteDuplicatedBlockEntity($block);
      $event->stopPropagation();
    }
  }

  /**
   * Looks for duplicate and deletes it.
   *
   * @param \Drupal\block_content\BlockContentInterface $block
   *   Block content entity.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function deleteDuplicatedBlockEntity(BlockContentInterface $block) {
    // If the changed and info value is the same, then blocks are duplicates.
    // Since this scenario is an edge case matching these should be enough.
    $blocks = $this
      ->entityTypeManager
      ->getStorage('block_content')
      ->loadByProperties([
        'info' => $block->get('info')->value,
        'changed' => $block->get('changed')->value,
      ]);

    foreach ($blocks as $key => $entity) {
      if ($key == $block->id()) {
        continue;
      }

      $entity->delete();
    }
  }

}
