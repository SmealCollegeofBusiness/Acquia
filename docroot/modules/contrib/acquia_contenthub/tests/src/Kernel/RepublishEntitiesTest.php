<?php

namespace Drupal\Tests\acquia_contenthub\Kernel;

use Acquia\ContentHubClient\ContentHubClient;
use Acquia\ContentHubClient\ObjectFactory;
use Acquia\ContentHubClient\Settings;
use Drupal\acquia_contenthub_subscriber\Exception\ContentHubImportException;
use Drupal\depcalc\DependencyStack;
use Drupal\Tests\acquia_contenthub\Kernel\PartialFetchOnImport\CdfImporterTest;
use GuzzleHttp\Psr7\Response;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;

/**
 * Republish Entities Test.
 *
 * @group acquia_contenthub_subscriber
 *
 * @requires module depcalc
 *
 * @package Drupal\Tests\acquia_contenthub\Kernel
 *
 * @coversDefaultClass \Drupal\acquia_contenthub_subscriber\CdfImporter
 */
class RepublishEntitiesTest extends CdfImporterTest {

  /**
   * Publisher webhook url.
   */
  protected const PUBLISHER_WEBHOOK_URL = 'http://example.com/acquia-contenthub/webhook';

  /**
   * Publisher origin uuid.
   */
  protected const PUBLISHER_ORIGIN = '621a4715-9ef5-4942-538c-cc1f091de756';

  /**
   * Subscriber site origin uuid.
   */
  protected const INITIATOR = '2d5ddb2b-b8dd-42af-be20-35d409eb473f';

  /**
   * Uuids queued in import queue.
   *
   * @var string[]
   */
  protected $queuedUuids = [
    'node' => 'b729de7e-913d-4dc1-aacf-c40cd5fef034',
    'path_alias' => 'd3598689-33aa-44e0-ad4e-9ae3955cb560',
  ];

  /**
   * Uuids which are missing from CH service.
   *
   * @var string[]
   */
  protected $missingUuids = [
    '3a22f0b0-7e5a-40f4-9c50-c47570970b2b',
    '5f93bd47-a102-4f21-bbbd-6e9268ada6a1',
  ];

  /**
   * Path of cdf document.
   */
  protected const CDF_PATH = 'node/node-republish-entities.json';

  /**
   * Tests that only one call is sent for all the entities.
   *
   * @covers ::requestToRepublishEntities
   *
   * @throws \Exception
   */
  public function testRepublishWebhookCalledOnce(): void {
    $stack = new DependencyStack();
    $client = $this->prophesize(ContentHubClient::class);
    $body = $this->mockClient($client);
    $exception_message = '';
    foreach ($this->queuedUuids as $type => $uuid) {
      $exception_message .= "The entity ($type, $uuid) could not be imported because the following dependencies are missing from Content Hub: " . implode(', ', $this->missingUuids) . "." . PHP_EOL;
    }
    foreach ($this->missingUuids as $uuid) {
      $exception_message .= "The entity with UUID = \"$uuid\" could not be imported because it is missing from Content Hub." . PHP_EOL;
    }

    $cdf_importer = $this->initializeCdfImporter($client);
    try {
      $cdf_importer->getCdfDocument($stack, ...array_values($this->queuedUuids));
    }
    catch (\Exception $e) {
      $this->assertInstanceOf(ContentHubImportException::class, $e);
      $this->assertEquals($exception_message, $e->getMessage());
      $info_messages = $this->loggerMock->getInfoMessages();
      $this->assertContains($body, $info_messages);
    }

  }

  /**
   * Builds mocked CH client and returns response body.
   *
   * @param \Prophecy\Prophecy\ObjectProphecy $client
   *   Mock ch client.
   *
   * @return string
   *   Response body.
   *
   * @throws \ReflectionException
   */
  protected function mockClient(ObjectProphecy $client): string {
    $initial_document = $this->createCdfDocumentFromFixturePath(self::CDF_PATH, $this->queuedUuids);
    $complete_document = $this->createCdfDocumentFromFixtureFile(self::CDF_PATH);
    $client
      ->getEntities(Argument::type('array'))
      ->shouldBeCalledTimes(2)
      ->willReturn(
      // Just the cdf doc for node and path alias.
        $initial_document,
        // CDF doc for all the dependencies for node and path alias.
        $complete_document);
    $client
      ->getClientByUuid(self::PUBLISHER_ORIGIN)
      ->shouldBeCalledOnce()
      ->willReturn([
        'name' => 'foo',
        'uuid' => self::PUBLISHER_ORIGIN,
      ]);
    $client
      ->cacheRemoteSettings(TRUE)
      ->shouldBeCalledOnce();
    $settings = new Settings('foo', self::INITIATOR, 'apikey', 'secretkey', 'https://example.com');
    $client
      ->getSettings()
      ->shouldBeCalledOnce()
      ->willReturn($settings);
    $webhook = ObjectFactory::getWebhook([
      'client_name' => 'foo',
      'client_uuid' => self::PUBLISHER_ORIGIN,
      'disable_retries' => FALSE,
      'url' => self::PUBLISHER_WEBHOOK_URL,
      'uuid' => '3a89ff1b-8869-419d-b931-f2282aca3e88',
      'status' => 1,
    ]);
    $client
      ->getWebHooks()
      ->shouldBeCalledOnce()
      ->willReturn([$webhook]);
    $entities_enqueued = array_map(static function ($type, $uuid) {
      return "$type/$uuid";
    }, array_keys($this->queuedUuids), $this->queuedUuids);
    $body = sprintf('Entities have been successfully enqueued by origin = %s. Entities: %s.',
      self::INITIATOR, implode(', ', $entities_enqueued));
    // Asserts that there is only one call to publisher webhook.
    $client
      ->request('post', self::PUBLISHER_WEBHOOK_URL, Argument::type('array'))
      ->shouldBeCalledOnce()
      ->willReturn(new Response(200, [], $body));
    return $body;
  }

}
