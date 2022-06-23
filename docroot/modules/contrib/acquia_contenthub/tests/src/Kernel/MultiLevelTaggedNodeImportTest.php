<?php

namespace Drupal\Tests\acquia_contenthub\Kernel;

use Acquia\ContentHubClient\CDFDocument;
use Drupal\acquia_contenthub\Event\LoadLocalEntityEvent;
use Drupal\acquia_contenthub_subscriber\EventSubscriber\LoadLocalEntity\TaxonomyTermMatch;
use Drupal\depcalc\DependencyStack;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\Tests\acquia_contenthub\Kernel\Traits\CdfDocumentCreatorTrait;

/**
 * CDF importer tests.
 *
 * @group acquia_contenthub_subscriber
 *
 * @requires module depcalc
 *
 * @package Drupal\Tests\acquia_contenthub\Kernel
 */
class MultiLevelTaggedNodeImportTest extends ImportExportTestBase {

  use CdfDocumentCreatorTrait;

  /**
   * L1 term uuid.
   *
   * @var string
   */
  protected $l1Term = "a3aa6e83-9173-4e93-8b82-30f30fbc5440";

  /**
   * L2 term uuid.
   *
   * @var string
   */
  protected $l2Term = "86ff4e06-d32d-4d09-b32f-f52af52084f1";

  /**
   * L3 term uuid.
   *
   * @var string
   */
  protected $l3Term = "2acd7707-1f43-483f-9a98-2af4cb6cc22b";

  /**
   * Fixtures for the test.
   *
   * @var array
   */
  protected $fixtures = [
    0 => [
      'cdf' => 'node/node-with-multilevel-tags.json',
      'expectations' => 'expectations/node/node_with_multilevel_tags.php',
    ],
  ];

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'taxonomy',
    'user',
    'node',
    'file',
    'field',
    'depcalc',
    'acquia_contenthub',
    'acquia_contenthub_subscriber',
    'acquia_contenthub_test',
  ];

  /**
   * Taxonomy term match event subscriber object.
   *
   * @var \Drupal\acquia_contenthub_subscriber\EventSubscriber\LoadLocalEntity\TaxonomyTermMatch
   */
  protected $taxonomyTermMatch;

  /**
   * {@inheritDoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installSchema('user', ['users_data']);
    $this->installEntitySchema('node');
    $this->installSchema('node', ['node_access']);
    $this->installEntitySchema('taxonomy_term');
    $this->installSchema('acquia_contenthub_subscriber', 'acquia_contenthub_subscriber_import_tracking');
    $this->installEntitySchema('file');
    $this->installSchema('file', ['file_usage']);
    $this->taxonomyTermMatch = new TaxonomyTermMatch();
  }

  /**
   * Tests that nodes with multi-level taxonomies are imported successfully.
   *
   * When there are cross dependencies on taxonomy terms with path aliases.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testMultiLevelNodeSuccessfulImport(): void {
    $this->importFixture(0);
  }

  /**
   * Tests that no invalid exception is raised.
   *
   * When LoadLocalEntity event is fired from Stub creation.
   *
   * @throws \Exception
   */
  public function testNoExceptionRaisedOnStubCreation(): void {
    Vocabulary::create(
      [
        'vid' => 'tags',
        'machine_name' => 'tags',
        'name' => 'Tags',
      ]
    )->save();
    $cdf = $this->createCdfDocumentFromFixture(0);
    $this->assertExceptions($cdf, $this->l2Term, $this->l1Term);
    $this->assertExceptions($cdf, $this->l3Term, $this->l2Term);
  }

  /**
   * Asserts that exception raised when event fired from normal unserialization.
   *
   * Asserts that no exception raised when event fired from stub creation.
   *
   * @param \Acquia\ContentHubClient\CDFDocument $cdf
   *   Cdf document.
   * @param string $child_uuid
   *   Child term uuid.
   * @param string $parent_uuid
   *   Parent term uuid.
   *
   * @throws \Exception
   */
  protected function assertExceptions(CDFDocument $cdf, string $child_uuid, string $parent_uuid): void {
    $stack = new DependencyStack();
    $child_term = $cdf->getCdfEntity($child_uuid);
    // Event fired from stub creation.
    $child_local_entity_event = new LoadLocalEntityEvent($child_term, $stack, TRUE);
    $this->taxonomyTermMatch->onLoadLocalEntity($child_local_entity_event);
    $this->assertFalse($child_local_entity_event->hasEntity(), 'Asserts that event subscriber didn\'t fail and no exception was raised.');
    // Event fired from normal unserialization process.
    $child_local_entity_event = new LoadLocalEntityEvent($child_term, $stack);
    $this->expectException(\Exception::class);
    $this->expectExceptionMessage(sprintf("Taxonomy term %s could not be found in the dependency stack during DataTamper.", $parent_uuid));
    $this->taxonomyTermMatch->onLoadLocalEntity($child_local_entity_event);
  }

}
