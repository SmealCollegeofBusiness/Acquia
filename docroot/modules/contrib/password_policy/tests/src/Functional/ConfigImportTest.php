<?php

namespace Drupal\Tests\password_policy\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests the user interface for importing configuration.
 *
 * @group config
 */
class ConfigImportTest extends BrowserTestBase {

  /**
   * Modules to install.
   *
   * @var array
   */
  public static $modules = ['config', 'password_policy'];

  /**
   * Test config import.
   *
   * Tests the config importer can import config without password_policy
   * module being enabled when there is password_policy config present in
   * sync.
   */
  public function testConfigImportDisabledModule() {
    $this->drupalLogin($this->drupalCreateUser(['synchronize configuration']));
    // Export config.
    $this->copyConfig($this->container->get('config.storage'), $this->container->get('config.storage.sync'));
    // Disable module.
    \Drupal::service('module_installer')->uninstall(['password_policy']);
    // Import config.
    $this->configImporter()->import();
  }

}
