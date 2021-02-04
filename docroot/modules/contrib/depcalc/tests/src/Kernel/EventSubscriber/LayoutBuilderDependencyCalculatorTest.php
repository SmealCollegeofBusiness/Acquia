<?php

namespace Drupal\Tests\depcalc\Kernel;

use Drupal;
use Drupal\block_content\Entity\BlockContent;
use Drupal\KernelTests\KernelTestBase;
use Drupal\layout_builder\Plugin\SectionStorage\OverridesSectionStorage;
use Drupal\layout_builder\Section;
use Drupal\layout_builder\SectionComponent;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\user\Entity\User;

/**
 * Class LayoutBuilderDependencyCalculatorTest.
 *
 * @requires module path_alias
 * @group depcalc
 */
class LayoutBuilderDependencyCalculatorTest extends KernelTestBase {

  use DependencyHelperTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'views',
    'depcalc_test',
    'depcalc',
    'node',
    'comment',
    'file',
    'image',
    'taxonomy',
    'user',
    'block_content',
    'system',
    'field',
    'text',
    'layout_discovery',
    'layout_builder',
  ];

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installSchema('system', ['sequences', 'key_value_expire']);
    $this->installSchema('file', ['file_usage']);
    $this->installSchema('comment', ['comment_entity_statistics']);
    $this->installSchema('layout_builder', ['inline_block_usage']);
    $this->installEntitySchema('view');
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('taxonomy_vocabulary');
    $this->installEntitySchema('taxonomy_term');
    $this->installEntitySchema('comment');
    $this->installEntitySchema('block_content');
    $this->installConfig('depcalc_test');
    $this->installConfig('image');
    $this->installConfig('user');

    $this->calculator = Drupal::service('entity.dependency.calculator');

    // Create test user.
    /** @var \Drupal\Core\Entity\EntityRepository $entity_repository */
    $entity_repository = Drupal::service('entity.repository');
    $admin_role = $entity_repository->loadEntityByUuid(
      'user_role',
      '27202596-169e-4835-b9d4-c51ded9a03b8');
    $test_user = User::create([
      'name' => 'Admin',
      'roles' => [$admin_role->id()],
      'uuid' => '2d666602-74c0-4d83-a6ef-d181fd562291',
    ]);
    $test_user->save();

    $block = BlockContent::create([
      'type' => 'basic',
      'info' => 'Test Block',
      'uuid' => 'f523731a-cb0a-44be-8f9d-a4fae4dd7015',
    ]);
    $block->save();

    $block = BlockContent::create([
      'type' => 'basic',
      'info' => 'Non-Inline Block',
      'uuid' => '865b56c1-134c-401e-88bc-f37d36dbc885',
    ]);
    $block->save();

    $node_type = NodeType::create([
      'name' => 'Landing Page',
      'type' => 'landing_page',
      'new_revision' => TRUE,
    ]);
    $node_type->save();

    /** @var \Drupal\Core\Entity\Display\EntityViewDisplayInterface $display */
    $display = \Drupal::entityTypeManager()->getStorage('entity_view_display')->create([
      'targetEntityType' => 'node',
      'bundle' => 'landing_page',
      'mode' => 'default',
      'status' => TRUE,
    ]);
    $display->setThirdPartySetting('layout_builder', 'enabled', TRUE);
    $display->setThirdPartySetting('layout_builder', 'allow_custom', TRUE);

    $section = new Section('layout_onecol', ['label' => '']);
    foreach ($this->getSectionComponents() as $component) {
      $section->appendComponent($component);
    }
    $display->setThirdPartySetting('layout_builder', 'sections', [$section]);
    $display->save();

    $node = Node::create([
      'title' => 'Landing Page 1',
      'type' => 'landing_page',
    ]);
    $node->set(OverridesSectionStorage::FIELD_NAME, [$section]);
    $node->save();
  }

  /**
   * Tests the calculation of Layout Builder dependencies.
   */
  public function testEntityDependencies() {
    $view = \Drupal::entityTypeManager()->getStorage('view')->load('who_s_online');
    // Calculate dependencies for an entity_view_display entity.
    /** @var \Drupal\Core\Entity\EntityInterface $entity */
    $entity = \Drupal::entityTypeManager()->getStorage('entity_view_display')->load('node.landing_page.default');

    // Extract the entity dependencies.
    $actual_entities = $this->getEntityDependencies($entity);
    // Test that our inline block is found.
    $this->assertTrue(in_array('f523731a-cb0a-44be-8f9d-a4fae4dd7015', $actual_entities));
    // Test that our regular content block was also found.
    $this->assertTrue(in_array('865b56c1-134c-401e-88bc-f37d36dbc885', $actual_entities));
    // Test that our placed view is a dependency.
    $this->assertTrue(in_array($view->uuid(), $actual_entities));

    $actual_modules = $this->getModuleDependencies($entity);
    $this->assertEqual($actual_modules, [
      'user',
      'block_content',
      'layout_builder',
      'layout_discovery',
      'views',
      'text'
    ]);

    $node = \Drupal::entityTypeManager()->getStorage('node')->load(1);

    // Extract the entity dependencies.
    $actual_entities = $this->getEntityDependencies($node);
    // Test that our inline block is found.
    $this->assertTrue(in_array('f523731a-cb0a-44be-8f9d-a4fae4dd7015', $actual_entities));
    // Test that our regular content block was also found.
    $this->assertTrue(in_array('865b56c1-134c-401e-88bc-f37d36dbc885', $actual_entities));
    // Test that our placed view is a dependency.
    $this->assertTrue(in_array($view->uuid(), $actual_entities));

    $actual_modules = $this->getModuleDependencies($node);
    $this->assertEqual($actual_modules, [
      'node',
      'layout_discovery',
      'block_content',
      'text',
      'layout_builder',
      'user',
      'views',
    ]);
  }

  /**
   * Gets an array of section components for testing dependencies.
   *
   * @return \Drupal\layout_builder\SectionComponent[]
   */
  protected function getSectionComponents() {
    return [
      // Inline block.
      new SectionComponent('01e80474-531b-46bc-8e0c-c3dbffb0e099', 'content', [
        'id' => 'inline_block:basic',
        'label' => 'Bar Block',
        'provider' => 'layout_builder',
        'label_display' => 'visible',
        'view_mode' => 'full',
        'block_revision_id' => 1,
        'block_serialized' => NULL,
        'context_mapping' => [],
      ]),
      // Regular content block
      new SectionComponent('e4ee411c-305d-4696-bada-3b7f41b5611b', 'content', [
        'id' => 'block_content:865b56c1-134c-401e-88bc-f37d36dbc885',
        'label' => 'Non-Inline Block',
        'provider' => 'block_content',
        'label_display' => 'visible',
        'status' => TRUE,
        'info' => '',
        'view_mode' => 'full',
        'context_mapping' => [],
      ]),
      // Export a view
      new SectionComponent('8c2741f3-7f82-47c5-a382-dcf4e216874f','content', [
        'id' => 'views_block:who_s_online-who_s_online_block',
        'label' => '',
        'provider' => 'views',
        'label_display' => 'visible',
        'views_label' => '',
        'items_per_page' => 'none',
        'context_mapping' => [],
      ]),
    ];
  }

}
