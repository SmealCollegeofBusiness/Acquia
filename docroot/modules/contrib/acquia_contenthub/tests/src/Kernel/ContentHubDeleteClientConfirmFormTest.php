<?php

namespace Drupal\Tests\acquia_contenthub\Kernel;

use Acquia\ContentHubClient\ContentHubClient;
use Acquia\ContentHubClient\Settings;
use Acquia\ContentHubClient\Webhook;
use Drupal\acquia_contenthub\Client\ClientFactory;
use Drupal\acquia_contenthub\Form\ContentHubDeleteClientConfirmForm;
use Drupal\acquia_contenthub_test\MockDataProvider;
use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\acquia_contenthub\Kernel\Traits\AcquiaContentHubAdminSettingsTrait;
use Drupal\Tests\acquia_contenthub\Kernel\Traits\WatchdogAssertsTrait;
use Prophecy\Argument;
use Psr\Http\Message\ResponseInterface;

/**
 * Tests that Client is deleted if webhook is not available.
 *
 * @group acquia_contenthub
 *
 * @coversDefaultClass \Drupal\acquia_contenthub\Form\ContentHubDeleteClientConfirmForm
 *
 * @requires module depcalc
 *
 * @package Drupal\Tests\acquia_contenthub\Kernel
 */
class ContentHubDeleteClientConfirmFormTest extends KernelTestBase {

  use WatchdogAssertsTrait;
  use AcquiaContentHubAdminSettingsTrait;

  /**
   * Content Hub client.
   *
   * @var \Acquia\ContentHubClient\ContentHubClient|\Prophecy\Prophecy\ObjectProphecy
   */
  protected $client;

  /**
   * Content Hub client factory.
   *
   * @var \Drupal\acquia_contenthub\Client\ClientFactory|\Prophecy\Prophecy\ObjectProphecy
   */
  protected $clientFactory;

  /**
   * Content Hub client settings.
   *
   * @var \Acquia\ContentHubClient\Settings
   */
  protected $settings;

  /**
   * Content Hub remote settings.
   *
   * @var array
   */
  protected $remoteSettings;

  /**
   * Config object.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $configFactory;

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'acquia_contenthub',
    'depcalc',
    'user',
    'dblog',
    'system',
  ];

  /**
   * {@inheritDoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->settings = $this->prophesize(Settings::class);
    $this->settings->getUuid()->willReturn('3a89ff1b-8869-419d-b931-f2282aca3e88');
    $this->settings->getName()->willReturn('foo');
    $this->settings->getUrl()->willReturn('http://www.example.com');
    $this->settings->getApiKey()->willReturn('apikey');
    $this->settings->getSecretKey()->willReturn('apisecret');

    $this->clientFactory = $this->prophesize(ClientFactory::class);
    $this->client = $this->prophesize(ContentHubClient::class);
    $delete_entity_response = $this->prophesize(ResponseInterface::class);
    $delete_entity_response->getStatusCode()->willReturn(202);

    $this->client
      ->getSettings()
      ->willReturn($this->settings->reveal());

    $this->client
      ->deleteClient('3a89ff1b-8869-419d-b931-f2282aca3e88')
      ->shouldBeCalled()
      ->willReturn($delete_entity_response->reveal());

    $this->client
      ->getWebHooks()
      ->willReturn($this->getWebHooks());

    $this->client
      ->listEntities(['origin' => '3a89ff1b-8869-419d-b931-f2282aca3e88'])
      ->willReturn(MockDataProvider::mockListEntities());

    $this->clientFactory
      ->getSettings()
      ->willReturn($this->settings->reveal());

    $this->client
      ->deleteWebhook((Argument::any()))
      ->shouldNotBeCalled();

    $this->container->set('acquia_contenthub.client.factory', $this->clientFactory->reveal());
    $this->installSchema('dblog', 'watchdog');
    $this->createAcquiaContentHubAdminSettings();
    $this->configFactory = $this->container->get('config.factory');
  }

  /**
   * Test that Client is deleted if remote webhook is not available.
   */
  public function testDeleteClientWithoutRemoteWebhook(): void {
    $this->setClientFactory('no_remote_webhook');
    $this->assertNotEmptyConfigs();

    $this->setConfirmForm();

    $this->assertLogMessage('acquia_contenthub', 'Client foo has been removed, no webhook was registered.');
    $this->assertLogMessage('acquia_contenthub', 'Local configurations is out of sync, http://www.example.com (3a89ff1b-8869-419d-b931-f2282aca3e88) was not registered to Content Hub, but remained in configuration.');

    $this->assertEmptyConfigs();
  }

  /**
   * Tests when Client is deleted when webhook in settings is not available.
   */
  public function testDeleteClientWithoutSettingWebhook(): void {
    $this->setClientFactory();
    $this->assertNotEmptyConfigs();

    $this->setConfirmForm();

    $this->assertLogMessage('acquia_contenthub', 'Client foo has been removed, no webhook was registered.');
    $this->assertLogMessage('acquia_contenthub', 'Webhook was not registered.');

    $this->assertEmptyConfigs();
  }

  /**
   * Tests when Client is deleted when webhook is registered to other client.
   */
  public function testDeleteClientWebhookInOtherClient(): void {
    $this->setClientFactory('other_remote_client');
    $this->assertNotEmptyConfigs();

    $this->setConfirmForm();

    $this->assertLogMessage('acquia_contenthub', 'Client foo has been removed, no webhook was registered.');
    $this->assertLogMessage('acquia_contenthub', 'The webhook is registered to other client. The configuration was outdated');

    $this->assertEmptyConfigs();
  }

  /**
   * Checks that CH admin settings are not empty.
   */
  public function assertNotEmptyConfigs(): void {
    $admin_settings = $this->configFactory->get('acquia_contenthub.admin_settings');
    $this->assertNotEmpty($admin_settings->get());
  }

  /**
   * Checks that CH admin settings are empty.
   */
  public function assertEmptyConfigs(): void {
    $admin_settings = $this->configFactory->get('acquia_contenthub.admin_settings');
    $this->assertEmpty($admin_settings->get());
  }

  /**
   * Builds and submits ContentHubDeleteClientConfirmForm.
   */
  public function setConfirmForm(): void {
    $form_state = new FormState();
    /** @var  \Drupal\Core\Form\FormBuilderInterface $form_builder */
    $form_builder = $this->container->get('form_builder');
    $form = $form_builder->buildForm(ContentHubDeleteClientConfirmForm::class, $form_state);
    $form_state->setTriggeringElement($form['delete_client_without_webhook']);
    $form_builder->submitForm(ContentHubDeleteClientConfirmForm::class, $form_state);

    $errors = $form_state->getErrors();
    $this->assertEmpty($errors);
  }

  /**
   * Returns webhooks list.
   *
   * @return \Acquia\ContentHubClient\Webhook[]
   *   Webhooks list.
   */
  public function getWebHooks(): array {
    return [
      new Webhook([
        'uuid' => '4e68da2e-a729-4c81-9c16-e4f8c05a11be',
        'client_uuid' => 'valid_client_uuid',
        'client_name' => 'client',
        'url' => 'http://example.com/acquia-contenthub/webhook',
        'version' => 2,
        'disable_retries' => 'false',
        'filters' => [
          'valid_filter_uuid_1',
          'valid_filter_uuid_2',
          'valid_filter_uuid_3',
        ],
        'status' => 'ENABLED',
        'is_migrated' => FALSE,
        'suppressed_until' => 0,
      ]),
    ];
  }

  /**
   * Sets client.factory service container of CH with required settings.
   *
   * @param string $case
   *   Represents test case for which client factory service container is set.
   */
  public function setClientFactory(string $case = ''): void {
    $this->settings->getWebhook('uuid')->willReturn('3a89ff1b-8869-419d-b931-f2282aca3e88');
    $this->settings->getWebhook()->willReturn('http://www.example.com');

    $this->setRemoteSettings($case);

    $this->client
      ->getSettings()
      ->willReturn($this->settings->reveal());
    $this->clientFactory
      ->getSettings()
      ->willReturn($this->settings->reveal());
    $this->client
      ->getRemoteSettings()
      ->willReturn($this->remoteSettings);
    $this->clientFactory
      ->getClient()
      ->willReturn($this->client->reveal());

    $this->container->set('acquia_contenthub.client.factory', $this->clientFactory->reveal());
  }

  /**
   * Sets remote settings for client factory of CH for different use cases.
   *
   * @param string $case
   *   Represents test case for which client factory service container is set.
   */
  public function setRemoteSettings(string $case): void {
    switch ($case) {
      case 'other_remote_client':
        $this->remoteSettings = [
          'clients' => [
            [
              'name' => 'foo',
              'uuid' => '3a89ff1b-8869-419d-b931-f2282aca3e89',
            ],
            [
              'name' => 'not_foo',
              'uuid' => '3a89ff1b-8869-419d-b931-f2282aca3e88',
            ],
          ],
          'success' => TRUE,
          'uuid' => MockDataProvider::randomUuid(),
          'webhooks' => [[
            'client_name' => 'not_foo',
            'client_uuid' => '7b89ff1b-8869-419d-b931-f2282aca7e99',
            'disable_retries' => FALSE,
            'url' => 'http://www.example.com',
            'uuid' => '3a89ff1b-8869-419d-b931-f2282aca3e88',
            'status' => 1,
          ],
          ],
          'shared_secret' => 'kh32j32132143143276bjsdnfjdhuf3',
        ];
        break;

      case 'no_remote_webhook':
        $this->remoteSettings = [
          'clients' => [
            [
              'name' => 'foo',
              'uuid' => '3a89ff1b-8869-419d-b931-f2282aca3e88',
            ],
          ],
          'success' => TRUE,
          'uuid' => MockDataProvider::randomUuid(),
          'webhooks' => [],
          'shared_secret' => 'kh32j32132143143276bjsdnfjdhuf3',
        ];
        break;

      default:
        $this->settings->getWebhook('uuid')->willReturn('');
        $this->remoteSettings = [];
        break;
    }
  }

}
