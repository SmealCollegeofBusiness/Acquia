<?php

namespace Drupal\Tests\acquia_contenthub\Kernel\EventSubscriber\EntityDataTamper;

use Acquia\ContentHubClient\CDFAttribute;
use Drupal\acquia_contenthub\Event\EntityDataTamperEvent;
use Drupal\acquia_contenthub\EventSubscriber\EntityDataTamper\AnonymousUser;
use Drupal\depcalc\DependencyStack;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\Tests\acquia_contenthub\Kernel\Stubs\DrupalVersion;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;

/**
 * Tests Anonymous user attribute.
 *
 * @group acquia_contenthub
 *
 * @coversDefaultClass \Drupal\acquia_contenthub\EventSubscriber\EntityDataTamper\AnonymousUser
 *
 * @requires module depcalc
 *
 * @package Drupal\Tests\acquia_contenthub\Kernel\EventSubscriber\EntityDataTamper
 */
class AnonymousUserTest extends EntityKernelTestBase {

  use DrupalVersion;
  use NodeCreationTrait;
  use ContentTypeCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'acquia_contenthub',
    'depcalc',
    'filter',
    'language',
    'node',
    'text',
    'path_alias',
  ];

  /**
   * The AnonymousUser object.
   *
   * @var \Drupal\acquia_contenthub\EventSubscriber\EntityDataTamper\AnonymousUser
   */
  protected $entityDataTamper;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('node_type');
    $this->installConfig(['node', 'filter']);
    $this->installEntitySchema('field_config');
    $this->installSchema('node', 'node_access');
    $this->installSchema('user', 'users_data');
    $this->installEntitySchema('path_alias');

    $this->createContentType([
      'type' => 'page',
      'name' => 'Page',
    ])->save();

    $this->entityDataTamper = new AnonymousUser();
  }

  /**
   * Test cases to tamper with CDF data before its imported.
   *
   * @covers ::onDataTamper
   *
   * @throws \Exception
   */
  public function testAnonymousUserDataTamper(): void {
    $node = $this->createNode();

    /** @var \Acquia\ContentHubClient\CDFDocument $cdf */
    $cdf = $this->container->get('acquia_contenthub_common_actions')
      ->getLocalCdfDocument($node);

    foreach ($cdf->getEntities() as $entity) {
      if (!$entity->getAttribute('is_anonymous')) {
        $entity->addAttribute('is_anonymous', CDFAttribute::TYPE_INTEGER, 0);
      }
    }

    $event = new EntityDataTamperEvent($cdf, new DependencyStack());
    $this->entityDataTamper->onDataTamper($event);

    $remote_uuid = current(array_keys($event->getStack()->getDependencies()));
    $this->assertArrayHasKey($remote_uuid, $cdf->getEntities()[$node->uuid()]->getDependencies());
  }

}
