<?php

namespace Drupal\Tests\acquia_contenthub\Unit\Libs;

use Acquia\ContentHubClient\Syndication\SyndicationEvents;
use Drupal\acquia_contenthub_publisher\ContentHubExportLogger;

/**
 * @coversDefaultClass \Drupal\acquia_contenthub_publisher\ContentHubExportLogger
 *
 * @group acquia_contenthub_publisher
 *
 * @package Drupal\Tests\acquia_contenthub\Unit\Libs
 */
class ContentHubExportLoggerTest extends ContentHubLoggerTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->chLogger = new ContentHubExportLogger($this->loggerChannel, $this->eventLogger);
  }

  /**
   * {@inheritdoc}
   */
  protected function getExpectedFailureEventName(): string {
    return SyndicationEvents::EXPORT_FAILURE['name'];
  }

}
