<?php

namespace Drupal\Tests\acquia_contenthub\Kernel\EventSubscriber\HandleWebhook;

use Acquia\Hmac\Key;
use Drupal\acquia_contenthub\Client\CdfMetricsManager;
use Drupal\acquia_contenthub\Event\HandleWebhookEvent;
use Drupal\acquia_contenthub_publisher\EventSubscriber\HandleWebhook\Purge;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\node\Entity\Node;
use Prophecy\Argument;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests queue and export table purge.
 *
 * @group acquia_contenthub
 *
 * @coversDefaultClass \Drupal\acquia_contenthub_publisher\EventSubscriber\HandleWebhook\Purge
 *
 * @package Drupal\Tests\acquia_contenthub\Kernel\EventSubscriber\HandleWebhook
 */
class PurgeTest extends EntityKernelTestBase {

  /**
   * Purge class instance.
   *
   * @var \Drupal\acquia_contenthub_publisher\EventSubscriber\HandleWebhook\Purge
   */
  protected $purge;

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
   * The queue factory.
   *
   * @var \Drupal\Core\Queue\QueueFactory
   */
  protected $queueFactory;

  /**
   * Database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Acquia Logger Channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $loggerChannel;

  /**
   * {@inheritdoc}
   */
  public static $modules = [
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
    Node::create([
      'type' => 'article',
      'title' => 'Test EN',
    ])->save();

    $this->queueFactory = $this->container->get('queue');
    $this->database = $this->container->get('database');
    $this->loggerChannel = $this->prophesize(LoggerChannelInterface::class)
      ->reveal();

    $cdf_metrics_manager = $this->prophesize(CdfMetricsManager::class);
    $cdf_metrics_manager->sendClientCdfUpdates(Argument::any())->shouldBeCalledOnce();
    $this->purge = new Purge($this->queueFactory, $this->loggerChannel, $cdf_metrics_manager->reveal(), $this->database);
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
   * Tests queue and export table purging.
   */
  public function testQueueAndTablePurge() {
    // Before purge.
    $queue = $this->queueFactory->get('acquia_contenthub_publish_export');
    $this->assertGreaterThan(0, $queue->numberOfItems());
    $this->assertGreaterThan(0, $this->getExportTableCount());

    $request = Request::createFromGlobals();
    $key = new Key('id', 'secret');
    $payload = [
      'crud' => 'purge',
      'status' => 'successful',
    ];
    $event = new HandleWebhookEvent($request, $payload, $key, $this->clientFactory->getClient());
    $this->purge->onHandleWebhook($event);

    // After purge.
    $this->assertEqual(0, $queue->numberOfItems());
    $this->assertEqual(0, $this->getExportTableCount());
  }

  /**
   * Fetch table rows count.
   *
   * @return int
   *   Table rows count.
   */
  protected function getExportTableCount(): int {
    return $this->database->select('acquia_contenthub_publisher_export_tracking', 'pet')
      ->fields('pet', [])
      ->countQuery()
      ->execute()
      ->fetchField();
  }

}
