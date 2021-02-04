<?php

namespace Drupal\Tests\acquia_contenthub\Kernel;

/**
 * Tests user export and import.
 *
 * @group acquia_contenthub
 *
 * @package Drupal\Tests\acquia_contenthub\Kernel
 */
class UserImportExportTest extends ImportExportTestBase {

  protected $fixtures = [
    [
      'cdf' => 'user/user.json',
      'expectations' => 'expectations/user/user.php',
    ],
    [
      'cdf' => 'user/user_no_email.json',
      'expectations' => 'expectations/user/user_no_email.php',
    ],
    [
      'cdf' => 'node/node_page.json',
      'expectations' => 'expectations/node/node_page_user.php',
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
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installSchema('user', ['users_data']);
  }

  /**
   * Tests "user" Drupal entity.
   *
   * @param mixed $args
   *   Arguments. @see ImportExportTestBase::contentEntityImportExport() for the
   *   details.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   *
   * @dataProvider userImportExportDataProvider
   */
  public function testUserImportExport(...$args) {
    parent::contentEntityImportExport(...$args);
  }

  /**
   * Data provider for testUserImport.
   *
   * @return array
   *   Data provider set.
   */
  public function userImportExportDataProvider() {
    $export_uuid = '5d1ba3c3-d527-4328-8fce-a6b714c5ef79';
    $user_uuid = '995f955b-08a9-4436-a0c7-1cde093ee174';

    if (version_compare(\Drupal::VERSION, '8.8.0', '<')) {
      $export_uuid = '38f023d8-b0d8-4e8c-9c06-8b547d8a0a85';
      $user_uuid = '8aa0ff11-1f3d-423e-84ac-f3ef22b10f81';
    }

    return [
      [
        0,
        [['type' => 'user', 'uuid' => 'f150c156-ef63-4f08-8d69-f15e5ee11106']],
        'user',
        'f150c156-ef63-4f08-8d69-f15e5ee11106',
      ],
      [
        1,
        [['type' => 'user', 'uuid' => 'f150c156-ef63-4f08-8d69-f15e5ee11106']],
        'user',
        'f150c156-ef63-4f08-8d69-f15e5ee11106',
      ],
      [
        2,
        [['type' => 'user', 'uuid' => $user_uuid]],
        'node',
        $export_uuid,
      ],
    ];
  }

}
