<?php

namespace Drupal\Tests\acquia_contenthub\Kernel\EventSubscriber\UnserializeContentField;

use Drupal\acquia_contenthub\AcquiaContentHubEvents;
use Drupal\acquia_contenthub\Event\UnserializeCdfEntityFieldEvent;
use Drupal\Core\Block\BlockManager;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\depcalc\DependencyStack;
use Drupal\depcalc\DependentEntityWrapper;
use Drupal\KernelTests\KernelTestBase;
use Drupal\layout_builder\Plugin\Block\InlineBlock;
use Drupal\layout_builder\Section;
use Drupal\layout_builder\SectionComponent;
use Drupal\Tests\acquia_contenthub\Kernel\Traits\WatchdogAssertsTrait;
use Prophecy\Argument;

/**
 * Test that layout builder fields are handled correctly during unserialization.
 *
 * @group acquia_contenthub
 * @coversDefaultClass \Drupal\acquia_contenthub\EventSubscriber\UnserializeContentField\LayoutBuilderFieldUnserializer
 *
 * @requires module depcalc
 *
 * @package Drupal\Tests\acquia_contenthub\Kernel\EventSubscriber\UnserializeContentField
 */
class LayoutBuilderFieldUnserializerTest extends KernelTestBase {

  use WatchdogAssertsTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'acquia_contenthub',
    'depcalc',
    'user',
    'dblog',
    'layout_builder',
    'layout_discovery',
    'block_content',
  ];

  /**
   * {@inheritdoc}
   *
   * @throws \Exception
   */
  protected function setup(): void {
    parent::setUp();
    $this->installEntitySchema('block_content');
    $this->installSchema('dblog', 'watchdog');
  }

  /**
   * Tests layout builder field unserializer.
   */
  public function testLayoutBuilderFieldUnSerializer(): void {
    $stack = $this->prophesize(DependencyStack::class);
    $wrapper = $this->prophesize(DependentEntityWrapper::class);
    $stack->getDependency(Argument::any())->willReturn($wrapper->reveal());

    $meta_data = [
      'type' => 'layout_section',
    ];
    $field_name = 'layout_builder__layout';
    $mock_data = $this->getMockData();

    $entity_type = $this->prophesize(EntityTypeInterface::class);
    $event = new UnserializeCdfEntityFieldEvent($entity_type->reveal(), 'basic', $field_name, $mock_data['field'], $meta_data, $stack->reveal());
    $this->container->get('event_dispatcher')->dispatch($event, AcquiaContentHubEvents::UNSERIALIZE_CONTENT_ENTITY_FIELD);

    $expected = [
      'en' => [
        $field_name => [
          0 => [
            'section' => $mock_data['section'],
          ],
        ],
      ],
    ];
    $this->assertLogMessage(
      'acquia_contenthub',
      'Entity of type block_content having component: c800d55c-d7d6-4064-bed9-8ee04aa2c6c1, not found.'
    );
    $this->assertEquals($expected, $event->getValue());
  }

  /**
   * Mocks required field and section.
   *
   * @return array
   *   Returns array of data.
   */
  public function getMockData(): array {
    $plugin_id = 'inline_block:basic';
    $config = [
      'id' => $plugin_id,
      'label' => 'custom block basic',
      'label_display' => 'visible',
      'provider' => 'layout_builder',
      'view_mode' => 'full',
      'block_revision_id' => 1,
      'block_serialized' => NULL,
      'context_mapping' => [],
    ];
    $additional_settings = [
      'block_uuid' => '270ba14a-be30-4d98-8c9e-76ceef2e570e',
    ];
    $layout_setting = [
      'label' => 'test section',
    ];
    $inline_block = $this->prophesize(InlineBlock::class);
    $block_manager = $this->prophesize(BlockManager::class);
    $block_manager->createInstance($plugin_id, $config)->willReturn($inline_block->reveal());
    $this->container->set('plugin.manager.block', $block_manager->reveal());

    $component = new SectionComponent('c800d55c-d7d6-4064-bed9-8ee04aa2c6c1', 'content', $config, $additional_settings);
    $component->set('weight', 0);
    $section = new Section('layout_onecol', $layout_setting, [$component]);
    $mock_field = [
      'value' => [
        'en' => [
          0 => $section,
        ],
      ],
    ];

    return [
      'field' => $mock_field,
      'section' => $section,
    ];
  }

}
