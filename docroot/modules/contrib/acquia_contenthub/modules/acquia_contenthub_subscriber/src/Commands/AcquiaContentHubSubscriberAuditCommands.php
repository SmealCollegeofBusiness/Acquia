<?php

namespace Drupal\acquia_contenthub_subscriber\Commands;

use Drupal\acquia_contenthub_subscriber\SubscriberTracker;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Queue\QueueFactory;
use Drush\Commands\DrushCommands;
use Drush\Exceptions\UserAbortException;

/**
 * Drush commands for Acquia Content Hub Subscriber Audit.
 *
 * @package Drupal\acquia_contenthub_subscriber\Commands
 */
class AcquiaContentHubSubscriberAuditCommands extends DrushCommands {

  use DependencySerializationTrait;

  /**
   * The queue object.
   *
   * @var \Drupal\Core\Queue\QueueInterface
   */
  protected $queue;

  /**
   * The published entity tracker.
   *
   * @var \Drupal\acquia_contenthub_subscriber\SubscriberTracker
   */
  protected $tracker;

  /**
   * AcquiaContentHubPublisherAuditCommands constructor.
   *
   * @param \Drupal\acquia_contenthub_subscriber\SubscriberTracker $tracker
   *   The published entity tracker.
   * @param \Drupal\Core\Queue\QueueFactory $queue_factory
   *   Queue factory.
   */
  public function __construct(SubscriberTracker $tracker, QueueFactory $queue_factory) {
    $this->queue = $queue_factory->get('acquia_contenthub_subscriber_import');
    $this->tracker = $tracker;
  }

  /**
   * Audits imported entities and reimport if inconsistencies are found.
   *
   * @param string|null $entity_type_id
   *   Entity type id.
   *
   * @option reimport
   *   Reimport inconsistent entities to Content Hub.
   * @default reimport false
   *
   * @option status
   *   The export status of the entities to audit, defaults to exported.
   * Possible values: imported, queued.
   * @default status imported
   *
   * @command acquia:contenthub-audit-subscriber
   * @aliases ach-audit-subscriber, ach-as
   *
   * @throws \Exception
   */
  public function audit(string $entity_type_id = '') {
    $reimport = $this->input->getOption('reimport');
    if ($reimport) {
      $warning_message = dt('Are you sure you want to republish entities to Content Hub?');
      if ($this->io()->confirm($warning_message) == FALSE) {
        throw new UserAbortException();
      }
    }

    $status = strtolower($this->input->getOption('status'));
    $entities = $this->getContentHubTrackedEntities($status, $entity_type_id);
    $this->output()->writeln(dt('<fg=green>Auditing entities with export status = @status</>', ['@status' => $status]));

    // Setting up batch process.
    $batch = [
      'title' => dt('Checks @status entities and compares them with Content Hub.', ['@status' => $status]),
      'operations' => [],
      'finished' => '\Drupal\acquia_contenthub\AuditTrackEntities::batchFinished',
    ];

    $chunks = array_chunk($entities, 50);
    foreach ($chunks as $chunk) {
      $batch['operations'][] = [
        '\Drupal\acquia_contenthub\AuditTrackEntities::batchProcess',
        [$chunk, $reimport, 'subscriber_audit'],
      ];
    }

    // Adds the batch sets.
    batch_set($batch);
    // Start the batch process.
    drush_backend_batch_process();
  }

  /**
   * Fetch Content Hub entities from export tracking table.
   *
   * @param string $status
   *   The status of the entities to audit.
   * @param string $entity_type_id
   *   The entity type.
   *
   * @return array
   *   Array containing list of entities.
   *
   * @throws \Exception
   */
  private function getContentHubTrackedEntities(string $status, $entity_type_id = ''): array {
    switch ($status) {
      case SubscriberTracker::QUEUED:
        // If we want to queue "queued" entities, then we have to make sure the
        // export queue is empty or we might be re-queuing entities that already
        // are in the queue.
        if ($this->queue->numberOfItems() > 0) {
          throw new \Exception(dt('You cannot audit queued entities when the queue is not empty because you run the risk of re-queuing the same entities. Please retry when the queue is empty.'));
        }
      case SubscriberTracker::IMPORTED:
        $entities = $this->tracker->listTrackedEntities($status, $entity_type_id);
        break;

      default:
        throw new \Exception(dt('You can only use the following values for status: imported, queued.'));
    }

    return $entities;
  }

}
