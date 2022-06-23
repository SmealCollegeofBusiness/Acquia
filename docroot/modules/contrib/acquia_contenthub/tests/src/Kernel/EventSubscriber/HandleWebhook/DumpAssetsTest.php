<?php

namespace Drupal\Tests\acquia_contenthub\Kernel\EventSubscriber\HandleWebhook;

use Acquia\Hmac\Key;
use Drupal\acquia_contenthub\Event\HandleWebhookEvent;
use Drupal\acquia_contenthub_subscriber\EventSubscriber\HandleWebhook\DumpAssets;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\Tests\acquia_contenthub\Kernel\Traits\AcquiaContentHubAdminSettingsTrait;
use Drupal\Tests\acquia_contenthub\Kernel\Traits\RequestTrait;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;

/**
 * Tests dump assets.
 *
 * @group acquia_contenthub
 *
 * @coversDefaultClass \Drupal\acquia_contenthub_subscriber\EventSubscriber\HandleWebhook\DumpAssets
 *
 * @requires module depcalc
 *
 * @package Drupal\Tests\acquia_contenthub\Kernel\EventSubscriber\HandleWebhook
 */
class DumpAssetsTest extends EntityKernelTestBase {

  use AcquiaContentHubAdminSettingsTrait;
  use ContentTypeCreationTrait;
  use NodeCreationTrait;
  use RequestTrait;

  /**
   * Dump assets instance.
   *
   * @var \Drupal\acquia_contenthub_subscriber\EventSubscriber\HandleWebhook\DumpAssets
   */
  protected $dumpAssets;

  /**
   * The SubscriberTracker.
   *
   * @var \Drupal\acquia_contenthub_subscriber\SubscriberTracker
   */
  protected $tracker;

  /**
   * The ContentHubCommonActions.
   *
   * @var \Drupal\acquia_contenthub\ContentHubCommonActions
   */
  protected $common;

  /**
   * The node entity.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $node;

  /**
   * Content Hub Client Factory.
   *
   * @var \Drupal\acquia_contenthub\Client\ClientFactory
   */
  protected $clientFactory;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'acquia_contenthub',
    'acquia_contenthub_subscriber',
    'depcalc',
    'node',
    'path_alias',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->createAcquiaContentHubAdminSettings();
    $this->installSchema('acquia_contenthub_subscriber', ['acquia_contenthub_subscriber_import_tracking']);
    $this->installSchema('user', ['users_data']);
    $this->installEntitySchema('path_alias');
    $this->installEntitySchema('node');
    $this->installConfig([
      'field',
      'filter',
      'node',
    ]);
    $this->clientFactory = $this->container->get('acquia_contenthub.client.factory');

    $this->createContentType([
      'type' => 'article',
    ]);
    $this->node = $this->createNode([
      'type' => 'article',
      'uuid' => '000-111-222-333',
    ]);

    $this->tracker = $this->container->get('acquia_contenthub_subscriber.tracker');
    $this->common = $this->container->get('acquia_contenthub_common_actions');
    $this->dumpAssets = new DumpAssets($this->tracker, $this->entityTypeManager, $this->common);
  }

  /**
   * Tests dump assets.
   *
   * @param string $crud
   *   The crud value.
   * @param string $status
   *   The status value.
   * @param string $uuid
   *   The uuid.
   * @param array $types
   *   Types of entity.
   * @param string $method
   *   The assert method.
   *
   * @dataProvider dataProviderDumpAsset
   */
  public function testDumpAsset(string $crud, string $status, string $uuid, array $types, string $method): void {
    $key = new Key('id', 'secret');
    $payload = [
      'crud' => $crud,
      'status' => $status,
      'initiator' => $uuid,
      'types' => $types,
    ];

    $event = new HandleWebhookEvent($this->createSignedRequest(), $payload, $key, $this->clientFactory->getClient());
    $this->dumpAssets->onHandleWebhook($event);

    $response = ($method == 'assertNotEmpty') ? $event->getResponse()->getBody()->getContents() : $event->getResponse()->getContent();
    $this->$method($response);
  }

  /**
   * Provides test data for testDumpAsset.
   *
   * @return array
   *   Test input.
   */
  public function dataProviderDumpAsset() {
    return [
      [
        'dump',
        'pending',
        'some-initiator-uuid',
        ['node'],
        'assertNotEmpty',
      ],
      [
        'test',
        'pending',
        'some-initiator-uuid',
        ['node'],
        'assertEmpty',
      ],
      [
        'dump',
        'successful',
        'some-initiator-uuid',
        ['node'],
        'assertEmpty',
      ],
      [
        'dump',
        'pending',
        '00000000-0000-0001-0000-123456789123',
        ['node'],
        'assertEmpty',
      ],
      [
        'dump',
        'pending',
        'some-initiator-uuid',
        [],
        'assertEmpty',
      ],
    ];
  }

}
