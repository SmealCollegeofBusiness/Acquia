<?php

namespace Drupal\Tests\acquia_contenthub\Kernel\EventSubscriber\HandleWebhook;

use Acquia\ContentHubClient\ContentHubClient;
use Acquia\Hmac\Key;
use Drupal\acquia_contenthub\Event\HandleWebhookEvent;
use Drupal\acquia_contenthub\EventSubscriber\HandleWebhook\RegenerateSharedSecret;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\acquia_contenthub\Kernel\Traits\AcquiaContentHubAdminSettingsTrait;
use Drupal\Tests\acquia_contenthub\Unit\Helpers\LoggerMock;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests regenerate shared secret webhook handler.
 *
 * @group acquia_contenthub
 *
 * @coversDefaultClass \Drupal\acquia_contenthub\EventSubscriber\HandleWebhook\RegenerateSharedSecret
 *
 * @requires module depcalc
 *
 * @package Drupal\Tests\acquia_contenthub\Kernel\EventSubscriber\HandleWebhook
 */
class RegenerateSharedSecretTest extends KernelTestBase {

  use AcquiaContentHubAdminSettingsTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'acquia_contenthub',
    'depcalc',
    'user',
  ];

  /**
   * Existing shared secret.
   */
  protected const EXISTING_SHARED_SECRET = 'existing-shared-secret';

  /**
   * Regenerated shared secret.
   */
  protected const REGENERATED_SHARED_SECRET = 'new-shared-secret';

  /**
   * HMAC key for HandleWebhook event.
   *
   * @var \Acquia\Hmac\Key
   */
  protected $key;

  /**
   * Request object.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * Mocked Content hub client.
   *
   * @var \Acquia\ContentHubClient\ContentHubClient|object
   */
  protected $client;

  /**
   * Content Hub config.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $config;

  /**
   * Logger mock.
   *
   * @var \Drupal\Tests\acquia_contenthub\Unit\Helpers\LoggerMock
   */
  protected $loggerMock;

  /**
   * {@inheritDoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->key = new Key('id', 'secret');
    $this->request = Request::createFromGlobals();
    $this->client = $this->prophesize(ContentHubClient::class)->reveal();
    $this->createAcquiaContentHubAdminSettings(['shared_secret' => self::EXISTING_SHARED_SECRET]);
    $this->config = $this->container->get('acquia_contenthub.config');
    $this->loggerMock = new LoggerMock();
  }

  /**
   * Tests shared secret is not updated.
   *
   * If CRUD is anything other than regenerate.
   *
   * @covers ::onHandleWebhook
   */
  public function testInvalidCrud(): void {
    $payload = ['crud' => 'invalid-crud'];
    $this->callHandleWebhook($payload);
    $this->assertEquals(
      self::EXISTING_SHARED_SECRET,
      $this->config->get('shared_secret'),
      'Shared secret has not been regenerated as crud was invalid.'
    );
  }

  /**
   * Tests shared secret is not present in payload.
   *
   * @covers ::onHandleWebhook
   */
  public function testInvalidPayload(): void {
    $payload = ['crud' => RegenerateSharedSecret::CRUD];
    $this->callHandleWebhook($payload);
    $this->assertEquals(
      self::EXISTING_SHARED_SECRET,
      $this->config->get('shared_secret'),
      'Shared secret has not been regenerated.'
    );
    $error_messages = $this->loggerMock->getErrorMessages();
    $this->assertEquals('Regenerated shared secret not found in the payload.', $error_messages[0]);
  }

  /**
   * Tests when CRUD and payload is valid.
   *
   * Shared secret is updated in CH settings config.
   *
   * @covers ::onHandleWebhook
   */
  public function testValidPayload(): void {
    $payload = [
      'crud' => RegenerateSharedSecret::CRUD,
      'message' => self::REGENERATED_SHARED_SECRET,
    ];
    $this->callHandleWebhook($payload);
    $this->assertEquals(
      self::REGENERATED_SHARED_SECRET,
      $this->config->get('shared_secret'),
      'Asserts that shared secret was updated in CH config settings.'
    );
    $info_messages = $this->loggerMock->getInfoMessages();
    $this->assertEquals('Regenerated shared secret has been updated in Content Hub settings config successfully.', $info_messages[0]);
  }

  /**
   * Calls handleWebhook method of event subscriber.
   *
   * @param array $payload
   *   Payload array.
   */
  protected function callHandleWebhook(array $payload): void {
    $event = new HandleWebhookEvent($this->request, $payload, $this->key, $this->client);
    $regenerateSharedSecret = new RegenerateSharedSecret($this->config, $this->loggerMock);
    $regenerateSharedSecret->onHandleWebhook($event);
    $this->assertEquals(200, $event->getResponse()->getStatusCode());
    $this->assertEquals('', $event->getResponse()->getContent());
  }

}
