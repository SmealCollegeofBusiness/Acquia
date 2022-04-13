<?php

namespace Drupal\acquia_contenthub_publisher;

use Drupal\acquia_contenthub\Libs\Common\ContentHubQueueBase;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueWorkerManager;
use Drupal\Core\Queue\SuspendQueueException;
use Drupal\Core\Render\RendererInterface;

/**
 * Implements an Export Queue for Content Hub.
 */
class ContentHubExportQueue extends ContentHubQueueBase {

  /**
   * Name of the export queue.
   */
  public const QUEUE_NAME = 'acquia_contenthub_publish_export';

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * {@inheritdoc}
   */
  public function __construct(QueueFactory $queue_factory, QueueWorkerManager $queue_manager, MessengerInterface $messenger, RendererInterface $renderer) {
    parent::__construct($queue_factory, $queue_manager, $messenger);
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public function batchProcess(&$context): void {
    $queue_worker = $this->queueManager->createInstance($this->getQueueName());
    $item = $this->queue->claimItem();
    if (!$item) {
      return;
    }

    try {
      // Generating a list of entities.
      $msg_label = $this->t('(@entity_type, @entity_id)', [
        '@entity_type' => $item->data->type,
        '@entity_id' => $item->data->uuid,
      ]);

      // Process item.
      $entities_processed = $queue_worker->processItem($item->data);
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

  /**
   * {@inheritdoc}
   */
  public function batchFinished(bool $success, array $results, array $operations): void {
    parent::batchFinished($success, $results, $operations);

    // Providing a report on the items processed by the queue.
    $elements = [
      '#theme' => 'item_list',
      '#type' => 'ul',
      '#items' => $results,
    ];
    $queue_report = $this->renderer->render($elements);
    $this->messenger->addMessage($queue_report);
  }

  /**
   * {@inheritdoc}
   */
  protected function getQueueName(): string {
    return self::QUEUE_NAME;
  }

}
