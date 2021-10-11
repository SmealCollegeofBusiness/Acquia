<?php

namespace Drupal\Tests\acquia_contenthub_publisher\Kernel\EventSubscriber\HandleWebhook;

use Acquia\Hmac\Key;
use Drupal\acquia_contenthub\Event\HandleWebhookEvent;
use Drupal\acquia_contenthub_publisher\EventSubscriber\HandleWebhook\ReExport;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\node\Entity\Node;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests Re-export functionality from a Webhook.
 *
 * @group acquia_contenthub
 *
 * @coversDefaultClass \Drupal\acquia_contenthub_publisher\EventSubscriber\HandleWebhook\ReExport
 *
 * @package Drupal\Tests\acquia_contenthub_publisher\Kernel\EventSubscriber\HandleWebhook
 */
class ReExportTest extends EntityKernelTestBase {

  /**
   * Re-Export instance.
   *
   * @var \Drupal\acquia_contenthub_publisher\EventSubscriber\HandleWebhook\ReExport
   */
  protected $reExport;

  /**
   * Config object.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $configFactory;

  /**
   * The Publisher Actions Service.
   *
   * @var \Drupal\acquia_contenthub_publisher\PublisherActions
   */
  protected $publisherActions;

  /**
   * The stream wrapper manager.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected $entityRepository;

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $logger;

  /**
   * Content Hub Client Factory.
   *
   * @var \Drupal\acquia_contenthub\Client\ClientFactory
   */
  protected $clientFactory;

  /**
   * Database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The Content Hub Settings Object.
   *
   * @var \Acquia\ContentHubClient\Settings
   */
  protected $settings;

  /**
   * The Publisher Tracker.
   *
   * @var \Drupal\acquia_contenthub_publisher\PublisherTracker
   */
  protected $tracker;

  /**
   * Publisher Queue.
   *
   * @var \Drupal\Core\Queue\QueueInterface
   */
  protected $publisherQueue;
  /**
   * A test node.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $node;

  /**
   * The remote origin that sends the webhook.
   *
   * @var string
   */
  protected $remoteOrigin;

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'acquia_contenthub',
    'acquia_contenthub_publisher',
    'depcalc',
    'node',
  ];

  public function setUp(): void {
    parent::setUp();

    $this->installSchema('acquia_contenthub_publisher', ['acquia_contenthub_publisher_export_tracking']);
    $this->remoteOrigin = '98213529-0000-2222-0000-123456789123';

    // Saving Content Hub Settings.
    $this->configFactory = $this->container->get('config.factory');
    $this->createAcquiaContentHubAdminSettings();
    $this->clientFactory = $this->container->get('acquia_contenthub.client.factory');

    // The services for publisher tracker and publisher queue.
    $this->tracker = $this->container->get('acquia_contenthub_publisher.tracker');
    $this->publisherQueue = $this->container->get('queue')->get('acquia_contenthub_publish_export');

    // Create a test node.
    $node_uuid = '98213529-0000-0001-0000-123456789123';
    $this->node = Node::create([
      'type' => 'article',
      'title' => 'Test EN',
      'uuid' => $node_uuid,
    ]);
    $this->node->save();

    // Saving the node would add it to the Publisher Queue.
    // Delete item from the queue and tracking table to start over.
    $this->deleteQueueItem($node_uuid);

    // Creating the ReExport EventSubscriber.
    $this->publisherActions = $this->container->get('acquia_contenthub_publisher.actions');
    $this->entityRepository = $this->container->get('entity.repository');
    $this->logger = $this->container->get('logger.factory');
    $this->database = $this->container->get('database');

    // This should re-add the queue item in the export queue.
    $this->reExport = new ReExport($this->publisherActions, $this->entityRepository, $this->logger);
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
      ->set('api_key', 'HqkhciruZhJxg6b844wc')
      ->set('secret_key', 'u8Pk4dTaeBWpRxA9pBvPJfru8BFSenKZi79CBKkk')
      ->set('hostname', 'https://dev-use1.content-hub-dev.acquia.com')
      ->set('shared_secret', '12312321312321')
      ->set('send_contenthub_updates', FALSE)
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
  public function testReExport(...$args) {

    $key = new Key('id', 'secret');
    $request = $this->createSignedRequest();

    $payload = [
      'uuid' => $args[1],
      'crud' => 'republish',
      'status' => 'successful',
      'initiator' => $this->remoteOrigin,
      'cdf' => [
        'type' => $args[0],
        'uuid' => $args[1],
        'dependencies' => [$args[1]],
      ],
    ];

    // Verify we are starting clean.
    $entity_status = $this->getStatusByUuid($args[1]);
    $this->assertEqual($args[2], $entity_status);

    // Handle Webhook Request Event to Re-export entity.
    $event = new HandleWebhookEvent($request, $payload, $key, $this->clientFactory->getClient($this->settings));
    $this->reExport->onHandleWebhook($event);

    // Verify item has been added to the publisher queue.
    $entity_status = $this->getStatusByUuid($args[1]);
    $this->assertEqual($args[3], $entity_status);

    // Verify response code.
    $response = $event->getResponse();
    $code = $response->getStatusCode();
    $this->assertEqual($code, $args[4]);

    // Verify response message.
    $message = $response->getBody()->getContents();
    $this->assertEqual($message, $args[5]);
  }

  /**
   * Data provider for testUpdatePublished.
   */
  public function dataProvider(): array {
    return [
      [
        'test_entity',
        '11111111-1111-1111-0000-111111111111',
        '',
        '',
        404,
        'The entity "test_entity:11111111-1111-1111-0000-111111111111" could not be found and thus cannot be re-exported from a webhook request by origin = 98213529-0000-2222-0000-123456789123.',
      ],
      [
        'node',
        '98213529-0000-0001-0000-123456789123',
        '',
        'queued',
        200,
        'Entity "node/98213529-0000-0001-0000-123456789123" successfully enqueued for export from webhook UUID = 98213529-0000-0001-0000-123456789123 by origin = 98213529-0000-2222-0000-123456789123.',
      ],
    ];
  }

  /**
   * Creates an test HMAC-Signed Request.
   *
   * @return \Symfony\Component\HttpFoundation\Request
   *   The HMAC signed request.
   */
  public function createSignedRequest(): Request {
    $request_global = Request::createFromGlobals();
    $request = $request_global->duplicate(NULL, NULL, NULL, NULL, NULL, [
      'REQUEST_URI' => 'http://example.com/acquia-contenthub/webhook',
      'SERVER_NAME' => 'example.com',
    ]);
    // @codingStandardsIgnoreStart
    $header = 'acquia-http-hmac headers="X-Custom-Signer1;X-Custom-Signer2",id="e7fe97fa-a0c8-4a42-ab8e-2c26d52df059",nonce="a9938d07-d9f0-480c-b007-f1e956bcd027",realm="CIStore",signature="0duvqeMauat7pTULg3EgcSmBjrorrcRkGKxRDtZEa1c=",version="2.0"';
    // @codingStandardsIgnoreEnd
    $request->headers->set('Authorization', $header);
    return $request;
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

  /**
   * Deletes Item from Queue and tracking table.
   *
   * @param string $uuid
   *   The Entity UUID.
   *
   * @throws \Exception
   */
  protected function deleteQueueItem(string $uuid) {
    $item_id = $this->tracker->getQueueId($uuid);
    if ($item_id) {
      $item = $this->publisherQueue->claimItem();
      if ($item_id === $item->item_id) {
        $this->publisherQueue->deleteItem($item);
      }
    }
    $this->tracker->delete($uuid);
  }

}
