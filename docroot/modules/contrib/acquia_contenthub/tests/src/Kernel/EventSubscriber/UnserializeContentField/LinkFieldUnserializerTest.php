<?php

namespace Drupal\Tests\acquia_contenthub\Kernel\EventSubscriber\UnserializeContentField;

use Drupal\acquia_contenthub\AcquiaContentHubEvents;
use Drupal\acquia_contenthub\Event\UnserializeCdfEntityFieldEvent;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\depcalc\DependencyStack;
use Drupal\depcalc\DependentEntityWrapper;
use Drupal\KernelTests\KernelTestBase;
use Drupal\taxonomy\Entity\Term;
use Prophecy\Argument;

/**
 * Test that links are handled correctly during unserialization.
 *
 * @group acquia_contenthub
 * @coversDefaultClass \Drupal\acquia_contenthub\EventSubscriber\UnserializeContentField\LinkFieldUnserializer
 *
 * @requires module depcalc
 *
 * @package Drupal\Tests\acquia_contenthub\Kernel\EventSubscriber\UnserializeContentField
 */
class LinkFieldUnserializerTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'acquia_contenthub',
    'depcalc',
    'user',
    'link',
    'field',
    'system',
    'taxonomy',
    'text',
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
  protected function setup(): void {
    parent::setUp();
    $this->installEntitySchema('field_config');
    $this->installEntitySchema('taxonomy_term');
    $this->installConfig(['system']);

    $this->dispatcher = $this->container->get('event_dispatcher');
  }

  /**
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testLinkFieldUnSerializer() {
    // Create mock taxonomy term.
    $term = Term::create([
      'name' => $this->randomMachineName(),
      'vid' => 'tags',
    ]);
    $term->save();

    $stack = $this->prophesize(DependencyStack::class);
    $wrapper = $this->prophesize(DependentEntityWrapper::class);
    $wrapper->getEntity()->willReturn($term);
    $stack->getDependency(Argument::any())->willReturn($wrapper->reveal());

    $meta_data = [
      'type' => 'link',
    ];

    $mock_field = [
      'value' => [
        'en' => [
          0 => [
            'uri' => 'uuid',
            'title' => 'test',
            'options' => [],
            'uri_type' => 'internal',
            'internal_type' => 'internal_entity',
          ],
        ],
        'hu' => [
          0 => NULL,
        ],
      ],
    ];

    $entity_type = $this->prophesize(EntityTypeInterface::class);
    $event = new UnserializeCdfEntityFieldEvent($entity_type->reveal(), 'test', 'link', $mock_field, $meta_data, $stack->reveal());
    $this->dispatcher->dispatch($event, AcquiaContentHubEvents::UNSERIALIZE_CONTENT_ENTITY_FIELD);

    $expected = [
      'en' => [
        'link' => [
          0 => [
            'uri' => 'internal:/taxonomy/term/1',
            'title' => 'test',
            'options' => [],
          ],
        ],
      ],
      'hu' => [
        'link' => [
          0 => [],
        ],
      ],
    ];

    $this->assertEquals($expected, $event->getValue());
  }

}
