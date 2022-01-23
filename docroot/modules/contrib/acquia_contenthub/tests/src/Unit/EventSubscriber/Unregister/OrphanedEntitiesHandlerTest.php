<?php

namespace Drupal\Tests\acquia_contenthub\Unit\EventSubscriber\Unregister;

use Acquia\ContentHubClient\ContentHubClient;
use Drupal\acquia_contenthub\Client\ClientFactory;
use Drupal\acquia_contenthub\Event\AcquiaContentHubUnregisterEvent;
use Drupal\acquia_contenthub\EventSubscriber\Unregister\OrphanedEntitiesHandler;
use Drupal\Tests\UnitTestCase;
use Prophecy\Argument;

/**
 * Tests orphaned entities handler.
 *
 * @group acquia_contenthub
 * @coversDefaultClass \Drupal\acquia_contenthub\EventSubscriber\Unregister\OrphanedEntitiesHandler
 *
 * @package Drupal\Tests\acquia_contenthub\Unit\EventSubscriber\Unregister
 */
class OrphanedEntitiesHandlerTest extends UnitTestCase {

  /**
   * The OrphanedEntitiesHandler object.
   *
   * @var \Drupal\acquia_contenthub\EventSubscriber\Unregister\OrphanedEntitiesHandler
   */
  protected $orphanedEntitiesHandler;

  /**
   * Content Hub client.
   *
   * @var \Acquia\ContentHubClient\ContentHubClient
   */
  protected $client;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $factory = $this->prophesize(ClientFactory::class);
    $this->client = $this->prophesize(ContentHubClient::class);
    $factory
      ->getClient()
      ->willReturn($this->client->reveal());
    $this->orphanedEntitiesHandler = new OrphanedEntitiesHandler($factory->reveal());
  }

  /**
   * @covers ::onDeleteWebhook
   *
   * @dataProvider dataProvider
   *
   * @throws \Exception
   */
  public function testDeleteWebhookWithoutData(string $webhook_uuid, string $client_uuid, bool $delete_webhook_only): void {
    $data = ['total' => 0];
    $this->client
      ->listEntities(Argument::any())
      ->willReturn($data);

    $event = new AcquiaContentHubUnregisterEvent($webhook_uuid, $client_uuid, $delete_webhook_only);
    $this->orphanedEntitiesHandler->onDeleteWebhook($event);

    $data['total'] = $data['total'] <= 1 ? 0 : $data['total'];
    $this->assertEquals($event->getOrphanedEntitiesAmount(), $data['total']);

    $this->assertEmpty($event->getOrphanedEntities());
    $this->assertEquals($event->isDeleteWebhookOnly(), $delete_webhook_only);
    $this->assertEquals($event->getWebhookUuid(), $webhook_uuid);
    $this->assertEquals($event->getOriginUuid(), $client_uuid);
  }

  /**
   * @covers ::onDeleteWebhook
   *
   * @dataProvider dataProvider
   *
   * @throws \Exception
   */
  public function testDeleteWebhookWithNullData(string $webhook_uuid, string $client_uuid, bool $delete_webhook_only): void {
    $data = [
      'total' => 0,
      'data' => NULL,
    ];
    $this->client
      ->listEntities(Argument::any())
      ->willReturn($data);

    $event = new AcquiaContentHubUnregisterEvent($webhook_uuid, $client_uuid, $delete_webhook_only);
    $this->orphanedEntitiesHandler->onDeleteWebhook($event);

    $data['total'] = $data['total'] <= 1 ? 0 : $data['total'];
    $this->assertEquals($event->getOrphanedEntitiesAmount(), $data['total']);

    $this->assertEmpty($event->getOrphanedEntities());
    $this->assertEquals($event->isDeleteWebhookOnly(), $delete_webhook_only);
    $this->assertEquals($event->getWebhookUuid(), $webhook_uuid);
    $this->assertEquals($event->getOriginUuid(), $client_uuid);
  }

  /**
   * @covers ::onDeleteWebhook
   *
   * @dataProvider dataProvider
   *
   * @throws \Exception
   */
  public function testDeleteWebhookWithEmptyData(string $webhook_uuid, string $client_uuid, bool $delete_webhook_only): void {
    $data = [
      'total' => 0,
      'data' => [],
    ];
    $this->client
      ->listEntities(Argument::any())
      ->willReturn($data);

    $event = new AcquiaContentHubUnregisterEvent($webhook_uuid, $client_uuid, $delete_webhook_only);
    $this->orphanedEntitiesHandler->onDeleteWebhook($event);

    $data['total'] = $data['total'] <= 1 ? 0 : $data['total'];
    $this->assertEquals($event->getOrphanedEntitiesAmount(), $data['total']);

    $this->assertEmpty($event->getOrphanedEntities());
    $this->assertEquals($event->isDeleteWebhookOnly(), $delete_webhook_only);
    $this->assertEquals($event->getWebhookUuid(), $webhook_uuid);
    $this->assertEquals($event->getOriginUuid(), $client_uuid);
  }

  /**
   * @covers ::onDeleteWebhook
   *
   * @dataProvider dataProvider
   *
   * @throws \Exception
   */
  public function testDeleteWebhookWithValidData(string $webhook_uuid, string $client_uuid, bool $delete_webhook_only): void {
    $data = [
      'total' => 2,
      'data' => [
        [
          'uuid' => '18140f8d-e9a7-4ad0-948b-2467ddd91e97',
          'origin' => '4ca79984-d291-4e29-6ecc-be9cf72a290b',
          'modified' => '2021-09-15T10:02:06Z',
          'type' => 'drupal8_content_entity',
        ],
        [
          'uuid' => '28140f8d-e9a7-4ad0-948b-2467ddd91e97',
          'origin' => '2ca79984-d291-4e29-6ecc-be9cf72a290b',
          'modified' => '2021-09-15T10:02:06Z',
          'type' => 'drupal8_config_entity',
        ],
      ],
    ];
    $this->client
      ->listEntities(Argument::any())
      ->willReturn($data);

    $event = new AcquiaContentHubUnregisterEvent($webhook_uuid, $client_uuid, $delete_webhook_only);
    $this->orphanedEntitiesHandler->onDeleteWebhook($event);

    $data['total'] = $data['total'] <= 1 ? 0 : $data['total'];
    $this->assertEquals($event->getOrphanedEntitiesAmount(), $data['total']);

    $this->assertEqualsCanonicalizing($event->getOrphanedEntities(), $data['data']);
    $this->assertEquals($event->isDeleteWebhookOnly(), $delete_webhook_only);
    $this->assertEquals($event->getWebhookUuid(), $webhook_uuid);
    $this->assertEquals($event->getOriginUuid(), $client_uuid);
  }

  /**
   * Data provider.
   */
  public function dataProvider(): array {
    return [
      [
        '18140f8d-e9a7-4ad0-948b-2467ddd91e97',
        '18140f8d-e9a7-4ad0-948b-2467ddd91e97',
        FALSE,
      ],
    ];
  }

}
