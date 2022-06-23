<?php

namespace Drupal\Tests\acquia_contenthub\Kernel\EventSubscriber\PreEntitySave;

use Acquia\ContentHubClient\CDF\CDFObject;
use Drupal\acquia_contenthub\Event\PreEntitySaveEvent;
use Drupal\acquia_contenthub_moderation\EventSubscriber\PreEntitySave\CreateModeratedForwardRevision;
use Drupal\depcalc\DependencyStack;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\content_moderation\Traits\ContentModerationTestTrait;

/**
 * Test that moderation state is correctly handled in PreEntitySave event.
 *
 * @group acquia_contenthub
 * @coversDefaultClass \Drupal\acquia_contenthub_moderation\EventSubscriber\PreEntitySave\CreateModeratedForwardRevision
 *
 * @requires module acquia_contenthub_moderation
 *
 * @package Drupal\Tests\acquia_contenthub\Kernel\EventSubscriber\PreEntitySave
 */
class CreateModeratedForwardRevisionTest extends KernelTestBase {

  use ContentModerationTestTrait;

  /**
   * Workflow entity.
   *
   * @var \Drupal\workflows\Entity\Workflow
   */
  protected $workflow;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'acquia_contenthub',
    'user',
    'field',
    'node',
    'system',
    'depcalc',
    'content_moderation',
    'workflows',
    'acquia_contenthub_moderation',
  ];

  /**
   * Node entity.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $node;

  /**
   * {@inheritdoc}
   *
   * @throws \Exception
   */
  protected function setup(): void {
    parent::setUp();
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installEntitySchema('content_moderation_state');
    $this->installSchema('node', ['node_access']);
    $this->installConfig([
      'system',
      'content_moderation',
      'acquia_contenthub_moderation',
    ]);

    NodeType::create([
      'type' => 'bundle_test',
      'new_revision' => TRUE,
    ])->save();

    $this->workflow = $this->createEditorialWorkflow();
    $this->workflow->getTypePlugin()->addEntityTypeAndBundle('node', 'bundle_test');
    $this->workflow->save();

    /** @var \Drupal\node\NodeInterface $node */
    $this->node = Node::create([
      'type' => 'bundle_test',
      'moderation_state' => 'draft',
      'langcode' => 'en',
      'title' => 'Check forward revisions',
    ]);

  }

  /**
   * Tests CreateModeratedForwardRevision event subscriber.
   */
  public function testCreateModeratedForwardRevision() {

    $config_factory = $this->container->get('config.factory');
    $config = $config_factory->getEditable('acquia_contenthub_moderation.settings');
    $config->set("workflows.{$this->workflow->id()}.moderation_state", 'archived');
    $config->save();

    $stack = $this->prophesize(DependencyStack::class);
    $cdf = $this->prophesize(CDFObject::class);

    $event = new PreEntitySaveEvent($this->node, $stack->reveal(), $cdf->reveal());
    $create_forward_revision = new CreateModeratedForwardRevision(
      $this->container->get('entity_type.manager'),
      $this->container->get('config.factory'),
      $this->container->get('content_moderation.moderation_information'),
      $this->container->get('logger.factory')
    );

    $mod_state = $this->node->get('moderation_state')->getString();
    $this->assertEquals($mod_state, 'draft');

    $create_forward_revision->onPreEntitySave($event);

    /** @var \Drupal\node\NodeInterface $entity */
    $entity = $event->getEntity();

    $mod_state = $entity->get('moderation_state')->getString();
    $this->assertEquals($mod_state, 'archived');
  }

  /**
   * Tests CreateModeratedForwardRevision event subscriber without config.
   */
  public function testCreateModFwdRevisionWithoutConfig(): void {

    $stack = $this->prophesize(DependencyStack::class);
    $cdf = $this->prophesize(CDFObject::class);

    $event = new PreEntitySaveEvent($this->node, $stack->reveal(), $cdf->reveal());
    $create_forward_revision = new CreateModeratedForwardRevision(
      $this->container->get('entity_type.manager'),
      $this->container->get('config.factory'),
      $this->container->get('content_moderation.moderation_information'),
      $this->container->get('logger.factory')
    );

    $create_forward_revision->onPreEntitySave($event);

    /** @var \Drupal\node\NodeInterface $entity */
    $entity = $event->getEntity();

    $mod_state = $entity->get('moderation_state')->getString();
    $this->assertEquals($mod_state, 'draft');
  }

}
