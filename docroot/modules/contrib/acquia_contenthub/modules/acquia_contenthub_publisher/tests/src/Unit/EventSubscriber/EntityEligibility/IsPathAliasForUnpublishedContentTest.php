<?php

namespace Drupal\Tests\acquia_contenthub_publisher\Unit\EventSubscriber\EntityEligibility;

use Drupal\acquia_contenthub_publisher\EntityModeratedRevision;
use Drupal\acquia_contenthub_publisher\Event\ContentHubEntityEligibilityEvent;
use Drupal\acquia_contenthub_publisher\EventSubscriber\EnqueueEligibility\IsPathAliasForUnpublishedContent;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\node\NodeInterface;
use Drupal\path_alias\Entity\PathAlias;
use Drupal\Tests\acquia_contenthub\Unit\Helpers\LoggerMock;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\Routing\Matcher\UrlMatcherInterface;

/**
 * Tests whether path aliases should be enqueued or not.
 *
 * @group acquia_contenthub
 *
 * @package Drupal\Tests\acquia_contenthub_publisher\Unit\EventSubscriber\EntityEligibility
 *
 * @covers \Drupal\acquia_contenthub_publisher\EventSubscriber\EnqueueEligibility\IsPathAliasForUnpublishedContent::onEnqueueCandidateEntity
 */
class IsPathAliasForUnpublishedContentTest extends UnitTestCase {

  /**
   * The Url Matcher Service.
   *
   * @var \Symfony\Component\Routing\Matcher\UrlMatcherInterface
   */
  protected $matcher;

  /**
   * The Entity Moderated Revision.
   *
   * @var \Drupal\acquia_contenthub_publisher\EntityModeratedRevision
   */
  protected $entityModeratedRevision;

  /**
   * Setup function.
   */
  public function setUp(): void {
    parent::setUp();
    // Initializing Services.
    $this->matcher = $this->prophesize(UrlMatcherInterface::class);
    $this->entityModeratedRevision = $this->prophesize(EntityModeratedRevision::class);
  }

  /**
   * Test case for path alias pointing to published/unpublished content.
   *
   * @throws \Exception
   */
  public function testPathAliasForUnpublishedContent() {
    // Setup a node .
    $pnode = $this->prophesize(NodeInterface::class);
    $pnode->id()->willReturn(1);
    $node = $pnode->reveal();
    // First call returns "published", second call "unpublished".
    $this->entityModeratedRevision->isPublishedRevision($node)->willReturn(TRUE, FALSE);

    // Setup pathalias pointing to the node.
    $ppathalias = $this->prophesize(PathAlias::class);
    $ppathalias->getPath()->willReturn('node/' . $node->id());
    $pathalias = $ppathalias->reveal();

    // Setup UrlMatcher Service.
    $params = [
      '_raw_variables' => new class {

        /**
         * Anonymous keys() function.
         *
         * @return string[]
         *   Array of entity keys.
         */
        public function keys(): array {
          return [
            'node',
          ];
        }

      },
      'node' => $node,
    ];
    $this->matcher->match($pathalias->getPath())->willReturn($params);

    // Event Subscriber.
    $subscriber = new IsPathAliasForUnpublishedContent($this->matcher->reveal(), $this->entityModeratedRevision->reveal(), new LoggerMock());

    // Test that pathalias is eligible if it points to a published node.
    $event = new ContentHubEntityEligibilityEvent($pathalias, 'insert');
    $subscriber->onEnqueueCandidateEntity($event);
    $this->assertTrue($event->getEligibility());
    $this->assertFalse($event->isPropagationStopped());

    // Test that pathalias is not eligible if it points to an unpublished node.
    $event = new ContentHubEntityEligibilityEvent($pathalias, 'update');
    $subscriber->onEnqueueCandidateEntity($event);
    $this->assertFalse($event->getEligibility());
    $this->assertTrue($event->isPropagationStopped());
  }

  /**
   * Tests exception is raised.
   *
   * And error is logged while checking matching entities.
   */
  public function testExceptionForPathAliases() {
    $logger = new LoggerMock();
    // Setup pathalias pointing to the node.
    $ppathalias = $this->prophesize(PathAlias::class);
    $ppathalias->uuid()->willReturn('random-uuid');
    $ppathalias->getPath()->willReturn('node/1');
    $pathalias = $ppathalias->reveal();
    $exception_message = 'Matcher is not valid.';
    $this->matcher->match($pathalias->getPath())->willThrow(new \Exception($exception_message));

    // Event Subscriber.
    $subscriber = new IsPathAliasForUnpublishedContent($this->matcher->reveal(), $this->entityModeratedRevision->reveal(), $logger);

    // Test that exception is raised and error is logged.
    $event = new ContentHubEntityEligibilityEvent($pathalias, 'insert');
    $this->assertEmpty($logger->getLogMessages());
    $subscriber->onEnqueueCandidateEntity($event);
    $log_messages = $logger->getLogMessages();
    $this->assertNotEmpty($log_messages);
    // Assert there are errors in log messages.
    $this->assertNotEmpty($log_messages[RfcLogLevel::ERROR]);
    // Assert first message in errors.
    $this->assertEquals(
      'Following error occurred while trying to get the matching entity for path alias with uuid: random-uuid. Error: ' . $exception_message,
      $log_messages[RfcLogLevel::ERROR][0]
    );
    $this->assertFalse($event->getEligibility());
    $this->assertTrue($event->isPropagationStopped());
  }

}
