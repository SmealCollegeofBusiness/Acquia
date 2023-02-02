<?php

namespace Drupal\Tests\acquia_contenthub\Unit\Libs;

use Acquia\ContentHubClient\Syndication\SyndicationEvents;
use Drupal\acquia_contenthub_subscriber\ContentHubImportLogger;

/**
 * @coversDefaultClass \Drupal\acquia_contenthub_subscriber\ContentHubImportLogger
 *
 * @group acquia_contenthub_subscriber
 *
 * @package Drupal\Tests\acquia_contenthub\Unit\Libs
 */
class ContentHubImportLoggerTest extends ContentHubLoggerTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->chLogger = new ContentHubImportLogger($this->loggerChannel, $this->eventLogger);
  }

  /**
   * {@inheritdoc}
   */
  protected function getExpectedFailureEventName(): string {
    return SyndicationEvents::IMPORT_FAILURE['name'];
  }

}
