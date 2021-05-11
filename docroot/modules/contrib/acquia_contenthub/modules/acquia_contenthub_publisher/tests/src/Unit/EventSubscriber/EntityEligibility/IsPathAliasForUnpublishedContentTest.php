<?php

namespace Drupal\Tests\acquia_contenthub_publisher\Unit\EventSubscriber\EntityEligibility;

use Drupal\acquia_contenthub_publisher\EntityModeratedRevision;
use Drupal\acquia_contenthub_publisher\Event\ContentHubEntityEligibilityEvent;
use Drupal\acquia_contenthub_publisher\EventSubscriber\EnqueueEligibility\IsPathAliasForUnpublishedContent;
use Drupal\node\NodeInterface;
use Drupal\path_alias\Entity\PathAlias;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\Routing\Matcher\UrlMatcherInterface;

/**
 * Tests unsupported file schemes.
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
  public function setup() {
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

        public function keys() {
          return [
            'node',
          ];
        }

      },
      'node' => $node,
    ];
    $this->matcher->match($pathalias->getPath())->willReturn($params);

    // Event Subscriber.
    $subscriber = new IsPathAliasForUnpublishedContent($this->matcher->reveal(), $this->entityModeratedRevision->reveal());

    // Test that pathalias is eligible if it points to a published node.
    $event = new ContentHubEntityEligibilityEvent($pathalias, 'insert');
    $subscriber->onEnqueueCandidateEntity($event);
    $this->assertTrue($event->getEligibility());

    // Test that pathalias is not eligible if it points to an unpublished node.
    $event = new ContentHubEntityEligibilityEvent($pathalias, 'update');
    $subscriber->onEnqueueCandidateEntity($event);
    $this->assertFalse($event->getEligibility());
  }

}
