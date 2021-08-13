<?php

namespace Drupal\Tests\acquia_contenthub\Kernel;

use Drupal\acquia_contenthub\AcquiaContentHubEvents;
use Drupal\redirect\Entity\Redirect;

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
    'acquia_contenthub_subscriber',
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
    $this->installSchema('acquia_contenthub_subscriber', 'acquia_contenthub_subscriber_import_tracking');
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
   * Tests import of a redirect that already has a local match.
   *
   * @param int $delta
   *   Fixture delta.
   * @param array $validate_data
   *   Data.
   * @param string $export_type
   *   Exported entity type.
   * @param string $export_uuid
   *   Entity UUID.
   * @param string $source
   *   Source for the duplicate.
   * @param string $path
   *   Path for the duplicate.
   * @param string $language
   *   Langcode for the duplicate.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   *
   * @dataProvider testDuplicateRedirectImportDataProvider
   */
  public function testDuplicateRedirectImport(
    int $delta,
    array $validate_data,
    string $export_type,
    string $export_uuid,
    string $source,
    string $path,
    string $language
    ) {
    $this->createDuplicateRedirect($export_uuid, $source, $path, $language);
    parent::contentEntityImportExport($delta, $validate_data, $export_type, $export_uuid);
  }

  /**
   * Tests if stubs are cleaned when there's an exception importing redirects.
   *
   * @param int $delta
   *   Fixture delta.
   * @param array $validate_data
   *   Data.
   * @param string $export_type
   *   Exported entity type.
   * @param string $export_uuid
   *   Entity UUID.
   * @param string $error_stub_uuid
   *   UUID of stub that should throw an exception.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   *
   * @dataProvider testExceptionOnRedirectImportDataProvider
   */
  public function testExceptionOnRedirectImport(
    int $delta,
    array $validate_data,
    $export_type,
    $export_uuid,
    $error_stub_uuid
  ) {

    $this->forcePreSaveExceptionByUuid($error_stub_uuid);

    try {
      parent::contentEntityImportExport($delta, $validate_data, $export_type, $export_uuid);
    }
    catch (\Exception $e) {
      $node = $this->loadByUuid($export_type, $export_uuid);
      $this->assertEmpty($node, 'Node stub cleaned up as expected');
      return;
    }

    // Didn't use $this->expectException() because we still need to make
    // assertions after the exception is thrown to prove stubs are deleted
    // and $this->expectException() causes failed assertions (which are
    // treated as exceptions) to pass.
    $this->fail('An exception was expected and not thrown.');
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
    $node = $this->loadByUuid('node', $uuid);
    $vids = $node_storage->revisionIds($node);
    $this->assertEquals(count($vids), 1, 'No revisions were created from stubs.');
  }

  /**
   * Creates a duplicate redirect based on fixture data.
   *
   * @param string $uuid
   *   UUID for the duplicate.
   * @param string $source
   *   Source for the duplicate.
   * @param string $path
   *   Path for the duplicate.
   * @param string $language
   *   Langcode for the duplicate.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function createDuplicateRedirect(
    string $uuid,
    string $source,
    string $path,
    string $language
  ) {
    $redirect = Redirect::create([
      'uuid' => $uuid,
    ]);
    $redirect->setSource($source);
    $redirect->setRedirect($path);
    $redirect->setLanguage($language);
    $redirect->save();
  }

  /**
   * Adds a listener to force an exception by UUID.
   *
   * @param string $error_stub_uuid
   *   The UUID.
   */
  protected function forcePreSaveExceptionByUuid(string $error_stub_uuid) {
    /** @var \Symfony\Component\EventDispatcher\EventDispatcherInterface $dispatcher */
    $dispatcher = $this->container->get('event_dispatcher');
    $dispatcher->addListener(AcquiaContentHubEvents::PRE_ENTITY_SAVE,
      function ($event) use ($error_stub_uuid) {
        if ($event->getCdf()->getUuid() === $error_stub_uuid) {
          throw new \Exception('Test exception entity uuid: ' . $error_stub_uuid);
        }
      }
    );
  }

  /**
   * Data provider for testRedirectImportExport.
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

  /**
   * Data provider for testDuplicateRedirectImport.
   *
   * @return array
   *   Data provider set.
   */
  public function testDuplicateRedirectImportDataProvider() {
    return [
      [
        3,
        [[
          'type' => 'redirect',
          'uuid' => '73cc40e6-af4a-45d4-915d-26503d416bf2',
        ],
        ],
        'redirect',
        '73cc40e6-af4a-45d4-915d-26503d416bf2',
        'check-internal-route',
        'internal:/node/add',
        'und',
      ],
    ];
  }

  /**
   * Data provider for testExceptionOnRedirectImport.
   *
   * @return array
   *   Data provider set.
   */
  public function testExceptionOnRedirectImportDataProvider() {
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
        'a1d183ff-f1de-433c-8a75-21450a9c868b',
      ],
    ];
  }

}
