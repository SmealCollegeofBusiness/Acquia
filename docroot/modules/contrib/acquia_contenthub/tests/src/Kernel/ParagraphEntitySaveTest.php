<?php

namespace Drupal\Tests\Kernel;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\Entity\ParagraphsType;
use Drupal\Tests\acquia_contenthub\Kernel\Traits\FieldTrait;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;

/**
 * Tests the paragraph entity save revisions.
 *
 * @requires module depcalc paragraphs entity_reference_revisions
 *
 * @group acquia_contenthub_publisher
 */
class ParagraphEntitySaveTest extends EntityKernelTestBase {

  use ContentTypeCreationTrait;
  use NodeCreationTrait;
  use FieldTrait;

  /**
   * A test node.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $node;

  /**
   * A test paragraph.
   *
   * @var \Drupal\paragraphs\ParagraphInterface
   */
  protected $paragraph;

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'acquia_contenthub',
    'acquia_contenthub_publisher',
    'depcalc',
    'node',
    'paragraphs',
    'entity_reference_revisions',
    'file',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('paragraph');
    $this->installSchema('node', ['node_access']);
    $this->installConfig([
      'filter',
      'node',
    ]);

    $paragraph_type_nested = ParagraphsType::create([
      'label' => 'Para Text',
      'id' => 'text_paragraph',
    ]);
    $paragraph_type_nested->save();

    // Add a title and paragraph reference field to paragraph bundle.
    $field_storage = $this->createFieldStorage('title', 'paragraph', 'string');
    $this->createFieldConfig($field_storage, 'text_paragraph');
    $nested_para_field = $this->createFieldStorage('nested_paragraph', 'paragraph', 'entity_reference_revisions', [], -1);
    $this->createFieldConfig($nested_para_field, 'text_paragraph');
    $this->paragraph = Paragraph::create([
      'type' => 'text_paragraph',
      'title' => 'My Paragraph',
    ]);
    $this->paragraph->save();

    $this->createContentType([
      'type' => 'article',
    ]);
    // Add a paragraph field to the article.
    $field_storage = $this->createFieldStorage('node_paragraph_field', 'node', 'entity_reference_revisions', [
      'target_type' => 'paragraph',
    ]);
    $this->createFieldConfig($field_storage, 'article');
    $this->node = $this->createNode([
      'type' => 'article',
      'title' => 'My node',
      'node_paragraph_field' => $this->paragraph,
    ]);
  }

  /**
   * Tests paragraph entity to have same revision id, if entity not changed.
   */
  public function testParagraphSave(): void {
    $node_original_revision = $this->node->getRevisionId();
    $paragraph_original_revision = $this->paragraph->getRevisionId();

    // Update the referenced paragraph.
    $this->paragraph = $this->setEntityField($this->paragraph, 'title', 'New Paragraph Title');
    $this->node = $this->setEntityField($this->node, 'node_paragraph_field', $this->paragraph);

    $paragraph_new_revision = $this->paragraph->getRevisionId();
    $node_new_revision = $this->node->getRevisionId();

    $this->assertNotSame($paragraph_new_revision, $paragraph_original_revision);
    $this->assertNotSame($node_new_revision, $node_original_revision);

    // Update node, set flag paragraphs_unchanged_disable_revision to false.
    $config = $this->container->get('config.factory')->getEditable('acquia_contenthub_publisher.features');
    $config->set('paragraphs_unchanged_disable_revision', FALSE)->save();
    $this->node = $this->setEntityField($this->node, 'title', 'New Node Title');

    $paragraph_updated_revision = $this->paragraph->getRevisionId();
    $node_updated_revision = $this->node->getRevisionId();
    $this->assertNotSame($paragraph_updated_revision, $paragraph_new_revision);
    $this->assertNotSame($node_updated_revision, $node_new_revision);

    // Setting paragraphs_unchanged_disable_revision flag as TRUE.
    $config->set('paragraphs_unchanged_disable_revision', TRUE)->save();
    $this->node = $this->setEntityField($this->node, 'title', 'New Title');

    $this->assertSame($this->paragraph->getRevisionId(), $paragraph_updated_revision);
    $this->assertNotSame($this->node->getRevisionId(), $node_updated_revision);
  }

  /**
   * Nested paragraph entities to have same revision id, if entity not changed.
   */
  public function testNestedParagraph(): void {
    $level3_paragraph = Paragraph::create([
      'type' => 'text_paragraph',
      'title' => 'Level 3 Paragraph',
    ]);
    $level3_paragraph->save();
    $level2_paragraph = Paragraph::create([
      'type' => 'text_paragraph',
      'title' => 'Level 2 Paragraph',
      'nested_paragraph' => $level3_paragraph,
    ]);
    $level2_paragraph->save();
    $this->paragraph = $this->setEntityField($this->paragraph, 'nested_paragraph', $level2_paragraph);
    $this->node = $this->setEntityField($this->node, 'node_paragraph_field', $this->paragraph);

    $node_original_revision = $this->node->getRevisionId();
    $level1_paragraph_revision = $this->paragraph->getRevisionId();
    $level2_paragraph_revision = $level2_paragraph->getRevisionId();
    $level3_paragraph_revision = $level3_paragraph->getRevisionId();

    // Update node, set flag paragraphs_unchanged_disable_revision to true.
    $config = $this->container->get('config.factory')->getEditable('acquia_contenthub_publisher.features');
    $config->set('paragraphs_unchanged_disable_revision', TRUE)->save();

    // Update level 2 paragraph.
    $level2_paragraph = $this->setEntityField($level2_paragraph, 'title', 'Level 2 Paragraph flag false.');
    $this->paragraph = $this->setEntityField($this->paragraph, 'nested_paragraph', $level2_paragraph);
    $this->node = $this->setEntityField($this->node, 'node_paragraph_field', $this->paragraph);

    $node_updated_revision = $this->node->getRevisionId();
    $level1_paragraph_updated_revision = $this->node->node_paragraph_field->entity->getRevisionId();
    $level2_paragraph_updated_revision = $this->node->node_paragraph_field->entity->nested_paragraph->entity->getRevisionId();
    $level3_paragraph_updated_revision = $this->node->node_paragraph_field->entity->nested_paragraph->entity->nested_paragraph->getValue()[0]['target_revision_id'];

    $this->assertSame($level3_paragraph_updated_revision, $level3_paragraph_revision);
    $this->assertNotSame($level2_paragraph_updated_revision, $level2_paragraph_revision);
    $this->assertNotSame($level1_paragraph_updated_revision, $level1_paragraph_revision);
    $this->assertNotSame($node_updated_revision, $node_original_revision);

    // Update node title, paragraph revision id shall remain same.
    $this->node = $this->setEntityField($this->node, 'title', 'This is new node title');

    $this->assertNotSame($this->node->getRevisionId(), $node_updated_revision);
    $this->assertSame($this->node->node_paragraph_field->entity->getRevisionId(), $level1_paragraph_updated_revision);
    $this->assertSame($this->node->node_paragraph_field->entity->nested_paragraph->target_revision_id, $level2_paragraph_updated_revision);
    $l2_para = $this->container->get('entity_type.manager')
      ->getStorage('paragraph')
      ->loadRevision($this->node->node_paragraph_field->entity->nested_paragraph->target_revision_id);
    $this->assertSame($l2_para->nested_paragraph->target_revision_id, $level3_paragraph_updated_revision);
  }

  /**
   * Sibling paragraph entities to have same revision id, if entity not changed.
   */
  public function testSiblingParagraphs(): void {
    $s1_paragraph = Paragraph::create([
      'type' => 'text_paragraph',
      'title' => 'Sibling 1',
    ]);
    $s1_paragraph->save();
    $s2_paragraph = Paragraph::create([
      'type' => 'text_paragraph',
      'title' => 'Sibling 2',
    ]);
    $s2_paragraph->save();
    $this->paragraph = $this->setEntityField(
      $this->paragraph,
      'nested_paragraph',
      [$s1_paragraph, $s2_paragraph]
    );
    $this->node = $this->setEntityField($this->node, 'node_paragraph_field', $this->paragraph);

    $node_original_revision = $this->node->getRevisionId();
    $paragraph_original_revision = $this->paragraph->getRevisionId();
    $s1_paragraph_original_revision = $s1_paragraph->getRevisionId();
    $s2_paragraph_original_revision = $s2_paragraph->getRevisionId();

    // Update node, set flag paragraphs_unchanged_disable_revision to true.
    $config = $this->container->get('config.factory')->getEditable('acquia_contenthub_publisher.features');
    $config->set('paragraphs_unchanged_disable_revision', TRUE)->save();

    $this->node = $this->setEntityField($this->node, 'title', 'This is new node title');
    $node_updated_revision = $this->node->getRevisionId();
    $paragraph_updated_revision = $this->node->node_paragraph_field->entity->getRevisionId();
    $s1_paragraph_updated_revision = $this->node->node_paragraph_field->entity->nested_paragraph[0]->target_revision_id;
    $s2_paragraph_updated_revision = $this->node->node_paragraph_field->entity->nested_paragraph[1]->target_revision_id;

    $this->assertNotSame($node_updated_revision, $node_original_revision);
    $this->assertSame($paragraph_updated_revision, $paragraph_original_revision);
    $this->assertSame($s1_paragraph_updated_revision, $s1_paragraph_original_revision);
    $this->assertSame($s2_paragraph_updated_revision, $s2_paragraph_original_revision);

    // Update s1 sibling paragraph.
    $s1_paragraph = $this->setEntityField($s1_paragraph, 'title', 'Sibling 1 updated');
    $this->paragraph = $this->setEntityField(
      $this->paragraph,
      'nested_paragraph',
      [$s1_paragraph, $s2_paragraph]
    );
    $this->node = $this->setEntityField($this->node, 'node_paragraph_field', $this->paragraph);

    $this->assertNotSame($this->node->getRevisionId(), $node_updated_revision);
    $this->assertNotSame($this->node->node_paragraph_field->entity->getRevisionId(), $paragraph_updated_revision);
    $this->assertNotSame($this->node->node_paragraph_field->entity->nested_paragraph[0]->target_revision_id, $s1_paragraph_updated_revision);
    $this->assertSame($this->node->node_paragraph_field->entity->nested_paragraph[1]->target_revision_id, $s2_paragraph_updated_revision);
  }

  /**
   * Sets value of field for given entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity for which value needs to be set.
   * @param string $field_name
   *   The field name for which value needs to be set.
   * @param mixed $field_value
   *   The value to be set for given field.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   Returns updated entity.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function setEntityField(ContentEntityInterface $entity, string $field_name, $field_value): ContentEntityInterface {
    $entity->set($field_name, $field_value);
    $entity->setNewRevision(TRUE);
    $entity->save();

    return $entity;
  }

}
