<?php

namespace Drupal\Tests\acquia_contenthub\Kernel;

/**
 * Tests redirect export and import.
 *
 * @group acquia_contenthub
 *
 * @package Drupal\Tests\acquia_contenthub\Kernel
 */
class RedirectImportExportTest extends ImportExportTestBase {

  /**
   * {@inheritdoc}
   */
  protected $fixtures = [
    [
      'cdf' => 'redirect/node_redirect.json',
      'expectations' => 'expectations/redirect/redirect_with_path_alias_destination.php',
    ],
    [
      'cdf' => 'redirect/node_redirect.json',
      'expectations' => 'expectations/redirect/redirect_with_internal_node_path.php',
    ],
    [
      'cdf' => 'redirect/node_redirect.json',
      'expectations' => 'expectations/redirect/redirect_with_entity_node_path.php',
    ],
    [
      'cdf' => 'redirect/redirect.json',
      'expectations' => 'expectations/redirect/redirect_with_internal_route.php',
    ],
  ];

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'system',
    'user',
    'node',
    'menu_link_content',
    'link',
    'field',
    'depcalc',
    'acquia_contenthub',
    'redirect',
    'path_alias',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installSchema('user', ['users_data']);
    $this->installEntitySchema('node');
    $this->installSchema('node', ['node_access']);
    $this->installEntitySchema('redirect');
    $this->installEntitySchema('menu_link_content');
    $this->drupalSetUpCurrentUser();
  }

  /**
   * Tests "redirect" Drupal entity.
   *
   * @param int $delta
   *   Fixture delta.
   * @param array $validate_data
   *   Data.
   * @param string $export_type
   *   Exported entity type.
   * @param string $export_uuid
   *   Entity UUID.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   *
   * @dataProvider redirectImportExportDataProvider
   */
  public function testRedirectImportExport(int $delta, array $validate_data, $export_type, $export_uuid) {
    parent::contentEntityImportExport($delta, $validate_data, $export_type, $export_uuid);

    if ($export_type === 'node') {
      $this->assertNodeRevisionCount($export_uuid);
    }

  }

  /**
   * Check if nodes with circular dependencies have no stub revisions created.
   *
   * @param string $uuid
   *   The node UUID to check.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function assertNodeRevisionCount(string $uuid) {
    /** @var \Drupal\Node\NodeStorageInterface $node_storage */
    $node_storage = $this->entityTypeManager->getStorage('node');
    /** @var \Drupal\node\Entity\Node $node */
    $node = current($node_storage->loadByProperties(['uuid' => $uuid]));
    $vids = $node_storage->revisionIds($node);
    $this->assertEqual(count($vids), 1, "No revisions were created from stubs.");
  }

  /**
   * Data provider for testUserImport.
   *
   * @return array
   *   Data provider set.
   */
  public function redirectImportExportDataProvider() {

    return [
      [
        0,
        [[
          'type' => 'redirect',
          'uuid' => 'a1d183ff-f1de-433c-8a75-21450a9c868b',
        ],
        ],
        'node',
        '03cf6ebe-f0b2-4217-9783-82d7125ef460',
      ],
      [
        1,
        [[
          'type' => 'redirect',
          'uuid' => '610bdde1-f19a-4afa-825b-d32a0147d87c',
        ],
        ],
        'node',
        '03cf6ebe-f0b2-4217-9783-82d7125ef460',
      ],
      [
        2,
        [[
          'type' => 'redirect',
          'uuid' => '0612f69c-5968-4b40-9c1d-48a549b56325',
        ],
        ],
        'node',
        '03cf6ebe-f0b2-4217-9783-82d7125ef460',
      ],
      [
        3,
        [[
          'type' => 'redirect',
          'uuid' => '73cc40e6-af4a-45d4-915d-26503d416bf2',
        ],
        ],
        'redirect',
        '73cc40e6-af4a-45d4-915d-26503d416bf2',
      ],
    ];
  }

}
