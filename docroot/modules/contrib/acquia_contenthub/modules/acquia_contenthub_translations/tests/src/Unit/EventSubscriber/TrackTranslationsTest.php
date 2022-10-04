<?php

namespace Drupal\Tests\acquia_contenthub_translations\Unit\EventSubscriber;

use Drupal\acquia_contenthub_translations\EventSubscriber\ParseCdf\TrackTranslations;
use Drupal\Tests\UnitTestCase;

/**
 * Tests whether syndication is active while parsing the CDF.
 *
 * @coversDefaultClass \Drupal\acquia_contenthub_translations\EventSubscriber\ParseCdf\TrackTranslations
 *
 * @group acquia_contenthub_translations
 *
 * @package Drupal\Tests\acquia_contenthub_translations\Unit
 */
class TrackTranslationsTest extends UnitTestCase {

  /**
   * Track translations object.
   *
   * @var \Drupal\acquia_contenthub_translations\EventSubscriber\ParseCdf\TrackTranslations
   */
  protected $trackTranslations;

  /**
   * {@inheritDoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->trackTranslations = new TrackTranslations();
  }

  /**
   * @covers ::onParseCdf
   */
  public function testSyndicationIsActive(): void {
    $this->assertFalse(TrackTranslations::$isSyndicating);
    $this->trackTranslations->onParseCdf();
    $this->assertTrue(TrackTranslations::$isSyndicating);
  }

}
