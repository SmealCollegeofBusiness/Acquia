<?php

namespace Drupal\acquia_contenthub_subscriber;

use Drupal\acquia_contenthub\Libs\Common\ContentHubQueueBase;

/**
 * Implements an Import Queue for entities.
 */
class ContentHubImportQueue extends ContentHubQueueBase {

  /**
   * Name of the import queue.
   */
  public const QUEUE_NAME = 'acquia_contenthub_subscriber_import';

  /**
   * {@inheritdoc}
   */
  protected function getQueueName(): string {
    return self::QUEUE_NAME;
  }

}
