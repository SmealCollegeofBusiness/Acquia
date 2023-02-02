<?php

namespace Drupal\Tests\acquia_contenthub_dashboard\Kernel;

use Drupal\acquia_contenthub_dashboard\Libs\ContentHubCors;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests Acquia ContentHub Dashboard service provider.
 *
 * @group orca_ignore
 *
 * @requires module depcalc
 *
 * @package Drupal\Tests\acquia_contenthub_dashboard\Kernel
 *
 * @coversDefaultClass \Drupal\acquia_contenthub_dashboard\AcquiaContentHubDashboardServiceProvider
 */
class CHDashboardServiceProviderTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'user',
    'depcalc',
    'acquia_contenthub',
    'acquia_contenthub_subscriber',
    'acquia_contenthub_dashboard',
  ];

  /**
   * @covers ::alter
   */
  public function testAlter(): void {
    $definition = $this->container->getDefinition('http_middleware.cors');
    $this->assertSame(ContentHubCors::class, $definition->getClass(), 'Class has been changed.');
  }

}
