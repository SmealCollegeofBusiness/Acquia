<?php

namespace Drupal\Tests\acquia_contenthub\Kernel;

/**
 * Tests for Webform syndication.
 *
 * @group acquia_contenthub
 *
 * @package Drupal\Tests\acquia_contenthub\Kernel
 */
class WebformImportExportTest extends ImportExportTestBase {

  /**
   * Fixture files.
   *
   * @var array
   */
  protected $fixtures = [
    [
      'cdf' => 'webform/webform.json',
      'expectations' => 'expectations/webform/webform.php',
    ],
    // We need to test when we update the Webform alias to make sure
    // we don't have issues with dependency calculation for path aliases.
    [
      'cdf' => 'webform/webform-update-alias.json',
      'expectations' => 'expectations/webform/webform_update_alias.php',
    ],
  ];

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'system',
    'user',
    'field',
    'depcalc',
    'acquia_contenthub',
    'path_alias',
    'webform',
  ];

  /**
   * {@inheritdoc}
   *
   * @throws \Exception
   */
  public function setUp() {
    parent::setUp();

    $this->installSchema('webform', ['webform']);
    $this->installConfig('webform');
    $this->installEntitySchema('user');
    $this->installSchema('user', ['users_data']);
    $this->installEntitySchema('path_alias');

    // Necessary to avoid errors when collecting path alias dependencies.
    $this->setUpCurrentUser();
  }

  /**
   * The webform import export test.
   *
   * @dataProvider webformImportExportDataProvider
   */
  public function testWebformImportExport($delta, $update_delta, $validate_data, $export_type, $export_uuid) {
    parent::configEntityImportExport($delta, $validate_data, $export_type, $export_uuid);
    parent::configEntityImportExport($update_delta, $validate_data, $export_type, $export_uuid);
  }

  /**
   * Data provider for webformImportExportDataProvider.
   *
   * @return array
   *   Array of import and export data.
   */
  public function webformImportExportDataProvider() {
    return [
      [
        0,
        1,
        [
          [
            'type' => 'webform',
            'uuid' => '035fd1a3-c6a4-449e-937b-e333eb4693b7',
          ],
        ],
        'webform',
        '035fd1a3-c6a4-449e-937b-e333eb4693b7',
      ],
    ];
  }

}
