<?php

namespace Drupal\acquia_contenthub_subscriber;

use Acquia\ContentHubClient\Syndication\SyndicationEvents;
use Drupal\acquia_contenthub\Libs\Logging\ContentHubLoggerBase;

/**
 * Logger implementation for Content Hub imports.
 *
 * @package Drupal\acquia_contenthub_publisher
 */
class ContentHubImportLogger extends ContentHubLoggerBase {

  /**
   * {@inheritdoc}
   */
  protected function getEventErrorName(): string {
    return SyndicationEvents::IMPORT_FAILURE['name'];
  }

}
