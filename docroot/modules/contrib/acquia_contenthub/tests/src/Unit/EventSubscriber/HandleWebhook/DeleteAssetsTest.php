<?php

namespace Drupal\Tests\acquia_contenthub\Unit\EventSubscriber\HandleWebhook;

use Acquia\ContentHubClient\ContentHubClient;
use Acquia\ContentHubClient\Settings;
use Acquia\Hmac\Key;
use Drupal\acquia_contenthub\Client\ClientFactory;
use Drupal\acquia_contenthub\Event\HandleWebhookEvent;
use Drupal\acquia_contenthub_subscriber\EventSubscriber\HandleWebhook\DeleteAssets;
use Drupal\acquia_contenthub_subscriber\SubscriberTracker;
use Drupal\Core\Entity\EntityBase;
use Drupal\Tests\UnitTestCase;
use Prophecy\Argument;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests the DeleteAssets webhook event subscriber.
 *
 * @group acquia_contenthub
 *
 * @package Drupal\Tests\acquia_contenthub\Unit\EventSubscriber\HandleWebhook\DeleteAssets
 *
 * @covers \Drupal\acquia_contenthub\EventSubscriber\HandleWebhook\DeleteAssets
 */
class DeleteAssetsTest extends UnitTestCase {

  private const STATUS_SUCCESSFUL = 'successful';
  private const OPERATION_DELETE = 'delete';
  private const CLIENT_UUID = 'some-client-uuid';
  private const INITIATOR_UUID = 'some-initiator-uuid';
  private const EXISTING_ENTITY_UUID = 'some-existing-entity-uuid';
  private const NON_EXISTING_ENTITY_UUID = 'some-non-existing-entity-uuid';
  private const EXISTING_DISCONNECTED_ENTITY_UUID = 'some-existing-disconnected-entity-uuid';
  private const ASSET_TYPE_D8_CONTENT_ENTITY = 'drupal8_content_entity';
  private const WEBHOOK_UUID = 'webhook-uuid';

  /**
   * A Drupal entity.
   *
   * @var \Drupal\Core\Entity\Entity
   */
  private $entity;

  /**
   * The ContentHubClient Settings object.
   *
   * @var \Acquia\ContentHubClient\Settings
   */
  private $contentHubSettings;

  /**
   * The ContentHubClient.
   *
   * @var \Acquia\ContentHubClient\ContentHubClient
   */
  private $contentHubClient;

  /**
   * The ClientFactory.
   *
   * @var \Drupal\acquia_contenthub\Client\ClientFactory
   */
  private $clientFactory;

  /**
   * The HMAC Key.
   *
   * @var \Acquia\Hmac\Key
   */
  private $key;

  /**
   * The request object.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  private $request;

  /**
   * The SubscriberTracker.
   *
   * @var \Drupal\acquia_contenthub_subscriber\SubscriberTracker
   */
  private $tracker;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    $this->entity = $this->prophesize(EntityBase::class);
    $entity = $this->entity;

    $this->contentHubSettings = $this->prophesize(Settings::class);
    $this->contentHubSettings->getUuid()->willReturn(self::CLIENT_UUID);
    $this->contentHubSettings->getWebhook("uuid")->willReturn(self::WEBHOOK_UUID);

    $this->contentHubClient = $this->prophesize(ContentHubClient::class);
    $this->contentHubClient->getSettings()
      ->willReturn($this->contentHubSettings);

    $this->clientFactory = $this->prophesize(ClientFactory::class);
    $this->clientFactory->getClient()->willReturn($this->contentHubClient);

    $this->key = new Key('id', 'secret');

    $this->request = $this->prophesize(Request::class)->reveal();

    $this->tracker = $this->prophesize(SubscriberTracker::class);
    $this->tracker->delete(Argument::type('string'));
    $this->tracker->getEntityByRemoteIdAndHash(Argument::type('string'))
      ->will(function ($uuid) use ($entity) {
        switch (current($uuid)) {
          case self::NON_EXISTING_ENTITY_UUID:
            return NULL;

          case self::EXISTING_ENTITY_UUID:
            return $entity;

          case self::EXISTING_DISCONNECTED_ENTITY_UUID:
            return $entity;
        }
      });
    $this->tracker->getStatusByUuid(Argument::type('string'))
      ->will(function ($uuid) {
        switch (current($uuid)) {
          case self::EXISTING_DISCONNECTED_ENTITY_UUID:
            return SubscriberTracker::AUTO_UPDATE_DISABLED;

          default:
            return SubscriberTracker::IMPORTED;
        }
      });
  }

  /**
   * Test an unsuccessful payload status.
   */
  public function testNonSuccessfulStatus(): void {
    $payload = [
      'status' => 'some-status',
      'crud' => self::OPERATION_DELETE,
      'initiator' => self::INITIATOR_UUID,
      'assets' => [
        [
          'type' => self::ASSET_TYPE_D8_CONTENT_ENTITY,
          'uuid' => self::EXISTING_ENTITY_UUID,
        ],
      ],
    ];

    $this->triggerEvent(
      $this->createEvent($payload)
    );

    $this->assertNoOperationShouldBeDone();
  }

  /**
   * Test successful non-delete crud operations.
   */
  public function testNonDeleteCrud(): void {
    $payload = [
      'status' => self::STATUS_SUCCESSFUL,
      'crud' => 'some-funny-operation',
      'initiator' => self::INITIATOR_UUID,
      'assets' => [
        [
          'type' => self::ASSET_TYPE_D8_CONTENT_ENTITY,
          'uuid' => self::EXISTING_ENTITY_UUID,
        ],
      ],
    ];

    $this->triggerEvent(
      $this->createEvent($payload)
    );

    $this->assertNoOperationShouldBeDone();
  }

  /**
   * Test a webhook that was initiated by my own site.
   */
  public function testInitiatorSameAsClient(): void {
    $payload = [
      'status' => self::STATUS_SUCCESSFUL,
      'crud' => self::OPERATION_DELETE,
      'initiator' => self::CLIENT_UUID,
      'assets' => [
        [
          'type' => self::ASSET_TYPE_D8_CONTENT_ENTITY,
          'uuid' => self::EXISTING_ENTITY_UUID,
        ],
      ],
    ];

    $this->triggerEvent(
      $this->createEvent($payload)
    );

    $this->assertNoOperationShouldBeDone();
  }

  /**
   * Test empty asset payload functionality.
   */
  public function testAnEmptyAssetList(): void {
    $payload = [
      'status' => self::STATUS_SUCCESSFUL,
      'crud' => self::OPERATION_DELETE,
      'initiator' => self::INITIATOR_UUID,
      'assets' => [],
    ];

    $this->triggerEvent(
      $this->createEvent($payload)
    );

    $this->assertNoOperationShouldBeDone();
  }

  /**
   * Test an unsupported asset type.
   */
  public function testUnsupportedAssetType(): void {
    $payload = [
      'status' => self::STATUS_SUCCESSFUL,
      'crud' => self::OPERATION_DELETE,
      'initiator' => self::INITIATOR_UUID,
      'assets' => [
        [
          'type' => 'some_unsupported_type',
          'uuid' => self::EXISTING_ENTITY_UUID,
        ],
      ],
    ];

    $this->triggerEvent(
      $this->createEvent($payload)
    );

    $this->assertNoOperationShouldBeDone();
  }

  /**
   * Test an entity that does not exit.
   */
  public function testNonExistingEntity(): void {
    $payload = [
      'status' => self::STATUS_SUCCESSFUL,
      'crud' => self::OPERATION_DELETE,
      'initiator' => self::INITIATOR_UUID,
      'assets' => [
        [
          'type' => self::ASSET_TYPE_D8_CONTENT_ENTITY,
          'uuid' => self::NON_EXISTING_ENTITY_UUID,
        ],
      ],
    ];

    $this->tracker->delete(self::NON_EXISTING_ENTITY_UUID)->shouldBeCalledTimes(1);
    $this->contentHubClient->deleteInterest(self::NON_EXISTING_ENTITY_UUID, self::WEBHOOK_UUID)->shouldBeCalledTimes(1);
    $this->entity->delete()->shouldNotBeCalled();

    $this->triggerEvent(
      $this->createEvent($payload)
    );
  }

  /**
   * Test an incoming delete webhook.
   */
  public function testDeletionOfAnExistingEntity(): void {
    $payload = [
      'status' => self::STATUS_SUCCESSFUL,
      'crud' => self::OPERATION_DELETE,
      'initiator' => self::INITIATOR_UUID,
      'assets' => [
        [
          'type' => self::ASSET_TYPE_D8_CONTENT_ENTITY,
          'uuid' => self::EXISTING_ENTITY_UUID,
        ],
      ],
    ];

    $this->triggerEvent(
      $this->createEvent($payload)
    );

    $this->tracker->getEntityByRemoteIdAndHash(self::EXISTING_ENTITY_UUID)->shouldBeCalledTimes(1);
    $this->tracker->getStatusByUuid(self::EXISTING_ENTITY_UUID)->shouldBeCalledTimes(1);
    $this->entity->delete()->shouldBeCalledTimes(1);
  }

  /**
   * Test deletion of an existing disconnected local entity.
   */
  public function testDeletionOfAnExistingDisconnectedEntity(): void {
    $payload = [
      'status' => self::STATUS_SUCCESSFUL,
      'crud' => self::OPERATION_DELETE,
      'initiator' => self::INITIATOR_UUID,
      'assets' => [
        [
          'type' => self::ASSET_TYPE_D8_CONTENT_ENTITY,
          'uuid' => self::EXISTING_DISCONNECTED_ENTITY_UUID,
        ],
      ],
    ];

    $this->tracker->delete(self::EXISTING_DISCONNECTED_ENTITY_UUID)->shouldBeCalledTimes(1);

    $this->triggerEvent(
      $this->createEvent($payload)
    );

    $this->tracker->getEntityByRemoteIdAndHash(self::EXISTING_DISCONNECTED_ENTITY_UUID)->shouldBeCalledTimes(1);
    $this->tracker->getStatusByUuid(self::EXISTING_DISCONNECTED_ENTITY_UUID)->shouldBeCalledTimes(1);
    $this->entity->delete()->shouldNotBeCalled();
  }

  /**
   * Creates a HandleWebhookEvent.
   *
   * @param array $payload
   *   The payload.
   *
   * @return \Drupal\acquia_contenthub\Event\HandleWebhookEvent
   *   Handle webhook event.
   */
  private function createEvent(array $payload): HandleWebhookEvent {
    return new HandleWebhookEvent($this->request, $payload, $this->key, $this->contentHubClient->reveal());
  }

  /**
   * Triggers the DeleteAssets subscriber.
   *
   * @param \Drupal\acquia_contenthub\Event\HandleWebhookEvent $event
   *   Handle webhook event.
   *
   * @throws \Exception
   */
  private function triggerEvent(HandleWebhookEvent $event): void {
    (new DeleteAssets($this->tracker->reveal()))->onHandleWebhook($event);
  }

  /**
   * Assert that no operation (entity lookup and deletion) would take place.
   */
  private function assertNoOperationShouldBeDone(): void {
    $this->tracker->getEntityByRemoteIdAndHash()->shouldNotBeCalled();
    $this->tracker->delete()->shouldNotBeCalled();
    $this->entity->delete()->shouldNotBeCalled();
  }

}
