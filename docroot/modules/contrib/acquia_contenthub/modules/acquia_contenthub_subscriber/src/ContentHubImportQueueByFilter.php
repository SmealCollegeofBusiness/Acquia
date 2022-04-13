<?php

namespace Drupal\acquia_contenthub_subscriber;

use Drupal\acquia_contenthub\Libs\Common\ContentHubQueueBase;

/**
 * Implements an Import Queue for entites based on custom filters.
 */
class ContentHubImportQueueByFilter extends ContentHubQueueBase {

  /**
   * Name of the import queue.
   */
  public const QUEUE_NAME = 'acquia_contenthub_import_from_filters';

  /**
   * {@inheritdoc}
   */
  protected function getQueueName(): string {
    return self::QUEUE_NAME;
  }

}
