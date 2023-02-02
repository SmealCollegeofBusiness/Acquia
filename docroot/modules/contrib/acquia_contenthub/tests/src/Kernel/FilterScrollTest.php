<?php

namespace Drupal\Tests\acquia_contenthub\Kernel;

use Acquia\ContentHubClient\ContentHubClient;
use Acquia\ContentHubClient\Settings;
use Drupal\acquia_contenthub\Client\ClientFactory;
use Drupal\acquia_contenthub_test\MockDataProvider;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\Tests\acquia_contenthub\Kernel\Traits\MetricsUpdateTrait;
use Prophecy\Argument;
use Psr\Http\Message\ResponseInterface;

/**
 * Tests that filter scroll is working as expected.
 *
 * @group acquia_contenthub
 *
 * @package Drupal\Tests\acquia_contenthub\Kernel
 */
class FilterScrollTest extends EntityKernelTestBase {

  use MetricsUpdateTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'user',
    'file',
    'node',
    'field',
    'depcalc',
    'acquia_contenthub',
    'acquia_contenthub_subscriber',
  ];

  /**
   * Contenthub client factory.
   *
   * @var \Drupal\acquia_contenthub\Client\ClientFactory
   */
  protected $clientFactory;

  /**
   * Mock of the ContentHub client.
   *
   * @var \Acquia\ContentHubClient\ContentHubClient
   */
  protected $contentHubClientMock;

  /**
   * Import queue instance.
   *
   * @var \Drupal\acquia_contenthub_subscriber\ContentHubImportQueue
   */
  protected $importQueue;

  /**
   * The Queue Worker.
   *
   * @var \Drupal\Core\Queue\QueueWorkerManager
   */
  protected $queueWorkerManager;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->importQueue = $this->container->get('acquia_contenthub_subscriber.acquia_contenthub_import_queue');
    $this->installSchema('acquia_contenthub_subscriber', ['acquia_contenthub_subscriber_import_tracking']);

    // Mock Content Hub stuff.
    $content_hub_settings = $this
      ->prophesize(Settings::class);
    $content_hub_settings
      ->getWebhook('uuid')
      ->willReturn('00000000-0000-460b-ac74-b6bed08b4441');
    $content_hub_settings
      ->toArray()
      ->willReturn(['name' => 'test-client']);
    $content_hub_settings
      ->getApiKey()
      ->willReturn('api_key');
    $content_hub_settings
      ->getSecretKey()
      ->willReturn('secret_key');
    $content_hub_settings
      ->getUuid()
      ->willReturn('origin_uuid');

    $content_hub_client = $this
      ->prophesize(ContentHubClient::class);

    $content_hub_client
      ->getSettings()
      ->willReturn($content_hub_settings);
    $content_hub_client
      ->getInterestsByWebhook('00000000-0000-460b-ac74-b6bed08b4441')
      ->willReturn([]);
    $content_hub_client
      ->addEntitiesToInterestListBySiteRole(Argument::any(), Argument::any(), Argument::type('array'))
      ->willReturn($this->prophesize(ResponseInterface::class)->reveal());
    $content_hub_client
      ->isFeatured()
      ->willReturn(FALSE);

    $content_hub_client
      ->putEntities(Argument::any())
      ->willReturn();
    $content_hub_client
      ->cancelScroll(Argument::any())
      ->willReturn([]);

    $this->mockMetricsCalls($content_hub_client);

    $this->contentHubClientMock = $content_hub_client;
    $this->queueWorkerManager = $this->container->get('plugin.manager.queue_worker');
  }

  /**
   * Tests whether filter scroll is working as per expectations.
   *
   * @param array $start_scroll
   *   Start scroll array with scroll id and item count.
   * @param array $scroll_ids
   *   Array of scroll ids for consecutive calls.
   * @param array $item_counts
   *   Array of item count for consecutive calls.
   * @param string $latest_scroll_id
   *   Latest non-null scroll id used for cancelling scroll.
   *
   * @dataProvider filterScrollDataProvider
   */
  public function testFilterScroll(array $start_scroll, array $scroll_ids, array $item_counts, string $latest_scroll_id): void {
    $this->assertEquals(0, $this->importQueue->getQueueCount());

    // This asserts that only latest non-null value
    // will be sent for cancellation.
    if (!empty($latest_scroll_id)) {
      $this
        ->contentHubClientMock
        ->cancelScroll($latest_scroll_id)
        ->shouldBeCalled()
        ->willReturn([]);
    }

    $this->alterContentHubMockPostCallback($start_scroll, $scroll_ids, $item_counts);
    $queue_item = new \stdClass();
    $queue_item->filter_uuid = '74a196d5-0000-0000-0000-000000000001';
    $filter_queue_worker = $this->queueWorkerManager->createInstance('acquia_contenthub_import_from_filters');
    $filter_queue_worker->processItem($queue_item);
    if (!empty($latest_scroll_id)) {
      $this->assertGreaterThan(0, $this->importQueue->getQueueCount());
    }
    else {
      $this->assertEquals(0, $this->importQueue->getQueueCount());
    }
  }

  /**
   * Alters ContentHub client mock.
   *
   * Depending on test data a specified set of responses will return.
   *
   * @param array $start_scroll
   *   Start scroll array with scroll id and item count.
   * @param array $scroll_ids
   *   Array of scroll ids for consecutive calls.
   * @param array $item_counts
   *   Array of item count for consecutive calls.
   */
  protected function alterContentHubMockPostCallback(array $start_scroll, array $scroll_ids, array $item_counts): void {
    $client_factory = $this
      ->prophesize(ClientFactory::class);

    $this->contentHubClientMock
      ->startScrollByFilter(Argument::any(), Argument::any(), Argument::type('integer'))
      ->willReturn($this->buildSearchResultResponse($start_scroll['start_scroll_id'], $start_scroll['start_count']));

    $responses = $this->responsesStackById($scroll_ids, $item_counts);
    $this
      ->contentHubClientMock
      ->continueScroll(Argument::any(), Argument::any())
      ->willReturn(...$responses);

    $client_factory->getSettings()->willReturn($this->contentHubClientMock->reveal()->getSettings());

    $client_factory->getClient()->willReturn($this->contentHubClientMock->reveal());

    $this->container->set('acquia_contenthub.client.factory', $client_factory->reveal());
  }

  /**
   * Data provider.
   *
   * @return array[]
   *   Data Provider.
   */
  public function filterScrollDataProvider(): array {
    return [
      [
        ['start_scroll_id' => 'scroll_id_1', 'start_count' => 10],
        ['scroll_id_1', NULL],
        [10, 0],
        'scroll_id_1',
      ],
      [
        ['start_scroll_id' => 'scroll_id_1', 'start_count' => 10],
        ['scroll_id_2', NULL],
        [10, 0],
        'scroll_id_2',
      ],
      [
        ['start_scroll_id' => 'scroll_id_1', 'start_count' => 100],
        ['scroll_id_2', 'scroll_id_3', NULL],
        [10, 10, 0],
        'scroll_id_3',
      ],
      [
        ['start_scroll_id' => NULL, 'start_count' => 100],
        ['scroll_id_2', 'scroll_id_3', NULL],
        [10, 10, 0],
        '',
      ],
      [
        ['start_scroll_id' => 'scroll_id_1', 'start_count' => 100],
        [NULL, 'scroll_id_3', NULL],
        [10, 10, 0],
        'scroll_id_1',
      ],
    ];
  }

  /**
   * Contains responses map.
   *
   * @param array $scroll_ids
   *   Array of scroll ids for consecutive calls.
   * @param array $item_counts
   *   Array of item count for consecutive calls.
   *
   * @return array
   *   Responses array.
   */
  protected function responsesStackById(array $scroll_ids, array $item_counts): array {
    $responses = [];
    $count = count($scroll_ids);
    for ($i = 0; $i < $count; $i++) {
      $scroll_id = $scroll_ids[$i];
      $item_count = $item_counts[$i];
      $responses[] = $this->buildSearchResultResponse($scroll_id, $item_count);
    }

    return $responses;
  }

  /**
   * Simulates test search response.
   *
   * @param string|null $scroll_id
   *   Scroll Id for this response.
   * @param int|null $item_count
   *   Search result item count.
   *
   * @return array
   *   Guzzle response.
   */
  protected function buildSearchResultResponse(?string $scroll_id = NULL, ?int $item_count = NULL): array {
    $items = [];
    for ($i = 0; $i < $item_count; $i++) {
      $items[] = [
        '_source' => [
          'uuid' => MockDataProvider::randomUuid(),
          'data' => [
            'type' => 'drupal8_content_entity',
          ],
        ],
      ];
    }

    return [
      '_scroll_id' => $scroll_id,
      'hits' => ['hits' => $items],
    ];
  }

}
