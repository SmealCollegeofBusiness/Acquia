<?php

namespace Drupal\Tests\acquia_contenthub\Kernel;

/**
 * Tests node imports and exports.
 *
 * @group acquia_contenthub
 * @group orca_ignore
 *
 * @package Drupal\Tests\acquia_contenthub\Kernel
 */
class NodeImportExportTest extends ImportExportTestBase {

  protected $fixtures = [
    0 => [
      'cdf' => 'node/node_page.json',
      'expectations' => 'expectations/node/node_page.php',
    ],
    1 => [
      'cdf' => 'node/node-with-embedded-image.json',
      'expectations' => 'expectations/node/node_with_embedded_image.php',
    ],
    2 => [
      'cdf' => 'node/node-translations.json',
      'expectations' => 'expectations/node/node_translations.php',
    ],
    3 => [
      'cdf' => 'node/node-translations-non-default-lang-node.json',
      'expectations' => 'expectations/node/node_translations_non_default_lang_node.php',
    ],
    4 => [
      'cdf' => 'node/node-with-links.json',
      'expectations' => 'expectations/node/node_with_links.php',
    ],
    5 => [
      'cdf' => 'node/node-with-recursive-deps.json',
      'expectations' => 'expectations/node/node_with_recursive_deps.php',
    ],
    6 => [
      'cdf' => 'node/node-with-content-moderation.json',
      'expectations' => 'expectations/node/node_with_content_moderation.php',
    ],
  ];

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'user',
    'node',
    'file',
    'taxonomy',
    'field',
    'acquia_contenthub_test',
    'content_moderation',
    'workflows',
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
    $this->installSchema('node', ['node_access']);
    $this->installEntitySchema('taxonomy_term');
    $this->installEntitySchema('file');
    $this->installSchema('file', ['file_usage']);
    $this->installEntitySchema('workflow');
    $this->installEntitySchema('content_moderation_state');
    $this->drupalSetUpCurrentUser();
  }

  /**
   * Tests Node entity import/export.
   *
   * @param mixed $args
   *   Arguments. @see ImportExportTestBase::contentEntityImportExport() for the
   *   details.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   *
   * @dataProvider nodeEntityDataProvider
   */
  public function testNodeEntity(...$args) {
    parent::contentEntityImportExport(...$args);
  }

  /**
   * Data provider for testNodeEntity.
   *
   * @return array
   *   Data sets.
   */
  public function nodeEntityDataProvider() {
    $export_uuid = '5d1ba3c3-d527-4328-8fce-a6b714c5ef79';

    if (version_compare(\Drupal::VERSION, '8.8.0', '<')) {
      $export_uuid = '38f023d8-b0d8-4e8c-9c06-8b547d8a0a85';
    }

    return [
      // Single Language, Simple Node.
      [
        0,
        [['type' => 'node', 'uuid' => $export_uuid]],
        'node',
        $export_uuid,
      ],
      // Single Language Node with a File Attached.
      [
        1,
        [
          ['type' => 'node', 'uuid' => 'f88ac4d1-50b9-4d39-b870-e97fa685e248'],
          ['type' => 'file', 'uuid' => '219ebded-70e6-459c-b29b-7686102e9bf3'],
        ],
        'node',
        'f88ac4d1-50b9-4d39-b870-e97fa685e248',
      ],
      // Multilingual Node with the default language.
      [
        2,
        [['type' => 'node', 'uuid' => 'b0137bab-a80e-4305-84fe-4d99ffd906c5']],
        'node',
        'b0137bab-a80e-4305-84fe-4d99ffd906c5',
      ],
      // Single Language Node without the default language.
      [
        3,
        [['type' => 'node', 'uuid' => 'c3910d90-e4ff-467e-9bb4-5c1b5bb43008']],
        'node',
        'c3910d90-e4ff-467e-9bb4-5c1b5bb43008',
      ],
      // Single Language Node with links.
      [
        4,
        [['type' => 'node', 'uuid' => 'fcec27d0-eb50-4ef4-8fb5-2cc736414a7f']],
        'node',
        'fcec27d0-eb50-4ef4-8fb5-2cc736414a7f',
      ],
      // Node with recursive dependencies.
      [
        5,
        [['type' => 'node', 'uuid' => 'd1aee8f8-e868-496d-a8f7-5b9a8df2de7e']],
        'node',
        'd1aee8f8-e868-496d-a8f7-5b9a8df2de7e',
      ],
      // Node with custom content moderation state.
      [
        6,
        [['type' => 'node', 'uuid' => '3405e162-4b3e-42ca-aa7b-9ba9fc78eb02']],
        'node',
        '3405e162-4b3e-42ca-aa7b-9ba9fc78eb02',
      ],
    ];
  }

}
