<?php

namespace Drupal\Tests\acquia_contenthub\Unit\EventSubscriber\CdfAttributes;

use Acquia\ContentHubClient\CDF\ClientCDFObject;
use Acquia\ContentHubClient\CDFAttribute;
use Drupal\acquia_contenthub\AcquiaContentHubEvents;
use Drupal\acquia_contenthub\Event\BuildClientCdfEvent;
use Drupal\acquia_contenthub\EventSubscriber\CdfAttributes\PublisherSubscriberStatusCdfAttribute;
use Drupal\acquia_contenthub\PubSubModuleStatusChecker;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * Tests the PublisherSubscriberStatusCdfAttribute.
 *
 * @group acquia_contenthub
 *
 * @package Drupal\Tests\acquia_contenthub\Unit\EventSubscriber\CdfAttributes
 *
 * @covers \Drupal\acquia_contenthub\EventSubscriber\CdfAttributes\PublisherSubscriberStatusCdfAttribute
 */
class PublisherSubscriberStatusCdfAttributeTest extends UnitTestCase {

  /**
   * Event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcher
   */
  protected $dispatcher;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->dispatcher = new EventDispatcher();
    $module_handler_service = $this->getMockBuilder(ModuleHandler::class)
      ->disableOriginalConstructor()
      ->getMock();

    $checker = new PubSubModuleStatusChecker($module_handler_service);

    $this->dispatcher->addSubscriber(new PublisherSubscriberStatusCdfAttribute($checker));
  }

  /**
   * Tests 'subscriber' and 'publisher' attributes population.
   */
  public function testOnPopulateAttributes() {
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $isPublisher = TRUE;
    $isSubscriber = FALSE;

    $cdf = $this->getMockBuilder(ClientCDFObject::class)
      ->disableOriginalConstructor()
      ->getMock();

    $publiherCdfAttribute = $this->getMockBuilder(CDFAttribute::class)
      ->disableOriginalConstructor()
      ->getMock();

    $subscriberCdfAttribute = $this->getMockBuilder(CDFAttribute::class)
      ->disableOriginalConstructor()
      ->getMock();

    $publiherCdfAttribute->method('getType')->willReturn(CDFAttribute::TYPE_BOOLEAN);
    $publiherCdfAttribute->method('getValue')->willReturn($isPublisher);
    $subscriberCdfAttribute->method('getType')->willReturn(CDFAttribute::TYPE_BOOLEAN);
    $subscriberCdfAttribute->method('getValue')->willReturn($isSubscriber);

    $cdf->method('getAttribute')
      ->will(
        $this->returnValueMap(
          [
            ['publisher', $publiherCdfAttribute],
            ['subscriber', $subscriberCdfAttribute],
          ]
        )
      );

    $event = $this->getMockBuilder(BuildClientCdfEvent::class)
      ->disableOriginalConstructor()
      ->getMock();

    $event->method('isPropagationStopped')->willReturn(TRUE);

    $event->method('getCdf')->willReturn($cdf);

    $this->dispatcher->dispatch(AcquiaContentHubEvents::BUILD_CLIENT_CDF, $event);
    $cdf = $event->getCdf();
    $publisher = $cdf->getAttribute('publisher');
    $subscriber = $cdf->getAttribute('subscriber');

    $this->assertEquals(CDFAttribute::TYPE_BOOLEAN, $publisher->getType());
    $this->assertEquals($isPublisher, $publisher->getValue());
    $this->assertEquals(CDFAttribute::TYPE_BOOLEAN, $subscriber->getType());
    $this->assertEquals($isSubscriber, $subscriber->getValue());
  }

}
