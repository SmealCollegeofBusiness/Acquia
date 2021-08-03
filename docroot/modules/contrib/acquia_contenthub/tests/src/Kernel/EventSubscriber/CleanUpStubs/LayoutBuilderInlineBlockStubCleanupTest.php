<?php

namespace Drupal\Tests\acquia_contenthub\Kernel\EventSubscriber\CleanUpStubs;

use Drupal\acquia_contenthub\AcquiaContentHubEvents;
use Drupal\acquia_contenthub\Event\CleanUpStubsEvent;
use Drupal\block_content\Entity\BlockContent;
use Drupal\depcalc\DependencyStack;
use Drupal\KernelTests\KernelTestBase;

/**
 * Test that LB inline block is handled correctly in CLEANUP_STUBS event.
 *
 * @group acquia_contenthub
 * @coversDefaultClass \Drupal\acquia_contenthub\EventSubscriber\CleanupStubs\LayoutBuilderInlineBlockStubCleanup
 *
 * @requires module depcalc
 *
 * @package Drupal\Tests\acquia_contenthub\Kernel\EventSubscriber\CleanUpStubs
 */
class LayoutBuilderInlineBlockStubCleanupTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'acquia_contenthub',
    'depcalc',
    'block',
    'block_content',
    'layout_builder',
    'user',
    'field',
  ];

  /**
   * Event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcher
   */
  protected $dispatcher;

  /**
   * {@inheritdoc}
   *
   * @throws \Exception
   */
  protected function setUp() {
    parent::setUp();

    $this->installEntitySchema('block_content');
    $this->installEntitySchema('field_config');
    $this->installSchema('layout_builder', 'inline_block_usage');

    $this->dispatcher = $this->container->get('event_dispatcher');
  }

  /**
   * Tests LayoutBuilderInlineBlockStubCleanup event subscriber.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function testLayoutBuilderInlineBlockStubCleanup() {
    $block_content = $this->createEntity();
    // Create again to have a duplicate.
    $this->createEntity();

    $event = new CleanUpStubsEvent($block_content, new DependencyStack());
    $this->dispatcher->dispatch(AcquiaContentHubEvents::CLEANUP_STUBS, $event);

    // Inline block usage is not set, should not stop propagation.
    $this->assertFalse($event->isPropagationStopped());

    // Just insert a row in the table with fixed values.
    $this->addInlineBlockUsage($block_content->id());

    $event = new CleanUpStubsEvent($block_content, new DependencyStack());
    $this->dispatcher->dispatch(AcquiaContentHubEvents::CLEANUP_STUBS, $event);

    // Inline block usage is there so propagation should be stopped.
    $this->assertTrue($event->isPropagationStopped());

    $blocks = \Drupal::entityTypeManager()
      ->getStorage('block_content')
      ->loadByProperties([
        'info' => $block_content->get('info')->value,
      ]);

    $this->assertEqual(count($blocks), 1, 'Only 1 block should be after subscriber run');
  }

  /**
   * Add a row into table with the given entity id.
   *
   * @param int $block_id
   *   Block content id.
   *
   * @throws \Exception
   */
  protected function addInlineBlockUsage($block_id) {
    \Drupal::database()->insert('inline_block_usage')
      ->fields([
        'block_content_id' => $block_id,
        'layout_entity_type' => 'node',
        'layout_entity_id' => 1,
      ])
      ->execute();
  }

  /**
   * Create a basic type block entity.
   *
   * @return \Drupal\block_content\Entity\BlockContent
   *   Returns the newly created block content.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function createEntity() {
    $block_content = BlockContent::create([
      'info' => 'Test',
      'type' => 'basic',
      'body' => [
        'value' => 'Test.',
        'format' => 'plain_text',
      ],
    ]);
    $block_content->save();

    return $block_content;
  }

}
