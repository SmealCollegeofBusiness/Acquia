<?php

namespace Drupal\Tests\acquia_contenthub\Unit\PartialFetchOnImport;

use Acquia\ContentHubClient\ContentHubClient;
use Drupal\acquia_contenthub\Client\ClientFactory;
use Drupal\acquia_contenthub\EntityCdfSerializer;
use Drupal\acquia_contenthub_subscriber\CdfImporter;
use Drupal\acquia_contenthub_subscriber\SubscriberTracker;
use Drupal\Tests\acquia_contenthub\Unit\Helpers\LoggerMock;
use Drupal\Tests\acquia_contenthub\Unit\Helpers\RandomWebhookGeneratorTrait;
use Drupal\Tests\UnitTestCase;
use Prophecy\Argument;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * CDF importer unit tests.
 *
 * @group acquia_contenthub_subscriber
 *
 * @package Drupal\Tests\acquia_contenthub\Unit
 *
 * @coversDefaultClass \Drupal\acquia_contenthub_subscriber\CdfImporter
 */
class CdfImporterTest extends UnitTestCase {

  use RandomWebhookGeneratorTrait;

  /**
   * The CdfImporter object to test.
   *
   * @var \Drupal\acquia_contenthub_subscriber\CdfImporter
   */
  protected $cdfImporter;

  /**
   * The ClientFactory object available to alter.
   *
   * @var \Drupal\acquia_contenthub\Client\ClientFactory|\Prophecy\Prophecy\ObjectProphecy
   */
  protected $clientFactory;

  /**
   * The mocked logger channel.
   *
   * @var \Drupal\Tests\acquia_contenthub\Unit\Helpers\LoggerMock
   */
  protected $loggerMock;

  /**
   * {@inheritdoc}
   */
  protected function setup(): void {
    parent::setUp();

    $dispatcher = $this->prophesize(EventDispatcherInterface::class);
    $cdf_serializer = $this->prophesize(EntityCdfSerializer::class);
    $this->clientFactory = $this->prophesize(ClientFactory::class);
    $this->loggerMock = new LoggerMock();
    $sub_tracker = $this->prophesize(SubscriberTracker::class);

    $this->cdfImporter = new CdfImporter(
      $dispatcher->reveal(),
      $cdf_serializer->reveal(),
      $this->clientFactory->reveal(),
      $this->loggerMock,
      $sub_tracker->reveal(),
    );
  }

  /**
   * Tests with different cases for getWebhookUrlFromClientOrigin.
   *
   * @param string $origin
   *   The client origin uuid.
   * @param array $publisher_client
   *   The retrieved client array (uuid and name).
   * @param array $webhooks
   *   The mocked webhooks returned by ContentHubClient->getWebhooks() call.
   * @param string $expect
   *   The expectation of the assertion.
   *
   * @dataProvider getWebhookUrlFromClientOriginCases
   *
   * @throws \Exception
   */
  public function testGetWebhookUrlFromClientOrigin(string $origin, array $publisher_client, array $webhooks, string $expect): void {
    $client = $this->prophesize(ContentHubClient::class);
    $client->getWebHooks()->willReturn($webhooks);
    $client->cacheRemoteSettings(Argument::type('bool'));
    $client->getClientByUuid(Argument::type('string'))->willReturn($publisher_client);
    $this->clientFactory->getClient()->willReturn($client->reveal());

    $result = $this->cdfImporter->getWebhookUrlFromClientOrigin($origin);
    $this->assertTrue($result === $expect, sprintf(
      'Expected: "%s" - Actual: "%s"', $expect, $result
    ));
  }

  /**
   * Provides test cases for testGetWebhookUrlFromClientOrigin.
   *
   * @return array[]
   *   The list of test cases.
   */
  public function getWebhookUrlFromClientOriginCases(): array {
    return [
      [
        'f7598df2-dfe9-4ddd-b419-007e5b951ec7',
        [
          'uuid' => 'f7598df2-dfe9-4ddd-b419-007e5b951ec7',
          'name' => 'pub_client',
        ],
        [
          $this->getRandomWebhook(),
          $this->getRandomWebhook(
            [
              'client_name' => 'pub_client',
              'url' => 'https://valid_webhook.com/acquia-contenthub/webhook',
            ]),
          $this->getRandomWebhook(),
        ],
        'https://valid_webhook.com/acquia-contenthub/webhook',
      ],
      [
        'f7598df2-dfe9-4ddd-b419-007e5b951ec7',
        [],
        [
          $this->getRandomWebhook(),
          $this->getRandomWebhook(
            [
              'client_uuid' => 'f7598df2-dfe9-4ddd-b419-007e5b951ec7',
              'url' => 'https://valid_webhook.com/acquia-contenthub/webhook',
            ]),
          $this->getRandomWebhook(),
        ],
        '',
      ],
      [
        'f7598df2-dfe9-4ddd-b419-007e5b951ec7',
        ['uuid' => 'f7598df2-dfe9-4ddd-b419-007e5b951ec7'],
        [
          $this->getRandomWebhook(),
          $this->getRandomWebhook(
            [
              'client_uuid' => 'f7598df2-dfe9-4ddd-b419-007e5b951ec7',
              'url' => 'https://valid_webhook.com/acquia-contenthub/webhook',
            ]),
          $this->getRandomWebhook(),
        ],
        '',
      ],
      [
        'f7598df2-dfe9-4ddd-b419-007e5b951ec7',
        [
          'uuid' => '487be3b0-7cd0-4f5b-abbe-f819d6ec8d8c',
          'name' => 'pub_client',
        ],
        [
          $this->getRandomWebhook(),
          $this->getRandomWebhook(),
          $this->getRandomWebhook(),
        ],
        '',
      ],
      [
        '',
        [
          'uuid' => '487be3b0-7cd0-4f5b-abbe-f819d6ec8d8c',
          'name' => 'pub_client',
        ],
        [
          $this->getRandomWebhook(),
          $this->getRandomWebhook(),
          $this->getRandomWebhook(),
        ],
        '',
      ],
      [
        '',
        [],
        [],
        '',
      ],
      [
        'f7598df2-dfe9-4ddd-b419-007e5b951ec7',
        [],
        [],
        '',
      ],
    ];
  }

  /**
   * Tests a specific case when the Publisher's client is not available.
   *
   * @throws \Exception
   */
  public function testGetWebhookUrlFromClientOriginWhenPublisherClientIsUnavailable(): void {
    $client = $this->prophesize(ContentHubClient::class);
    $client->getWebHooks()->willReturn([$this->getRandomWebhook()]);
    $client->cacheRemoteSettings(Argument::type('bool'));
    $client->getClientByUuid(Argument::type('string'))->willReturn([]);
    $this->clientFactory->getClient()->willReturn($client->reveal());

    $result = $this->cdfImporter->getWebhookUrlFromClientOrigin('f7598df2-dfe9-4ddd-b419-007e5b951ec7');
    $error_msgs = $this->loggerMock->getErrorMessages();
    $expected = 'The Publisher site "f7598df2-dfe9-4ddd-b419-007e5b951ec7" is not registered properly to Content Hub.';
    $this->assertEquals($expected, $error_msgs[0]);
    $this->assertTrue($result === '', 'The result should be empty string.');
  }

}
