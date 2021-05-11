<?php

namespace Drupal\Tests\acquia_contenthub_publisher\Unit\EventSubscriber\EntityEligibility;

use Drupal\acquia_contenthub\Plugin\FileSchemeHandler\FileSchemeHandlerManagerInterface;
use Drupal\acquia_contenthub_publisher\Event\ContentHubEntityEligibilityEvent;
use Drupal\acquia_contenthub_publisher\EventSubscriber\EnqueueEligibility\FileSchemeIsSupported;
use Drupal\file\FileInterface;
use Drupal\Tests\UnitTestCase;

/**
 * Tests unsupported file schemes.
 *
 * @group acquia_contenthub
 *
 * @package Drupal\Tests\acquia_contenthub_publisher\Unit\EventSubscriber\EntityEligibility
 *
 * @covers \Drupal\acquia_contenthub_publisher\EventSubscriber\EnqueueEligibility\FileSchemeIsSupported::onEnqueueCandidateEntity
 */
class UnsupportedFileSchemeTest extends UnitTestCase {

  public function testFileSchemeEligibility() {
    // Setup our files for testing.
    $supported_file = $this->prophesize(FileInterface::class);
    $unsupported_file = $this->prophesize(FileInterface::class);

    // Setup our manager's response to our files.
    $manager = $this->prophesize(FileSchemeHandlerManagerInterface::class);
    $manager->isSupportedFileScheme($supported_file->reveal())->willReturn(TRUE);
    $manager->isSupportedFileScheme($unsupported_file->reveal())->willReturn(FALSE);

    // This is the thing we're actually going to test.
    $subscriber = new FileSchemeIsSupported($manager->reveal());

    // Test supported files.
    $supported_event = new ContentHubEntityEligibilityEvent($supported_file->reveal(), 'insert');
    $subscriber->onEnqueueCandidateEntity($supported_event);
    $this->assertTrue($supported_event->getEligibility());

    // Test unsupported files.
    $unsupported_event = new ContentHubEntityEligibilityEvent($unsupported_file->reveal(), 'insert');
    $subscriber->onEnqueueCandidateEntity($unsupported_event);
    $this->assertFalse($unsupported_event->getEligibility());
  }

}
