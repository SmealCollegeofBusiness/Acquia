<?php

namespace Drupal\Tests\depcalc\Kernel\EventSubscriber\DependencyCollector;

use Drupal\depcalc\DependencyStack;
use Drupal\depcalc\DependentEntityWrapper;
use Drupal\depcalc\Event\CalculateEntityDependenciesEvent;
use Drupal\depcalc\EventSubscriber\DependencyCollector\MenuItemContentDependencyCollector;
use Drupal\KernelTests\KernelTestBase;

/**
 * Class MenuDependencyTest.
 *
 * @group depcalc
 *
 * @package Drupal\Tests\depcalc\Kernel\EventSubscriber\DependencyCollector
 */
class MenuDependencyTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'depcalc_test',
    'depcalc',
    'system',
    'node',
    'menu_link_content',
    'user',
    'link',
    'path_alias',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('menu_link_content');
    $this->installEntitySchema('path_alias');
    $this->installSchema('system', ['sequences']);
    $user = \Drupal::entityTypeManager()->getStorage('user')->create([
      'uuid' => '3e346612-34c0-4d83-a6ef-e181fd562281',
      'name' => 'Test user',
    ]);
    $user->save();

    $node = \Drupal::entityTypeManager()->getStorage('node')->create(
      [
        'type' => 'article',
        'title' => 'A test article',
        'field_body' => 'body content',
        'uid' => 1,
      ]
    );
    $node->save();

    $menu = \Drupal::entityTypeManager()->getStorage('menu')->create([
      'id' => 'test',
      'label' => 'Test Menu',
      'uuid' => '38584be3-605a-47b1-8881-838e1424d0f9',
    ]);
    $menu->save();
  }


  /**
   * Tests the calculation of menu entity dependencies.
   *
   * @param string $test_entity_type
   *   The test entity type.
   * @param string $test_entity_uuid
   *   The test entity uuid.
   * @param array $entities
   *   Entities to create.
   * @param array $expected_entities
   *   The list of expected entities.
   * @param array $expected_modules
   *   The list of expected modules.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @dataProvider menuDependenciesDataProvider
   */
  public function testMenuEntityDependencies(string $test_entity_type, string $test_entity_uuid, array $entities, array $expected_entities, array $expected_modules) {
    /** @var \Drupal\Core\Entity\EntityRepositoryInterface $repository */
    $repository = \Drupal::service('entity.repository');
    $entity_type_manager = \Drupal::entityTypeManager();
    foreach ($entities as $entity_type => $entities_values) {
      foreach ($entities_values as $entity_values) {
        $entity = $entity_type_manager->getStorage($entity_type)->create($entity_values);
        $entity->save();
      }
    }

    $test_entity = $repository->loadEntityByUuid($test_entity_type, $test_entity_uuid);
    $wrapper = new DependentEntityWrapper($test_entity);
    $stack = new DependencyStack();
    $event = new CalculateEntityDependenciesEvent($wrapper, $stack);
    $subscriber = new MenuItemContentDependencyCollector();
    $subscriber->onCalculateDependencies($event);

    $this->assertEqual(array_keys($wrapper->getDependencies()), $expected_entities);
    $this->assertEqual($wrapper->getModuleDependencies(), $expected_modules);
  }

  /**
   * Data provider for testMenuEntityDependencies.
   *
   * @return array
   *   Test data sets consisting of entity values and a list of dependencies
   *   respectively.
   */
  public function menuDependenciesDataProvider() {
    return [
      [
        // Stand alone menu link content entity.
        'menu_link_content',
        '6e452034-9a51-42c4-8c51-eda1be63d048',
        [
          'menu_link_content' => [
            [
              'uuid' => '6e452034-9a51-42c4-8c51-eda1be63d048',
              'title' => 'Test 1',
              'menu_name' => 'test',
              'link' => 'http://www.google.com',
              'external' => TRUE
            ]
          ]
        ],
        ['38584be3-605a-47b1-8881-838e1424d0f9'],
        [
          'menu',
          'menu_link_content',
          'system',
        ]
      ],
      // Menu with parent
      [
        'menu_link_content',
        '6e452034-9a51-42c4-8c51-eda1be63d047',
        [
          'menu_link_content' => [
            [
              'uuid' => '6e452034-9a51-42c4-8c51-eda1be63d048',
              'title' => 'Test 1',
              'menu_name' => 'test',
              'link' => 'http://www.google.com',
              'external' => TRUE
            ],
            [
              'uuid' => '6e452034-9a51-42c4-8c51-eda1be63d047',
              'title' => 'Test 2',
              'menu_name' => 'test',
              'link' => 'http://www.yahoo.com',
              'external' => TRUE,
              'parent' => 'menu_link_content:6e452034-9a51-42c4-8c51-eda1be63d048'
            ]
          ]
        ],
        [
          '38584be3-605a-47b1-8881-838e1424d0f9',
          '6e452034-9a51-42c4-8c51-eda1be63d048',
        ],
        [
          'menu',
          'menu_link_content',
          'system',
        ]
      ]
    ];
  }

}
