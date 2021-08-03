<?php

namespace Drupal\Tests\acquia_contenthub_publisher\Unit\EventSubscriber\EntityEligibility;

use Drupal\acquia_contenthub_publisher\Event\ContentHubEntityEligibilityEvent;
use Drupal\acquia_contenthub_publisher\EventSubscriber\EnqueueEligibility\IsNotParagraph;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\Tests\UnitTestCase;

/**
 * Tests for directly enqueing paragraph entities.
 *
 * @group acquia_contenthub
 *
 * @package Drupal\Tests\acquia_contenthub_publisher\Unit\EventSubscriber\EntityEligibility
 *
 * @covers \Drupal\acquia_contenthub_publisher\EventSubscriber\EnqueueEligibility\IsNotParagraph::onEnqueueCandidateEntity
 */
class IsNotParagraphTest extends UnitTestCase {

  /**
   * Tests paragraph eligibility.
   *
   * @throws \Exception
   */
  public function testParagraphEligibility() {
    // Setup our files for testing.
    $paragraph = $this->prophesize(ParagraphInterface::class);

    // This is the thing we're actually going to test.
    $subscriber = new IsNotParagraph();

    // Test insert.
    $event = new ContentHubEntityEligibilityEvent($paragraph->reveal(), 'insert');
    $subscriber->onEnqueueCandidateEntity($event);
    $this->assertFalse($event->getEligibility());

    // Test update.
    $event = new ContentHubEntityEligibilityEvent($paragraph->reveal(), 'update');
    $subscriber->onEnqueueCandidateEntity($event);
    $this->assertFalse($event->getEligibility());
  }

}
