<?php

namespace Drupal\Tests\acquia_contenthub\Kernel;

use Drupal\Tests\acquia_contenthub\Kernel\Core\FileSystemTrait;

/**
 * Tests that files are properly exported and imported.
 *
 * @group acquia_contenthub
 *
 * @package Drupal\Tests\acquia_contenthub\Kernel
 */
class FileImportExportTest extends ImportExportTestBase {

  use FileSystemTrait;

  /**
   * {@inheritdoc}
   */
  protected $fixtures = [
    [
      'cdf' => 'file/node-with-file-reference.json',
      'expectations' => 'expectations/file/reference_1.php',
    ],
    [
      'cdf' => 'file/node-with-multiple-files.json',
      'expectations' => 'expectations/file/reference_2.php',
    ],
  ];

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'system',
    'user',
    'node',
    'file',
    'field',
    'depcalc',
    'acquia_contenthub',
    'acquia_contenthub_test',
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
    $this->fileSystemSetUp();
  }

  /**
   * Tests import/export of node with file.
   *
   * @param int $delta
   *   Delta.
   * @param array $validate_data
   *   Data.
   * @param string $export_type
   *   Entity type ID.
   * @param string $export_uuid
   *   Uuid.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Exception
   *
   * @dataProvider fileImportExportDataProvider
   */
  public function testFileImportExport($delta, array $validate_data, $export_type, $export_uuid) {
    parent::contentEntityImportExport($delta, $validate_data, $export_type, $export_uuid);
    /** @var \Drupal\Core\Entity\EntityRepository $repository */
    $repository = \Drupal::service('entity.repository');
    foreach ($validate_data as $datum) {
      if (!isset($datum['file'])) {
        continue;
      }

      $entity_type = $datum['type'];
      $validate_uuid = $datum['uuid'];
      $file_fixture = $datum['file'];
      $file = $repository->loadEntityByUuid($entity_type, $validate_uuid);
      $this->assertFileExists($file->getFileUri());
      $imported_file_content = file_get_contents($file->getFileUri());
      $this->assertStringEqualsFile(sprintf($file_fixture, $this->getPathToFixtures()), $imported_file_content);
    }
  }

  /**
   * Data provider for testFileImportExport.
   *
   * @return array
   *   Test data sets.
   */
  public function fileImportExportDataProvider() {
    $export_uuid_node = [
      'b5ca43f8-adce-411d-a161-aff4eeda5e36',
      '386db454-41ab-4628-b2b1-caae809569a7',
    ];
    $export_uuid_files = [
      'b88f7854-0b14-4993-88ab-f14f9e24c4b3',
      'a5b12985-69f7-46d0-8e3f-a9e940eab99f',
      'f8a90411-8bc0-4e49-a4e5-211add30c654',
    ];

    if (version_compare(\Drupal::VERSION, '8.8.0', '<')) {
      $export_uuid_node = [
        '88be98ad-e1fd-4d81-929a-6a1a444e44ee',
        '6b600947-877a-4512-b054-749ffa1ec821',
      ];
      $export_uuid_files = [
        '0a70f867-cc1f-4eb3-b025-bf6ee9158425',
        'ff3d6699-52d7-4586-ad24-cca8f1b9459b',
        '5021d85b-6784-4185-8b25-d2db32dd5483',
      ];
    }

    return [
      [
        0,
        [
          [
            'type' => 'file',
            'uuid' => $export_uuid_files[0],
            'file' => '%s/misc/1.txt',
          ],
        ],
        'node',
        $export_uuid_node['0'],
      ],
      [
        1,
        [
          [
            'type' => 'file',
            'uuid' => $export_uuid_files[1],
            'file' => '%s/misc/1.txt',
          ],
          [
            'type' => 'file',
            'uuid' => $export_uuid_files[2],
            'file' => '%s/misc/2.txt',
          ],
        ],
        'node',
        $export_uuid_node['1'],
      ],
    ];
  }

  /**
   * Returns path to fixtures directory.
   *
   * @return string
   *   Path to fixtures directory.
   */
  protected function getPathToFixtures() {
    $path_to_fixtures = sprintf('%s/tests/fixtures',
      drupal_get_path('module', 'acquia_contenthub')
    );
    return $path_to_fixtures;
  }

}
