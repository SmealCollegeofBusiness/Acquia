<?php

namespace Drupal\Tests\acquia_contenthub_translations\Kernel;

use Drupal\acquia_contenthub_translations\EntityHandler\NonTranslatableEntityHandlerCompilerPass;
use Drupal\KernelTests\KernelTestBase;

/**
 * @coversDefaultClass \Drupal\acquia_contenthub_translations\AcquiaContenthubTranslationsServiceProvider
 *
 * @requires module depcalc
 *
 * @group acquia_contenthub_translations
 *
 * @package Drupal\Tests\acquia_contenthub_translations\Kernel
 */
class AcquiaContenthubTranslationsServiceProviderTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'acquia_contenthub',
    'acquia_contenthub_subscriber',
    'acquia_contenthub_translations',
    'depcalc',
    'system',
    'user',
  ];

  /**
   * Tests if the compiler pass was registered correctly.
   *
   * @covers ::register
   */
  public function testNonTranslatableEntityHandlerCompilerPass(): void {
    $passes = $this->container->getCompilerPassConfig()->getPasses();
    $found = FALSE;
    foreach ($passes as $pass) {
      if ($pass instanceof NonTranslatableEntityHandlerCompilerPass) {
        $found = TRUE;
        break;
      }
    }
    $this->assertTrue($found);
  }

}
