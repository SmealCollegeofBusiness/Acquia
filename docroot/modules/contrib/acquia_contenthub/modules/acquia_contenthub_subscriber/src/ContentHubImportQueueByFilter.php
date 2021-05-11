<?php

namespace Drupal\acquia_contenthub_subscriber;

use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueWorkerManagerInterface;
use Drupal\Core\Queue\SuspendQueueException;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Implements an Import Queue for entites based on custom filters.
 */
class ContentHubImportQueueByFilter {

  use StringTranslationTrait;
  use DependencySerializationTrait;

  /**
   * The Queue Worker.
   *
   * @var \Drupal\Core\Queue\QueueWorkerManager
   */
  protected $queueWorkerManager;

  /**
   * Subscriber Import Queue from filters.
   *
   * @var \Drupal\Core\Queue\QueueInterface
   */
  protected $filterQueue;

  /**
   * ContentHubImportQueueByFilter constructor.
   *
   * @param \Drupal\Core\Queue\QueueFactory $queue_factory
   *   Drupal Queue Factory.
   * @param \Drupal\Core\Queue\QueueWorkerManagerInterface $queue_worker_manager
   *   Queue Worker manager.
   */
  public function __construct(QueueFactory $queue_factory, QueueWorkerManagerInterface $queue_worker_manager) {
    $this->queueWorkerManager = $queue_worker_manager;
    $this->filterQueue = $queue_factory->get('acquia_contenthub_import_from_filters');
  }

  /**
   * Define batch process which handles the creation of import queues.
   *
   * @param array $filter_uuids
   *   Array of cloud filter uuids.
   */
  public function process(array $filter_uuids) {
    $batch = [
      'title' => $this->t('Process all entities to be queued for import'),
      'operations' => [],
      'finished' => [[$this, 'batchFinished'], []],
    ];

    foreach ($filter_uuids as $filter_uuid) {
      $data = new \stdClass();
      $data->filter_uuid = $filter_uuid;
      $this->filterQueue->createItem($data);

      $batch['operations'][] = [[$this, 'batchProcess'], []];
    }

    batch_set($batch);
  }

  /**
   * Process the batch.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function batchProcess() {
    $filter_queue_worker = $this->queueWorkerManager->createInstance('acquia_contenthub_import_from_filters');
    if ($item = $this->filterQueue->claimItem()) {
      try {
        $filter_queue_worker->processItem($item->data);
        $this->filterQueue->deleteItem($item);
      }
      catch (SuspendQueueException $exception) {
        $context['errors'][] = $exception->getMessage();
        $context['success'] = FALSE;
        $this->filterQueue->releaseItem($item);
      }
    }
  }

  /**
   * Batch finish callback.
   *
   * This will inspect the results of the batch and will display a message to
   * indicate how the batch process ended.
   *
   * @param bool $success
   *   The result of batch process.
   * @param array $result
   *   The result of $context.
   * @param array $operations
   *   The operations that were run.
   */
  public static function batchFinished($success, array $result, array $operations) {
    if ($success) {
      \Drupal::messenger()->addMessage('Processed cloud filters.');
      return;
    }

    $error_operation = reset($operations);
    \Drupal::messenger()->addMessage(t('An error occurred while processing @operation with arguments : @args', [
      '@operation' => $error_operation[0],
      '@args' => print_r($error_operation[0], TRUE),
    ]));
  }

}
