<?php

namespace Drupal\Tests\acquia_contenthub_dashboard\Kernel;

use Acquia\ContentHubClient\ContentHubClient;
use Acquia\ContentHubClient\Settings;
use Drupal\acquia_contenthub\Client\ClientFactory;
use Drupal\acquia_contenthub\ContentHubConnectionManager;
use Drupal\acquia_contenthub_dashboard\AutoPublisherDiscoveryFilterHandler;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\Tests\acquia_contenthub\Kernel\Traits\AcquiaContentHubAdminSettingsTrait;
use Drupal\Tests\acquia_contenthub\Unit\Helpers\LoggerMock;
use Prophecy\Argument;

/**
 * @coversDefaultClass \Drupal\acquia_contenthub_dashboard\AutoPublisherDiscoveryFilterHandler
 *
 * @group acquia_contenthub_dashboard
 *
 * @package Drupal\Tests\acquia_contenthub_dashboard\Unit
 */
class AutoPublisherDiscoveryFilterHandlerTest extends EntityKernelTestBase {

  use AcquiaContentHubAdminSettingsTrait;

  /**
   * The ContentHub client factory.
   *
   * @var \Drupal\acquia_contenthub\Client\ClientFactory
   */
  protected $factory;

  /**
   * The Content Hub connection manager.
   *
   * @var \Drupal\acquia_contenthub\ContentHubConnectionManager
   */
  protected $chConnectionManager;

  /**
   * Content Hub client.
   *
   * @var \Acquia\ContentHubClient\ContentHubClient
   */
  protected $client;

  /**
   * Config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * CH allowed origins.
   *
   * @var \Drupal\acquia_contenthub_dashboard\Libs\ContentHubAllowedOrigins
   */
  protected $chAllowedOrigins;

  /**
   * The acquia_contenthub_dashboard logger channel mock.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $loggerMock;

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'acquia_contenthub',
    'acquia_contenthub_test',
    'acquia_contenthub_dashboard',
    'depcalc',
  ];

  /**
   * {@inheritDoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->createAcquiaContentHubAdminSettings([
      'webhook' => [
        'uuid' => '00000000-0000-0001-0000-123456789123',
      ],
    ]);
    $this->chConnectionManager = $this->prophesize(ContentHubConnectionManager::class);
    $this->factory = $this->prophesize(ClientFactory::class);
    $this->client = $this->prophesize(ContentHubClient::class);

    $this->factory
      ->getClient()
      ->willReturn($this->client->reveal());

    $this->container->set('acquia_contenthub.client.factory', $this->factory->reveal());
    $this->configFactory = $this->container->get('config.factory');
    $this->chAllowedOrigins = $this->container->get('acquia_contenthub_dashboard.ach_allowed_origins');
    $this->loggerMock = new LoggerMock();
  }

  /**
   * @covers ::updateDefaultClientPublisherFilterToWebhook
   *
   * @throws \Exception
   */
  public function testAddClientFilterToWebhook(): void {
    $filter = [
      'name' => 'filter_1',
      'uuid' => 'cfcd1dc9-7891-4e61-90cc-61ab43ca03c7',
      'webhook_uuid' => '00000000-0000-0001-0000-123456789123',
    ];

    $settings = $this->prophesize(Settings::class);
    $settings
      ->getWebhook('uuid')
      ->willReturn($filter['webhook_uuid']);

    $settings
      ->getName()
      ->willReturn($filter['name']);

    $this->client
      ->getSettings()
      ->willReturn($settings->reveal());

    $this->client
      ->queryEntities(Argument::any())
      ->willReturn($this->prepareQueryEntities());

    $this->chConnectionManager
      ->addDefaultFilterToWebhook(Argument::any(), Argument::any(), Argument::any())
      ->shouldBeCalled();

    $auto_pub_discovery = new AutoPublisherDiscoveryFilterHandler($this->factory->reveal(), $this->chConnectionManager->reveal(), $this->configFactory, $this->chAllowedOrigins, $this->loggerMock);
    $auto_pub_discovery->updateDefaultClientPublisherFilterToWebhook(TRUE);

    $config = $this->configFactory->getEditable('acquia_contenthub_dashboard.settings');
    $this->assertTrue($config->get('auto_publisher_discovery'));

    $allowed_origins = $config->get('allowed_origins');
    $this->assertEquals('https://www.example.com', $allowed_origins[0]);
  }

  /**
   * @covers ::removeClientPublisherFilterFromWebhook
   *
   * @dataProvider removeFilterDataProvider
   *
   * @throws \Exception
   */
  public function testRemoveClientFilterFromWebhook(array $remove_filter_assignment, array $expected_log_messages): void {
    $filter = [
      'name' => 'filter_1',
      'uuid' => 'cfcd1dc9-7891-4e61-90cc-61ab43ca03c7',
      'webhook_uuid' => '00000000-0000-0001-0000-123456789123',
    ];

    $this->client
      ->removeFilterFromWebhook($filter['uuid'], $filter['webhook_uuid'])
      ->shouldBeCalled()
      ->willReturn($remove_filter_assignment);

    $this->client
      ->getFilterByName($filter['name'])
      ->shouldBeCalled()
      ->willReturn(['uuid' => $filter['uuid']]);

    $auto_pub_discovery = new AutoPublisherDiscoveryFilterHandler($this->factory->reveal(), $this->chConnectionManager->reveal(), $this->configFactory, $this->chAllowedOrigins, $this->loggerMock);
    $auto_pub_discovery->removeClientPublisherFilterFromWebhook($filter['webhook_uuid'], $filter['name']);
    $log_messages = $this->loggerMock->getLogMessages();
    $this->assertEqualsCanonicalizing($log_messages, $expected_log_messages);
  }

  /**
   * @covers ::removeClientPublisherFilterFromWebhook
   */
  public function testRemoveClientFilterFromWebhookException(): void {
    $filter = [
      'name' => 'filter_1',
      'uuid' => 'cfcd1dc9-7891-4e61-90cc-61ab43ca03c7',
      'webhook_uuid' => '00000000-0000-0001-0000-123456789123',
    ];

    $this->client
      ->removeFilterFromWebhook($filter['uuid'], $filter['webhook_uuid'])
      ->shouldBeCalled()
      ->willThrow(new \Exception('API exception.'));

    $this->client
      ->getFilterByName($filter['name'])
      ->shouldBeCalled()
      ->willReturn(['uuid' => $filter['uuid']]);

    $auto_pub_discovery = new AutoPublisherDiscoveryFilterHandler($this->factory->reveal(), $this->chConnectionManager->reveal(), $this->configFactory, $this->chAllowedOrigins, $this->loggerMock);
    $auto_pub_discovery->removeClientPublisherFilterFromWebhook($filter['webhook_uuid'], $filter['name']);
    $log_messages = $this->loggerMock->getLogMessages();
    $this->assertEquals($log_messages[RfcLogLevel::ERROR][0], 'API exception.');
  }

  /**
   * Dataprovider for testRemoveClientFilterFromWebhook.
   */
  public function removeFilterDataProvider(): array {
    return [
      [
        [
          'success' => FALSE,
          'request_id' => 'bd1f7f1a-cca7-4531-a8c1-cf7a58e9d3b1',
          'error' => [
            'code' => 4000,
            'message' => 'unauthorized',
          ],
        ],
        [
          RfcLogLevel::ERROR => [
            'Filter cfcd1dc9-7891-4e61-90cc-61ab43ca03c7 could not be detached from webhook 00000000-0000-0001-0000-123456789123. Error: unauthorized',
          ],
        ],
      ],
      [
        [
          'success' => TRUE,
          'request_id' => 'bd1f7f1a-cca7-4531-a8c1-cf7a58e9d3b1',
        ],
        [
          RfcLogLevel::INFO => [
            'Filter cfcd1dc9-7891-4e61-90cc-61ab43ca03c7 successfully detached from webhook 00000000-0000-0001-0000-123456789123.',
          ],
        ],
      ],
    ];
  }

  /**
   * @covers ::saveDashboardConfig
   *
   * @dataProvider dataProvider
   *
   * @throws \Exception
   */
  public function testSaveDashboardConfig(string $config_key, $config_value, $expected): void {
    $auto_pub_discovery = new AutoPublisherDiscoveryFilterHandler($this->factory->reveal(), $this->chConnectionManager->reveal(), $this->configFactory, $this->chAllowedOrigins, $this->loggerMock);
    $auto_pub_discovery->saveDashboardConfig($config_key, $config_value);

    $config = $this->configFactory->getEditable('acquia_contenthub_dashboard.settings');
    $output = $config->get($config_key);
    $this->assertEquals($expected, $output);
  }

  /**
   * Data provider for testSaveDashbordConfig.
   */
  public function dataProvider(): array {
    return [
      [
        'auto_publisher_discovery',
        FALSE,
        FALSE,
      ],
      [
        'auto_publisher_discovery',
        TRUE,
        TRUE,
      ],
      [
        'allowed_origins',
        [],
        [],
      ],
      [
        'allowed_origins',
        ['https://www.example.com'],
        ['https://www.example.com'],
      ],
    ];
  }

  /**
   * Prepare query entities data.
   *
   * @return array
   *   Entity data.
   */
  protected function prepareQueryEntities(): array {
    return [
      'data' => [
        [
          'metadata' => [
            'settings' => [
              'webhook' => [
                'settings_url' => 'https://www.example.com',
              ],
            ],
          ],
          'attributes' => [
            'publisher' => [
              'und' => TRUE,
            ],
          ],
        ],
      ],
    ];
  }

}
