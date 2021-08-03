<?php

namespace Drupal\Tests\acquia_contenthub\Kernel;

use Drupal\acquia_contenthub_publisher\Event\ContentHubEntityEligibilityEvent;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * Tests Optional Dependency Calculation for given entity.
 *
 * @group acquia_contenthub
 *
 * @package Drupal\Tests\acquia_contenthub\Kernel
 */
class OptionalDependencyCalculationTest extends KernelTestBase {

  use NodeCreationTrait;
  use ContentTypeCreationTrait;
  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'system',
    'user',
    'field',
    'filter',
    'node',
    'text',
    'depcalc',
    'path_alias',
    'acquia_contenthub',
    'acquia_contenthub_publisher',
  ];

  /**
   * Content Hub Common Actions Service.
   *
   * @var \Drupal\acquia_contenthub\ContentHubCommonActions
   */
  private $commonActionService;

  /**
   * {@inheritDoc}
   */
  public function setUp() {
    parent::setUp();
    $this->installSchema('acquia_contenthub_publisher', ['acquia_contenthub_publisher_export_tracking']);
    $this->installConfig('node');
    $this->installConfig('field');
    $this->installConfig('filter');
    $this->installSchema('node', 'node_access');
    $this->installSchema('user', 'users_data');
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('path_alias');
    $this->installEntitySchema('user');

    $this->commonActionService = $this->container->get('acquia_contenthub_common_actions');
  }

  /**
   * Tests there are no dependencies calculated when depcalc flag is false.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testDependencyCalculation(): void {
    $bundle = 'dummy_content_type';
    $contentType = $this->createContentType([
      'type' => $bundle,
      'name' => 'Dummy content type',
    ]);
    $contentType->save();
    $node = $this->createNode([
      'type' => $bundle,
    ]);
    $node->save();

    $queue = \Drupal::queue('acquia_contenthub_publish_export');

    // Check whether any queue item inserted on this node save
    // has default calculate dependency flag.
    // We are not going to process this item,
    // we are just going to examine it.
    $queue_item = $queue->claimItem();
    // This assertion holds that default CH workflow is not affected.
    $this->assertFalse(property_exists($queue_item->data, 'calculate_dependencies'));
    // Delete the queue item for next assertion.
    $queue->deleteQueue();

    // Create new queue item.
    $event = new ContentHubEntityEligibilityEvent($node, 'insert');
    // This will be done by Lift Publisher event subscriber
    // and is not a Content Hub use case.
    $event->setCalculateDependencies(FALSE);
    $item = new \stdClass();
    $item->type = $node->getEntityTypeId();
    $item->uuid = $node->uuid();
    if ($event->getCalculateDependencies() === FALSE) {
      $item->calculate_dependencies = FALSE;
    }
    $queue->createItem($item);
    $queue_item = $queue->claimItem();
    $this->assertFalse($queue_item->data->calculate_dependencies);

    $entities = [];
    // Get CDF without dependencies.
    $cdf_objects = $this->commonActionService->getEntityCdf($node, $entities, TRUE, FALSE);
    $this->assertEqual(1, count($cdf_objects), 'There is only 1 CDF object.');
    /** @var \Acquia\ContentHubClient\CDF\CDFObject $cdf */
    $cdf = reset($cdf_objects);
    // Only cdf object is for given node.
    $this->assertEqual($node->uuid(), $cdf->getUuid());
    $this->assertEmpty($cdf->getDependencies());
    $this->assertEmpty($cdf->getModuleDependencies());

    // Get the CDF with dependencies.
    $entities = [];
    $cdf_objects = $this->commonActionService->getEntityCdf($node, $entities);
    $this->assertGreaterThan(1, count($cdf_objects));
    foreach ($cdf_objects as $cdf) {
      // Only assert for the main node as we don't know
      // if other entities in CDF will have the dependencies or not.
      if ($node->uuid() === $cdf->getUuid()) {
        $this->assertNotEmpty($cdf->getDependencies());
      }
      // All the entities in CDF will have at least one module dependency.
      $this->assertNotEmpty($cdf->getModuleDependencies());
    }
  }

}
