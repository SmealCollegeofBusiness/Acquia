<?php

namespace Drupal\Tests\acquia_contenthub\Kernel\EventSubscriber\HandleWebhook;

use Acquia\Hmac\Key;
use Drupal\acquia_contenthub\Event\HandleWebhookEvent;
use Drupal\acquia_contenthub_publisher\EventSubscriber\HandleWebhook\UpdatePublished;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\node\Entity\Node;
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

  protected function setUp(): void {
    parent::setUp();

    $this->installSchema('acquia_contenthub_publisher', ['acquia_contenthub_publisher_export_tracking']);

    $this->configFactory = $this->container->get('config.factory');
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
   * Get Acquia Content Hub settings.
   *
   * @return mixed
   *   Acquia Content Hub admin settings.
   */
  public function createAcquiaContentHubAdminSettings() {
    $admin_settings = $this->configFactory
      ->getEditable('acquia_contenthub.admin_settings');

    return $admin_settings
      ->set('client_name', 'test-client')
      ->set('origin', '00000000-0000-0001-0000-123456789123')
      ->set('api_key', '12312321312321')
      ->set('secret_key', '12312321312321')
      ->set('hostname', 'https://example.com')
      ->set('shared_secret', '12312321312321')
      ->save();
  }

  /**
   * Tests entity updated status.
   *
   * @param mixed $args
   *   Data.
   *
   * @dataProvider dataProvider
   */
  public function testUpdatePublished(...$args) {
    $key = new Key('id', 'secret');
    $request = Request::createFromGlobals();

    $payload = [
      'crud' => 'update',
      'status' => 'successful',
      'initiator' => $this->clientFactory->getSettings()->getUuid(),
      'assets' => [
        [
          'type' => $args[0],
          'uuid' => $args[1],
        ],
      ],
    ];
    $event = new HandleWebhookEvent($request, $payload, $key, $this->clientFactory->getClient());
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
