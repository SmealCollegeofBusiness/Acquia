<?php

namespace Drupal\Tests\acquia_contenthub\Kernel;

/**
 * Class ConfigEntityImportWithLocalNullUuid.
 *
 * Generic Import of a Null Config Entity.
 *
 * @see acquia_contenthub_test_config_import
 *
 * @group acquia_contenthub
 *
 * @package Drupal\Tests\acquia_contenthub\Kernel
 */
class ConfigEntityImportWithLocalNullUuidTest extends ImportExportTestBase {

  /**
   * {@inheritdoc}
   */

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'user',
    'node',
    'field',
    'acquia_contenthub_test',
    'acquia_contenthub_subscriber',
    'acquia_contenthub_test_config_import',
  ];

  /**
   * Fixture files.
   *
   * @var array
   */
  protected $fixtures = [
    0 => [
      'cdf' => 'node/node_type_basic_page.json',
      'expectations' => 'expectations/node/node_type_basic_page.php',
    ],
  ];

  /**
   * {@inheritdoc}
   *
   * @throws \Exception
   */
  protected function setUp() {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installSchema('user', ['users_data']);
    $this->installEntitySchema('node');
    $this->installEntitySchema('node_type');
    $this->installSchema('node', ['node_access']);
    $this->installSchema('acquia_contenthub_subscriber', 'acquia_contenthub_subscriber_import_tracking');
    $this->installConfig('acquia_contenthub_test_config_import');
  }

  /**
   * Tests View Configuration Entity import/export.
   *
   * @param mixed $args
   *   Arguments. @see ImportExportTestBase::contentEntityImportExport() for the
   *   details.
   *
   * @throws \Exception
   *
   * @dataProvider nodeConfigEntityDataProvider
   */
  public function testNodeConfigEntity(...$args) {
    $config = \Drupal::configFactory()->getEditable('node.type.page');
    $config->set('uuid', NULL);
    $config->save();
    parent::configEntityImportExport(...$args);
  }

  /**
   * Data provider for testNodeConfigEntity.
   *
   * @return array
   *   Data provider set.
   */
  public function nodeConfigEntityDataProvider() {
    return [
      [
        0,
        [
          [
            'type' => 'node_type',
            'uuid' => '959b13f5-10b5-403b-b23d-f3e49aaa8776',
          ],
        ],
        'node_type',
        '959b13f5-10b5-403b-b23d-f3e49aaa8776',
      ],
    ];
  }

}
