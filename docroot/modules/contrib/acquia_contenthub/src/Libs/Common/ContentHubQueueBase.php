<?php

namespace Drupal\acquia_contenthub\Libs\Common;

use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueInterface;
use Drupal\Core\Queue\QueueWorkerManager;
use Drupal\Core\Queue\SuspendQueueException;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Boilerplate for Content Hub Queue implementations.
 */
abstract class ContentHubQueueBase implements ContentHubQueueInterface {

  use StringTranslationTrait;
  use DependencySerializationTrait;

  /**
   * An instantiated queue.
   *
   * @var \Drupal\Core\Queue\QueueInterface
   */
  protected $queue;

  /**
   * The queue worker.
   *
   * @var \Drupal\Core\Queue\QueueWorkerManager
   */
  protected $queueManager;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Constructs a Content Hub queue object.
   *
   * @param \Drupal\Core\Queue\QueueFactory $queue_factory
   *   The queue factory.
   * @param \Drupal\Core\Queue\QueueWorkerManager $queue_manager
   *   The queue manager.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   */
  public function __construct(QueueFactory $queue_factory, QueueWorkerManager $queue_manager, MessengerInterface $messenger) {
    $this->queue = $queue_factory->get($this->getQueueName());
    $this->queueManager = $queue_manager;
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public function getQueue(): QueueInterface {
    return $this->queue;
  }

  /**
   * {@inheritdoc}
   */
  public function processQueueItems($items = []): void {
    $batch = [
      'title' => $this->t('Process @queue queue', ['@queue' => $this->getQueueName()]),
      'operations' => [],
      'finished' => [[$this, 'batchFinished'], []],
    ];

    for ($i = 0; $i < $this->getQueueCount(); $i++) {
      $batch['operations'][] = [[$this, 'batchProcess'], []];
    }

    batch_set($batch);
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
      $queue_worker->processItem($item->data);
      $this->queue->deleteItem($item);
    }
    catch (SuspendQueueException $exception) {
      $context['errors'][] = $exception->getMessage();
      $context['success'] = FALSE;
      $this->queue->releaseItem($item);
    }
    catch (EntityStorageException $exception) {
      $context['errors'][] = $exception->getMessage();
      $context['success'] = FALSE;
      $this->queue->releaseItem($item);
    }

  }

  /**
   * {@inheritdoc}
   */
  public function batchFinished(bool $success, array $result, array $operations): void {
    if ($success) {
      $this->messenger->addMessage($this->t('Successfully processed all items.'));
      return;
    }
    $error_operation = reset($operations);
    $this->messenger->addMessage($this->t('An error occurred while processing @operation with arguments : @args', [
      '@operation' => $error_operation[0],
      '@args' => print_r($error_operation[0], TRUE),
    ]));
  }

  /**
   * {@inheritdoc}
   */
  public function getQueueCount(): int {
    return $this->queue->numberOfItems();
  }

  /**
   * {@inheritdoc}
   */
  public function purgeQueues(): void {
    $this->queue->deleteQueue();
  }

  /**
   * Return the name of the Content Hub queue.
   *
   * @return string
   *   The queue name.
   */
  abstract protected function getQueueName(): string;

}
