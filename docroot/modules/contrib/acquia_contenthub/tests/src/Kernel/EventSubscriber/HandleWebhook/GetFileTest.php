<?php

namespace Drupal\Tests\acquia_contenthub\Kernel\EventSubscriber\HandleWebhook;

use Acquia\Hmac\Key;
use Drupal\acquia_contenthub\Event\HandleWebhookEvent;
use Drupal\acquia_contenthub_publisher\EventSubscriber\HandleWebhook\GetFile;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\Tests\acquia_contenthub\Kernel\Traits\AcquiaContentHubAdminSettingsTrait;
use Drupal\Tests\acquia_contenthub\Kernel\Traits\RequestTrait;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Tests get file webhook handler.
 *
 * @group acquia_contenthub
 *
 * @coversDefaultClass \Drupal\acquia_contenthub_publisher\EventSubscriber\HandleWebhook\GetFile
 *
 * @requires module depcalc
 *
 * @package Drupal\Tests\acquia_contenthub\Kernel\EventSubscriber\HandleWebhook
 */
class GetFileTest extends EntityKernelTestBase {

  use AcquiaContentHubAdminSettingsTrait;
  use RequestTrait;

  /**
   * Get file instance.
   *
   * @var \Drupal\acquia_contenthub_publisher\EventSubscriber\HandleWebhook\GetFile
   */
  protected $getFile;

  /**
   * The stream wrapper manager.
   *
   * @var \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface
   */
  protected $streamWrapperManager;

  /**
   * The ContentHubCommonActions.
   *
   * @var \Drupal\acquia_contenthub\ContentHubCommonActions
   */
  protected $common;

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
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->createAcquiaContentHubAdminSettings();
    $this->installSchema('acquia_contenthub_subscriber', ['acquia_contenthub_subscriber_import_tracking']);
    $this->installConfig([
      'field',
      'filter',
      'node',
    ]);
    $this->clientFactory = $this->container->get('acquia_contenthub.client.factory');

    $this->streamWrapperManager = $this->container->get('stream_wrapper_manager');
    $this->common = $this->container->get('acquia_contenthub_common_actions');
    $this->getFile = new GetFile($this->common, $this->streamWrapperManager);
  }

  /**
   * Tests get file webhook handler.
   */
  public function testGetFile(): void {
    $scheme = 'public';
    $class = new \ReflectionClass(__CLASS__);
    $path = $class->getFileName();
    $file_path = dirname($path) . '/assets/test_file.txt';
    $key = new Key('id', 'secret');
    $payload = [
      'crud' => 'getFile',
      'status' => 'successful',
      'uuid' => 'some-uuid',
      'initiator' => 'some-initiator-uuid',
      'cdf' => [
        'uri' => $file_path,
        'scheme' => $scheme,
      ],
    ];

    $event = new HandleWebhookEvent($this->createSignedRequest(), $payload, $key, $this->clientFactory->getClient());
    $this->getFile->onHandleWebhook($event);
    $binary = new BinaryFileResponse($payload['cdf']['uri'], 200, [], TRUE, 'inline');

    $this->assertSame($binary->getStatusCode(), $event->getResponse()->getStatusCode());
    $this->assertNotEmpty($event->getResponse());
    $this->assertSame(200, $event->getResponse()->getStatusCode());
  }

}
