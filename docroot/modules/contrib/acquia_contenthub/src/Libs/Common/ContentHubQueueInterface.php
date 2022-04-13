<?php

namespace Drupal\acquia_contenthub\Libs\Common;

use Drupal\Core\Queue\QueueInterface;

/**
 * Represents a Content Hub queue.
 */
interface ContentHubQueueInterface {

  /**
   * Returns the QueueInterface object in hand.
   *
   * @return \Drupal\Core\Queue\QueueInterface
   *   The instantiated queue.
   */
  public function getQueue(): QueueInterface;

  /**
   * Handles batch process.
   *
   * Builds the batch structure, sets batch process functions and the items.
   */
  public function processQueueItems(): void;

  /**
   * Processes the batch.
   *
   * The batch worker will run through the queued items and process them
   * according to their queue method.
   *
   * @param mixed $context
   *   The batch context.
   */
  public function batchProcess(&$context): void;

  /**
   * Batch finished callback.
   *
   * @param bool $success
   *   Whether the batch process succeeded or not.
   * @param array $result
   *   Holds results.
   * @param array $operations
   *   An array of operations.
   *
   * @throws \Exception
   */
  public function batchFinished(bool $success, array $result, array $operations): void;

  /**
   * Returns the number of items in the queue.
   *
   * @return int
   *   Number of queue items.
   */
  public function getQueueCount(): int;

  /**
   * Removes the items from the queue in hand.
   */
  public function purgeQueues(): void;

}
