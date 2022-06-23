<?php

namespace Drupal\Tests\acquia_contenthub\Kernel\EventSubscriber\CdfAttributes;

use Acquia\ContentHubClient\CDF\ClientCDFObject;
use Acquia\ContentHubClient\CDFAttribute;
use Drupal\acquia_contenthub\AcquiaContentHubEvents;
use Drupal\acquia_contenthub\Event\BuildClientCdfEvent;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests that core version attribute is added to client CDF.
 *
 * @group acquia_contenthub
 * @coversDefaultClass \Drupal\acquia_contenthub\EventSubscriber\CdfAttributes\CoreVersionAttribute
 *
 * @package Drupal\Tests\acquia_contenthub\Kernel\EventSubscriber\CdfAttributes
 */
class CoreVersionAttributeTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'acquia_contenthub_server_test',
    'acquia_contenthub',
    'depcalc',
    'user',
  ];

  /**
   * Event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcher
   */
  protected $dispatcher;

  /**
   * {@inheritdoc}
   *
   * @throws \Exception
   */
  protected function setup(): void {
    parent::setUp();

    $this->dispatcher = $this->container->get('event_dispatcher');
  }

  /**
   * Tests CoreVersionAttribute event subscriber.
   *
   * Tests covers that the attribute gets added or not. Checking the value would
   * be problematic because of the different pipeline jobs.
   *
   * @throws \Exception
   */
  public function testCoreVersionCdfAttribute() {
    $cdf = ClientCDFObject::create('uuid', ['settings' => ['name' => 'test']]);

    $event = new BuildClientCdfEvent($cdf);
    $this->dispatcher->dispatch($event, AcquiaContentHubEvents::BUILD_CLIENT_CDF);
    $core_attribute = $event->getCdf()->getAttribute('drupal_version');
    $this->assertNotNull($core_attribute);
    $this->assertEquals(CDFAttribute::TYPE_ARRAY_STRING, $core_attribute->getType());
  }

}
