<?php

namespace Drupal\Tests\depcalc\Kernel;

use Drupal\depcalc\DependentEntityWrapper;
use Drupal\KernelTests\KernelTestBase;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\Entity\ParagraphsType;
use Drupal\Tests\depcalc\Kernel\Traits\FieldTrait;
use Drupal\Tests\field\Traits\EntityReferenceTestTrait;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Drupal\Tests\taxonomy\Traits\TaxonomyTestTrait;

/**
 * Class HashCalculatorForReferencedEntitiesTest.
 *
 * @requires module paragraphs
 * @requires module entity_reference_revisions
 *
 * @group depcalc
 */
class HashCalculatorForReferencedEntitiesTest extends KernelTestBase {

  use ContentTypeCreationTrait;
  use FieldTrait;
  use NodeCreationTrait;
  use TaxonomyTestTrait;
  use EntityReferenceTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'depcalc',
    'field',
    'filter',
    'language',
    'node',
    'text',
    'user',
    'system',
    'paragraphs',
    'file',
    'entity_reference_revisions',
    'taxonomy',
  ];

  /**
   * The entity.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected $entity;

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installSchema('node', ['node_access']);
    $this->installEntitySchema('taxonomy_term');
    $this->installEntitySchema('paragraph');
    $this->installConfig([
      'field',
      'filter',
      'node',
    ]);

    $this->createContentType([
      'type' => 'article',
    ]);
  }

  /**
   * Tests hash calculation for complex entity.
   */
  public function testHashWithReferenceFields(): void {
    $page_content_type = $this->createContentType([
      'type' => 'page',
    ]);
    $dependentEntityWrapper = new DependentEntityWrapper($page_content_type);

    $paragraph_type = ParagraphsType::create([
      'label' => 'Para Text',
      'id' => 'text_paragraph',
    ]);
    $paragraph_type->save();
    // Add a title and page node reference field to text_paragraph paragraph.
    $field_storage = $this->createFieldStorage('title', 'paragraph', 'string');
    $this->createFieldConfig($field_storage, 'text_paragraph');
    $field_storage = $this->createFieldStorage('reference_node', 'paragraph', 'entity_reference');
    $this->createFieldConfig($field_storage, 'text_paragraph');

    $node = $this->createNode([
      'type' => 'page',
      'title' => 'My page node',
    ]);
    $paragraph = Paragraph::create([
      'type' => 'text_paragraph',
      'title' => 'Paragraph title',
      'reference_node' => $node,
    ]);
    // Add a paragraph field to the article.
    $field_storage = $this->createFieldStorage('node_paragraph_field', 'node', 'entity_reference_revisions', [
      'target_type' => 'paragraph',
    ]);
    $this->createFieldConfig($field_storage, 'article');

    // Term reference field to article.
    $vocabulary = $this->createVocabulary();
    $this->createEntityReferenceField('node', 'article', 'taxonomy_reference', 'taxonomy_reference', 'taxonomy_term');
    $term = $this->createTerm($vocabulary, ['name' => 'Test term']);

    $this->entity = $this->createNode([
      'type' => 'article',
      'title' => 'My node',
      'node_paragraph_field' => $paragraph,
      'taxonomy_reference' => $term,
    ]);

    $dependentEntityWrapper1 = new DependentEntityWrapper($this->entity);
    $term->set('name', 'Test term updated')->save();
    $this->entity->set('taxonomy_reference', $term)->save();
    $dependentEntityWrapper2 = new DependentEntityWrapper($this->entity);
    $this->assertSame($dependentEntityWrapper1->getHash(), $dependentEntityWrapper2->getHash());

    $node->set('title', 'My page node updated')->save();
    $paragraph->set('reference_node', $node)->save();
    $this->entity->set('node_paragraph_field', $paragraph)->save();
    $dependentEntityWrapper3 = new DependentEntityWrapper($this->entity);
    $this->assertSame($dependentEntityWrapper2->getHash(), $dependentEntityWrapper3->getHash());

    $this->entity->set('taxonomy_reference', NULL)->save();
    $dependentEntityWrapper4 = new DependentEntityWrapper($this->entity);
    $this->assertNotSame($dependentEntityWrapper3->getHash(), $dependentEntityWrapper4->getHash());

    $this->entity->set('node_paragraph_field', NULL)->save();
    $dependentEntityWrapper5 = new DependentEntityWrapper($this->entity);
    $this->assertNotSame($dependentEntityWrapper4->getHash(), $dependentEntityWrapper5->getHash());

    $dependentEntityWrapper6 = new DependentEntityWrapper($page_content_type);
    $this->assertSame($dependentEntityWrapper->getHash(), $dependentEntityWrapper6->getHash());
  }

}
