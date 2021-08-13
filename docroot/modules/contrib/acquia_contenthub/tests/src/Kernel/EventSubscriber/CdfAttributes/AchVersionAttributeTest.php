<?php

namespace Drupal\Tests\acquia_contenthub\Kernel\EventSubscriber\CdfAttributes;

use Acquia\ContentHubClient\CDF\ClientCDFObject;
use Acquia\ContentHubClient\CDFAttribute;
use Drupal\acquia_contenthub\AcquiaContentHubEvents;
use Drupal\acquia_contenthub\Client\ProjectVersionClient;
use Drupal\acquia_contenthub\Event\BuildClientCdfEvent;
use Drupal\acquia_contenthub\EventSubscriber\CdfAttributes\AchVersionAttribute;
use Drupal\Core\Extension\Extension;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\KernelTests\KernelTestBase;
use Prophecy\Argument;

/**
 * Tests that ACH version attribute is added to client CDF.
 *
 * @group acquia_contenthub
 * @coversDefaultClass \Drupal\acquia_contenthub\EventSubscriber\CdfAttributes\AchVersionAttribute
 *
 * @package Drupal\Tests\acquia_contenthub\Kernel\EventSubscriber\CdfAttributes
 */
class AchVersionAttributeTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
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
  protected function setUp() {
    parent::setUp();
    $this->dispatcher = $this->container->get('event_dispatcher');
  }

  /**
   * Tests AchVersionAttribute event subscriber.
   *
   * Tests covers that the attribute gets added or not. Checking the value would
   * be problematic because of the different pipeline jobs.
   *
   * @throws \Exception
   */
  public function testAchVersionCdfAttribute() {
    $cdf = ClientCDFObject::create('uuid', ['settings' => ['name' => 'test']]);
    $event = new BuildClientCdfEvent($cdf);
    $this->dispatcher->dispatch(AcquiaContentHubEvents::BUILD_CLIENT_CDF, $event);
    $ach_attribute = $event->getCdf()->getAttribute('ch_version');
    $this->assertNotNull($ach_attribute);
    $this->assertEquals(CDFAttribute::TYPE_ARRAY_STRING, $ach_attribute->getType());
  }

  /**
   * Tests AchVersionAttribute event subscriber.
   *
   * Tests covers that if version file is missing, it will throw exception.
   *
   * @throws \Exception
   */
  public function testVersionFileMissing() {
    $module_handler = $this->prophesize(ModuleHandlerInterface::class);
    $extension = $this->prophesize(Extension::class);
    $extension
      ->getPath()
      ->willReturn('');

    $module_handler
      ->getModule(Argument::any())
      ->willReturn($extension->reveal());

    $pv_client = $this->prophesize(ProjectVersionClient::class);
    $cdf = ClientCDFObject::create('uuid', ['settings' => ['name' => 'test']]);
    $event = new BuildClientCdfEvent($cdf);

    $ach_version_setter = new AchVersionAttribute($pv_client->reveal(), $module_handler->reveal());
    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('ACH YAML version file doesn\'t exist.');
    $ach_version_setter->onBuildClientCdf($event);
  }

}
