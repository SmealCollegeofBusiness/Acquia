<?php

namespace Drupal\Tests\acquia_contenthub\Kernel\EventSubscriber\HandleWebhook;

use Acquia\Hmac\Key;
use Drupal\acquia_contenthub\Event\HandleWebhookEvent;
use Drupal\acquia_contenthub_publisher\EventSubscriber\HandleWebhook\UpdatePublished;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\Tests\acquia_contenthub\Kernel\Traits\AcquiaContentHubAdminSettingsTrait;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests entity updated published status.
 *
 * @group acquia_contenthub
 *
 * @coversDefaultClass \Drupal\acquia_contenthub_publisher\EventSubscriber\HandleWebhook\UpdatePublished
 *
 * @package Drupal\Tests\acquia_contenthub\Kernel\EventSubscriber\HandleWebhook
 */
class UpdatePublishedTest extends EntityKernelTestBase {

  use AcquiaContentHubAdminSettingsTrait;

  /**
   * Update published instance.
   *
   * @var \Drupal\acquia_contenthub_publisher\EventSubscriber\HandleWebhook\UpdatePublished
   */
  protected $updatePublished;

  /**
   * Content Hub Client Factory.
   *
   * @var \Drupal\acquia_contenthub\Client\ClientFactory
   */
  protected $clientFactory;

  /**
   * Config object.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $configFactory;

  /**
   * Database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * A test node.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $node;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'acquia_contenthub',
    'acquia_contenthub_publisher',
    'acquia_contenthub_server_test',
    'depcalc',
    'node',
  ];

  /**
   * {@inheritDoc}
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installSchema('acquia_contenthub_publisher', ['acquia_contenthub_publisher_export_tracking']);

    $this->createAcquiaContentHubAdminSettings();
    $this->clientFactory = $this->container->get('acquia_contenthub.client.factory');

    // Create a test node.
    $this->node = Node::create([
      'type' => 'article',
      'title' => 'Test EN',
      'uuid' => '98213529-0000-0001-0000-123456789123',
    ]);
    $this->node->save();

    $this->database = $this->container->get('database');
    $this->updatePublished = new UpdatePublished($this->database);
  }

  /**
   * Tests entity updated status.
   *
   * @param mixed $args
   *   Data.
   *
   * @dataProvider dataProvider
   *
   * @throws \ReflectionException
   */
  public function testUpdatePublished(...$args) {
    $key = new Key('id', 'secret');
    $request = Request::createFromGlobals();

    $client = $this->clientFactory->getClient();

    $payload = [
      'crud' => 'update',
      'status' => 'successful',
      'initiator' => $client->getSettings()->getUuid(),
      'assets' => [
        [
          'type' => $args[0],
          'uuid' => $args[1],
        ],
      ],
    ];
    $event = new HandleWebhookEvent($request, $payload, $key, $client);
    $this->updatePublished->onHandleWebhook($event);

    $entity_status = $this->getStatusByUuid($args[1]);
    $this->assertEquals($entity_status, $args[2]);
  }

  /**
   * Data provider for testUpdatePublished.
   */
  public function dataProvider(): array {
    return [
      [
        'test_entity',
        '',
        '',
      ],
      [
        'drupal8_content_entity',
        '98213529-0000-0001-0000-123456789123',
        'confirmed',
      ],
    ];
  }

  /**
   * Fetch entity status.
   *
   * @param string $uuid
   *   Entity uuid.
   *
   * @return string
   *   Export status.
   */
  protected function getStatusByUuid(string $uuid): string {
    return $this->database->select('acquia_contenthub_publisher_export_tracking', 'acpet')
      ->fields('acpet', ['status'])
      ->condition('acpet.entity_uuid', $uuid)
      ->execute()
      ->fetchField();
  }

}
