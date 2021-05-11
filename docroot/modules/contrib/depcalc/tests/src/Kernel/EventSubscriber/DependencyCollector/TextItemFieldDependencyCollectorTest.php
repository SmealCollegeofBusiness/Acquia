<?php

namespace Drupal\Tests\depcalc\Kernel\EventSubscriber\DependencyCollector;

use Drupal\editor\Entity\Editor;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\field\Traits\EntityReferenceTestTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Drupal\Tests\taxonomy\Traits\TaxonomyTestTrait;

/**
 * Class TextItemFieldDependencyCollectorTest.
 *
 * @coversDefaultClass \Drupal\depcalc\EventSubscriber\DependencyCollector\TextItemFieldDependencyCollector
 *
 * @package Drupal\depcalc\EventSubscriber\DependencyCollector
 *
 * @group depcalc
 */
class TextItemFieldDependencyCollectorTest extends KernelTestBase {

  use NodeCreationTrait;
  use TaxonomyTestTrait;
  use EntityReferenceTestTrait;
  use CalculateDependenciesEventDispatcherTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'depcalc',
    'field',
    'filter',
    'language',
    'content_translation',
    'node',
    'system',
    'text',
    'user',
    'file',
    'taxonomy',
    'path_alias',
    'entity_test',
    'entity_test_third_party',
    'field',
    'field_ui',
    'link',
    'editor',
    'editor_test',
  ];

  /**
   * {@inheritdoc}
   *
   * @throws \ReflectionException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function setUp() {
    parent::setUp();

    $this->installEntitySchema('filter_format');
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installEntitySchema('path_alias');
    $this->installEntitySchema('taxonomy_term');
    $this->installEntitySchema('entity_test');
    $this->installSchema('system', 'sequences');
    $this->installSchema('file', 'file_usage');
    $this->installConfig([
      'node',
      'user',
      'file',
      'filter',
      'entity_test',
    ]);
  }

  /**
   * @covers ::onCalculateDependencies
   *
   * @throws \Exception
   */
  public function testFieldsAndEditorWereAddedAsDependencies() {
    $editor = Editor::create([
      'format' => 'plain_text',
      'editor' => 'unicorn',
    ]);
    $editor->save();

    $vocabulary = $this->createVocabulary();

    $vocabulary_target = $this->createVocabulary();
    $handler_settings = [
      'target_bundles' => [
        $vocabulary_target->id() => $vocabulary_target->id(),
      ],
    ];
    $this->createEntityReferenceField('taxonomy_term', $vocabulary->id(), 'type', $this->randomString(), 'taxonomy_term', 'default', $handler_settings);
    $entity_display_repository = \Drupal::service('entity_display.repository');
    $entity_display_repository->getViewDisplay('taxonomy_term', $vocabulary->id(), 'default')
      ->setComponent('type')
      ->save();
    $entity_display_repository->getFormDisplay('taxonomy_term', $vocabulary->id(), 'default')
      ->setComponent('type', ['type' => 'entity_reference_autocomplete'])
      ->save();
    $term1 = $this->createTerm($vocabulary);
    $term2 = $this->createTerm($vocabulary_target);

    $term1->set('type', $term2->id());
    $term1->save();

    $event = $this->dispatchCalculateDependencies($term1);
    $dependencies = $event->getDependencies();
    $filter_uuid = \Drupal::entityTypeManager()->getStorage('filter_format')
      ->load('plain_text')->uuid();

    $this->assertArrayHasKey($filter_uuid, $dependencies);
    $this->assertArrayHasKey($editor->uuid(), $dependencies);
  }

}
