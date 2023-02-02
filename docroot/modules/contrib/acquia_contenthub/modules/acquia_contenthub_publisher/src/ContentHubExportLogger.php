<?php

namespace Drupal\acquia_contenthub_publisher;

use Acquia\ContentHubClient\Syndication\SyndicationEvents;
use Drupal\acquia_contenthub\Libs\Logging\ContentHubLoggerBase;

/**
 * Logger implementation for Content Hub exports.
 *
 * @package Drupal\acquia_contenthub_publisher
 */
class ContentHubExportLogger extends ContentHubLoggerBase {

  /**
   * {@inheritdoc}
   */
  protected function getEventErrorName(): string {
    return SyndicationEvents::EXPORT_FAILURE['name'];
  }

}
