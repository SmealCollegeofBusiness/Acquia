<?php

namespace Drupal\Tests\acquia_contenthub\Kernel;

/**
 * Test to assert entities don't get into export queue.
 *
 * When they are being imported for the first time.
 *
 * @group acquia_contenthub
 *
 * @package Drupal\Tests\acquia_contenthub\Kernel
 */
class PreventExportForImportedEntitiesTest extends ImportExportTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'taxonomy',
    'acquia_contenthub_publisher',
    'acquia_contenthub_subscriber',
    'acquia_contenthub_server_test',
  ];

  /**
   * Fixture structure.
   *
   * @var \string[][]
   */
  protected $fixtures = [
    [
      'cdf' => 'taxonomy_term/taxonomy_term-multiple_parent.json',
      'expectations' => 'expectations/node/node_term_page.php',
    ],
  ];

  /**
   * {@inheritDoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installSchema('acquia_contenthub_publisher', ['acquia_contenthub_publisher_export_tracking']);
    $this->installSchema('acquia_contenthub_subscriber', ['acquia_contenthub_subscriber_import_tracking']);
    $this->installEntitySchema('taxonomy_term');
  }

  /**
   * Tests no items get added to export queue when entities are being imported.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testNoItemsExportedOnImport(): void {
    $export_queue = \Drupal::queue('acquia_contenthub_publish_export');
    $export_queue->deleteQueue();
    $this->assertEquals(0, $export_queue->numberOfItems());
    $this->importFixture(0);
    $this->assertEquals(0, $export_queue->numberOfItems());
  }

}
