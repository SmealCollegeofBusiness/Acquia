<?php

namespace Drupal\Tests\acquia_contenthub\Kernel\EventSubscriber\HandleWebhook;

use Acquia\Hmac\Key;
use Drupal\acquia_contenthub\Event\HandleWebhookEvent;
use Drupal\acquia_contenthub_preview\EventSubscriber\HandleWebhook\PreviewEntity;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\Tests\acquia_contenthub\Kernel\Traits\AcquiaContentHubAdminSettingsTrait;
use Drupal\Tests\acquia_contenthub\Kernel\Traits\AssetHandlerTrait;
use Drupal\Tests\acquia_contenthub\Kernel\Traits\RequestTrait;

/**
 * Tests preview entity event handler.
 *
 * @group acquia_contenthub
 *
 * @coversDefaultClass \Drupal\acquia_contenthub_preview\EventSubscriber\HandleWebhook\PreviewEntity
 *
 * @requires module depcalc
 *
 * @package Drupal\Tests\acquia_contenthub\Kernel\EventSubscriber\HandleWebhook
 */
class PreviewEntityTest extends EntityKernelTestBase {

  use AcquiaContentHubAdminSettingsTrait;
  use RequestTrait;
  use AssetHandlerTrait;

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
   * The preview entity event subscriber.
   *
   * @var \Drupal\acquia_contenthub_preview\EventSubscriber\HandleWebhook\PreviewEntity
   */
  protected $previewEntity;

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'acquia_contenthub',
    'acquia_contenthub_subscriber',
    'acquia_contenthub_server_test',
    'acquia_contenthub_preview',
    'depcalc',
  ];

  /**
   * Cdf Importer.
   *
   * @var \Drupal\acquia_contenthub_subscriber\CdfImporter
   */
  protected $cdfImporter;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->configFactory = $this->container->get('config.factory');
    $this->createAcquiaContentHubAdminSettings();

    $this->installSchema('acquia_contenthub_subscriber', ['acquia_contenthub_subscriber_import_tracking']);
    $this->clientFactory = $this->container->get('acquia_contenthub.client.factory');

    /** @var \Drupal\acquia_contenthub_subscriber\CdfImporter cdfImporter */
    $this->cdfImporter = $this->container->get('acquia_contenthub_subscriber.cdf_importer');

    $this->previewEntity = new PreviewEntity($this->cdfImporter);
  }

  /**
   * Tests preview entity event handler.
   */
  public function testPreviewEntity(): void {
    $key = new Key('id', 'secret');
    $data = $this->getCdfArray('node_article_onparsecdf.json');
    $payload = [
      'crud' => 'preview',
      'status' => 'successful',
      'initiator' => 'client-uuid',
      'preview' => 'some-uuid',
      'cdf' => [
        'entities' => [$data],
      ],
    ];

    $event = new HandleWebhookEvent($this->createSignedRequest(), $payload, $key, $this->clientFactory->getClient());
    $this->previewEntity->onHandleWebhook($event);

    $this->assertNotEmpty($event->getResponse());
    $this->assertSame(200, $event->getResponse()->getStatusCode());
  }

}
