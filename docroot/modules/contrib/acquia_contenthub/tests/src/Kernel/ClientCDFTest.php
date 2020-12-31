<?php

namespace Drupal\Tests\acquia_contenthub\Kernel;

use Acquia\ContentHubClient\CDF\ClientCDFObject;
use Drupal\acquia_contenthub\AcquiaContentHubEvents;
use Drupal\acquia_contenthub\Event\BuildClientCdfEvent;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;

/**
 * Class ClientCDFTest.
 *
 * @group acquia_contenthub
 *
 * @package Drupal\Tests\acquia_contenthub\Kernel
 */
class ClientCDFTest extends EntityKernelTestBase {

  /**
   * Database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Client uuid.
   *
   * @var string
   */
  protected $clientUuid;


  /**
   * Config object.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $adminSettings;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcher
   */
  protected $dispatcher;

  /**
   * Client CDF object.
   *
   * @var \Acquia\ContentHubClient\CDF\ClientCDFObject
   */
  protected $clientCDFObject;


  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = [
    'depcalc',
    'acquia_contenthub',
    'acquia_contenthub_publisher',
    'acquia_contenthub_subscriber',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->clientUuid = '2d5ddb2b-b8dd-42af-be20-35d409eb473f';
    $this->database = \Drupal::database();
    $this->installSchema('acquia_contenthub_subscriber', 'acquia_contenthub_subscriber_import_tracking');
    $this->installSchema('acquia_contenthub_publisher', 'acquia_contenthub_publisher_export_tracking');
    $this->dispatcher = $this->container->get('event_dispatcher');

    $this->adminSettings = \Drupal::configFactory()
      ->getEditable('acquia_contenthub.admin_settings');

    $this
      ->adminSettings
      ->set('client_name', 'test-client')
      ->set('origin', '00000000-0000-0001-0000-123456789123')
      ->set('api_key', '123123123123123')
      ->set('secret_key', '123123123123123987654398765439876543')
      ->set('hostname', 'https://example.com')
      ->set('shared_secret', '12312321312321')
      ->save();
  }

  /**
   * Tests generation of successive clients.
   *
   * @param array $subscriber_records
   *   Data for subscriber tracking table.
   * @param array $publisher_records
   *   Data for publisher tracking table.
   *
   * @throws \Exception
   *
   * @dataProvider providerTestSuccessiveClientGeneration
   */
  public function testSuccessiveClientGeneration(array $subscriber_records, array $publisher_records) {

    /** @var \Drupal\acquia_contenthub\Client\ClientFactory $clientFactory */
    $clientFactory = \Drupal::service('acquia_contenthub.client.factory');
    $clientSettings = $clientFactory->getClient()->getSettings();

    $import_query = $this->database
      ->insert('acquia_contenthub_subscriber_import_tracking')
      ->fields([
        'entity_uuid',
        'entity_type',
        'entity_id',
        'status',
        'first_imported',
        'last_imported',
        'hash',
      ]);

    foreach ($subscriber_records as $object) {
      $import_query->values([
        $object['entity_uuid'],
        $object['entity_type'],
        $object['entity_id'],
        $object['status'],
        $object['first_imported'],
        $object['last_imported'],
        $object['hash'],
      ]);
    }

    $import_query->execute();

    $export_query = $this->database
      ->insert('acquia_contenthub_publisher_export_tracking')
      ->fields([
        'entity_type',
        'entity_id',
        'entity_uuid',
        'status',
        'created',
        'modified',
        'hash',
      ]);

    foreach ($publisher_records as $object) {
      $export_query->values([
        $object['entity_type'],
        $object['entity_id'],
        $object['entity_uuid'],
        $object['status'],
        $object['created'],
        $object['modified'],
        $object['hash'],
      ]);
    }

    $export_query->execute();

    $event = new BuildClientCdfEvent(ClientCDFObject::create($this->clientUuid, ['settings' => $clientSettings->toArray()]));
    $this->dispatcher->dispatch(AcquiaContentHubEvents::BUILD_CLIENT_CDF, $event);
    $baseClient = $event->getCdf();

    $event = new BuildClientCdfEvent(ClientCDFObject::create($this->clientUuid, ['settings' => $clientSettings->toArray()]));
    $this->dispatcher->dispatch(AcquiaContentHubEvents::BUILD_CLIENT_CDF, $event);
    $successiveClient = $event->getCdf();

    $this->assertTrue(($baseClient->getAttribute('hash')->getValue() === $successiveClient->getAttribute('hash')->getValue()), 'Hashes match after two seconds.');
  }

  /**
   * Provides sample data for settings and client tests.
   *
   * @return array
   *   Settings.
   */
  public function providerTestSuccessiveClientGeneration() {
    $time = date('c');
    return [
      [
        [
          [
            'entity_uuid' => '3d5ddb2b-b8dd-42af-be20-35d409eb473f',
            'entity_type' => 'drupal8_content_entity',
            'entity_id' => '1',
            'status' => 'imported',
            'first_imported' => $time,
            'last_imported' => $time,
            'hash' => '111',
          ],
        ],
        [
          [
            'entity_type' => 'drupal8_content_entity',
            'entity_id' => '1',
            'entity_uuid' => '4d5ddb2b-b8dd-42af-be20-35d409eb473f',
            'status' => 'confirmed',
            'created' => $time,
            'modified' => $time,
            'hash' => '111',
          ],
        ],
      ],
    ];
  }

}
