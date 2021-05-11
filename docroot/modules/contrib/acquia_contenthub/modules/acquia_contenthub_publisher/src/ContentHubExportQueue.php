<?php

namespace Drupal\acquia_contenthub_publisher;

use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueWorkerManager;
use Drupal\Core\Queue\SuspendQueueException;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Implements an Export Queue for Content Hub.
 */
class ContentHubExportQueue {

  use StringTranslationTrait;
  use DependencySerializationTrait;

  /**
   * The Publisher Exporting Queue.
   *
   * @var \Drupal\Core\Queue\QueueInterface
   */
  protected $queue;

  /**
   * The Queue Worker.
   *
   * @var \Drupal\Core\Queue\QueueWorkerManager
   */
  protected $queueManager;

  /**
   * The messenger object.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * {@inheritdoc}
   */
  public function __construct(QueueFactory $queue_factory, QueueWorkerManager $queue_manager, MessengerInterface $messenger) {
    $this->queue = $queue_factory->get('acquia_contenthub_publish_export');
    $this->queueManager = $queue_manager;
    $this->messenger = $messenger;
  }

  /**
   * Obtains the number of items in the export queue.
   *
   * @return mixed
   *   The number of items in the export queue.
   */
  public function getQueueCount() {
    return $this->queue->numberOfItems();
  }

  /**
   * Remove all the publish export queues.
   */
  public function purgeQueues() {
    $this->queue->deleteQueue();
  }

  /**
   * Process all queue items with batch API.
   */
  public function processQueueItems() {
    // Create batch which collects all the specified queue items and process
    // them one after another.
    $batch = [
      'title' => $this->t("Process Content Hub Export Queue"),
      'operations' => [],
      'finished' => [[$this, 'batchFinished'], []],
    ];

    // Count number of the items in this queue, create enough batch operations.
    for ($i = 0; $i < $this->getQueueCount(); $i++) {
      // Create batch operations.
      $batch['operations'][] = [[$this, 'batchProcess'], []];
    }

    // Adds the batch sets.
    batch_set($batch);
  }

  /**
   * Common batch processing callback for all operations.
   *
   * @param mixed $context
   *   The context array.
   */
  public function batchProcess(&$context) {
    $queueWorker = $this->queueManager->createInstance('acquia_contenthub_publish_export');

    // Get a queued item.
    if ($item = $this->queue->claimItem()) {
      try {
        // Generating a list of entities.
        $msg_label = $this->t('(@entity_type, @entity_id)', [
          '@entity_type' => $item->data->type,
          '@entity_id' => $item->data->uuid,
        ]);

        // Process item.
        $entities_processed = $queueWorker->processItem($item->data);
        if ($entities_processed == FALSE) {
          // Indicate that the item could not be processed.
          if ($entities_processed === FALSE) {
            $message = $this->t('There was an error processing entities: @entities and their dependencies. The item has been sent back to the queue to be processed again later. Check your logs for more info.', [
              '@entities' => $msg_label,
            ]);
          }
          else {
            $message = $this->t('No processing was done for entities: @entities and their dependencies. The item has been sent back to the queue to be processed again later. Check your logs for more info.', [
              '@entities' => $msg_label,
            ]);
          }
          $context['message'] = $message->jsonSerialize();
          $context['results'][] = $message->jsonSerialize();
        }
        else {
          // If everything was correct, delete processed item from the queue.
          $this->queue->deleteItem($item);

          // Creating a text message to present to the user.
          $message = $this->t('Processed entities: @entities and their dependencies (@count @label sent).', [
            '@entities' => $msg_label,
            '@count' => $entities_processed,
            '@label' => $entities_processed == 1 ? $this->t('entity') : $this->t('entities'),
          ]);
          $context['message'] = $message->jsonSerialize();
          $context['results'][] = $message->jsonSerialize();
        }

      }
      catch (SuspendQueueException $e) {
        // If there was an Exception thrown because of an error
        // Releases the item that the worker could not process.
        // Another worker can come and process it.
        $this->queue->releaseItem($item);
      }
    }
  }

  /**
   * Batch finished callback.
   *
   * @param bool $success
   *   Whether the batch process succeeded or not.
   * @param array $results
   *   The results array.
   * @param array $operations
   *   An array of operations.
   */
  public function batchFinished($success, array $results, array $operations) {
    if ($success) {
      $this->messenger->addMessage(t("The contents are successfully exported."));
    }
    else {
      $error_operation = reset($operations);
      $this->messenger->addMessage(t('An error occurred while processing @operation with arguments : @args', [
        '@operation' => $error_operation[0],
        '@args' => print_r($error_operation[0], TRUE),
      ]
      ));
    }

    // Providing a report on the items processed by the queue.
    $elements = [
      '#theme' => 'item_list',
      '#type' => 'ul',
      '#items' => $results,
    ];
    $queue_report = \Drupal::service('renderer')->render($elements);
    $this->messenger->addMessage($queue_report);
  }

}
