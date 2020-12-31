<?php

namespace Drupal\Tests\acquia_telemetry\Unit;

use Drupal\acquia_telemetry\Telemetry;
use Drupal\Tests\UnitTestCase;

/**
 * @group acquia_telemetry
 *
 * @coversDefaultClass \Drupal\acquia_telemetry\Telemetry
 */
class TelemetryTest extends UnitTestCase {

  /**
   * @covers ::getExtensionVersion
   */
  public function testGetExtensionVersion() {
    $this->assertSame('1.x-dev', Telemetry::getExtensionVersion([
      'version' => '1.x-dev',
      'core_version_requirement' => '^8 || ^9',
      'core' => '8.x',
    ]));
    $this->assertSame('^8 || ^9', Telemetry::getExtensionVersion([
      'core_version_requirement' => '^8 || ^9',
      'core' => '8.x',
    ]));
    $this->assertSame('8.x', Telemetry::getExtensionVersion([
      'core' => '8.x',
    ]));
  }

}
