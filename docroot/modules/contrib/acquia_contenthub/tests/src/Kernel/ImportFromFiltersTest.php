<?php

namespace Drupal\Tests\acquia_contenthub\Kernel;

use Acquia\ContentHubClient\ContentHubClient;
use Acquia\ContentHubClient\Settings;
use Drupal\acquia_contenthub\Client\ClientFactory;
use Drupal\acquia_contenthub_subscriber\ContentHubImportQueueByFilter;
use Drupal\acquia_contenthub_test\MockDataProvider;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use GuzzleHttp\Psr7\Response;

/**
 * Tests that imports from filters work properly.
 *
 * @group acquia_contenthub
 *
 * @package Drupal\Tests\acquia_contenthub\Kernel
 */
class ImportFromFiltersTest extends EntityKernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'user',
    'file',
    'node',
    'field',
    'depcalc',
    'acquia_contenthub',
    'acquia_contenthub_subscriber',
    'acquia_contenthub_test',
  ];

  /**
   * Contenthub client factory.
   *
   * @var \Drupal\acquia_contenthub\Client\ClientFactory
   */
  protected $clientFactory;

  /**
   * Import queue instance.
   *
   * @var \Drupal\acquia_contenthub_subscriber\ContentHubImportQueue
   */
  protected $importQueue;

  /**
   * Mock of the ContentHub client.
   *
   * @var \Acquia\ContentHubClient\ContentHubClient
   */
  private $contentHubClientMock;

  /**
   * Search scroll ID.
   *
   * @var string
   */
  private $scrollID;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->installSchema('acquia_contenthub_subscriber', ['acquia_contenthub_subscriber_import_tracking']);
    $this->importQueue = $this->container->get('acquia_contenthub_subscriber.acquia_contenthub_import_queue');
    $this->scrollID = $this->randomString(60);

    // Mock Content Hub stuff.
    $content_hub_settings = $this
      ->getMockBuilder(Settings::class)
      ->disableOriginalConstructor()
      ->getMock();
    $content_hub_settings
      ->method('getWebhook')
      ->willReturn('00000000-0000-460b-ac74-b6bed08b4441');

    $content_hub_client = $this
      ->getMockBuilder(ContentHubClient::class)
      ->disableOriginalConstructor()
      ->setMethods([
        'post',
        'put',
        'delete',
        'getSettings',
        'listFiltersForWebhook',
        'getInterestsByWebhook',
      ])
      ->getMock();
    $content_hub_client
      ->method('getSettings')
      ->willReturn($content_hub_settings);
    $content_hub_client->method('getInterestsByWebhook')
      ->willReturn([]);

    $content_hub_client
      ->method('put')
      ->will($this->returnCallback([$this, 'returnEmptyResponse']));
    $content_hub_client
      ->method('delete')
      ->will($this->returnCallback([$this, 'returnEmptyResponse']));

    $this->contentHubClientMock = $content_hub_client;
  }

  /**
   * Tests import from filter.
   *
   * @param mixed $filtersUuids
   *   Filters Uuids.
   * @param mixed $expectedItems
   *   Expected Items.
   * @param mixed $responses
   *   Responses as callbacks.
   *
   * @dataProvider dataProviderForImportFromFilters
   *
   * @throws \Exception
   */
  public function testImportFromFilters($filtersUuids, $expectedItems, $responses) {
    $this->assertEquals(0, $this->importQueue->getQueueCount());

    $this->alterContentHubMockPostCallback($responses);
    $filterQueue = $this->container->get('acquia_contenthub_subscriber.acquia_contenthub_import_queue_by_filter');
    $this->processFilterQueue($filterQueue, $filtersUuids);

    $this->assertEqual($expectedItems, $this->importQueue->getQueueCount());
  }

  /**
   * Data provider for testImportFromFilters.
   *
   * @return array
   *   Returns test dataset which contains:
   *    - Filter UUIDs list.
   *    - Expected items in the import queue.
   *    - ID of responses stack.
   */
  public function dataProviderForImportFromFilters() {
    return [
      [
        ['74a196d5-0000-0000-0000-000000000001'],
        1,
        'one_filter',
      ],
      [
        [
          '74a196d5-0000-0000-0000-000000000001',
          '74a196d5-0000-0000-0000-000000000002',
        ],
        2,
        'multiple_filters',
      ],
      [
        ['74a196d5-0000-0000-0000-000000000001'],
        0,
        'empty_filter',
      ],
      [
        ['74a196d5-0000-0000-0000-000000000001'],
        1,
        'filters_chunk_1',
      ],
      [
        ['74a196d5-0000-0000-0000-000000000001'],
        1,
        'filters_chunk_2',
      ],
      [
        ['74a196d5-0000-0000-0000-000000000001'],
        2,
        'filters_chunk_3',
      ],
    ];
  }

  /**
   * Returns empty response.
   *
   * @return \GuzzleHttp\Psr7\Response
   *   Guzzle response.
   */
  public function returnEmptyResponse() {
    return new Response(200, [], "");
  }

  /**
   * Contains responses map.
   *
   * @param string $id
   *   Responses stack ID.
   *
   * @return mixed
   *   Responses.
   */
  protected function responsesStackById($id) {
    $responses = [
      'one_filter' => [
        // Start scrolling.
        $this->buildSearchResultResponse(3),
        // Continue scrolling.
        $this->buildSearchResultResponse(3),
        // Final page.
        $this->buildSearchResultResponse(0),
        $this->returnEmptyResponse(),
      ],
      'multiple_filters' => [
        // 1 filter. Start scrolling.
        $this->buildSearchResultResponse(3),
        // 1 filter. Continue scroll.
        $this->buildSearchResultResponse(3),
        // 1 filter. Final page.
        $this->buildSearchResultResponse(0),
        // 2 filter. Start scrolling.
        $this->buildSearchResultResponse(3),
        $this->buildSearchResultResponse(3),
        $this->buildSearchResultResponse(0),
        $this->returnEmptyResponse(),
      ],
      'empty_filter' => [
        $this->buildSearchResultResponse(0),
        $this->returnEmptyResponse(),
      ],
      'filters_chunk_1' => [
        $this->buildSearchResultResponse(49),
        $this->buildSearchResultResponse(0),
        $this->returnEmptyResponse(),
      ],
      'filters_chunk_2' => [
        $this->buildSearchResultResponse(50),
        $this->buildSearchResultResponse(0),
        $this->returnEmptyResponse(),
      ],
      'filters_chunk_3' => [
        $this->buildSearchResultResponse(10),
        $this->buildSearchResultResponse(10),
        $this->buildSearchResultResponse(10),
        $this->buildSearchResultResponse(10),
        $this->buildSearchResultResponse(10),
        $this->buildSearchResultResponse(1),
        $this->buildSearchResultResponse(0),
        $this->returnEmptyResponse(),
      ],
    ];

    return $responses[$id];
  }

  /**
   * Simulates test search response.
   *
   * @param int $foundItems
   *   Search result items.
   *
   * @return \GuzzleHttp\Psr7\Response
   *   Guzzle response.
   */
  protected function buildSearchResultResponse($foundItems) {
    $items = [];
    for ($i = 0; $i < $foundItems; $i++) {
      $items[] = [
        '_source' => [
          'uuid' => MockDataProvider::randomUuid(),
          'data' => [
            'type' => 'drupal8_content_entity',
          ],
        ],
      ];
    }

    $body = json_encode([
      '_scroll_id' => $this->scrollID,
      'hits' => ['hits' => $items],
    ]);

    return new Response(200, [], $body);
  }

  /**
   * Alters ContentHub client mock.
   *
   * Depending on test data a specified set of responses will return.
   *
   * @param string $responsesStackId
   *   Responses stack id.
   */
  protected function alterContentHubMockPostCallback(string $responsesStackId) {
    $clientFactory = $this
      ->getMockBuilder(ClientFactory::class)
      ->disableOriginalConstructor()
      ->getMock();

    $responses = $this->responsesStackById($responsesStackId);
    $this->contentHubClientMock
      ->method('post')
      ->will($this->onConsecutiveCalls(...$responses));

    $clientFactory->method('getClient')->willReturn($this->contentHubClientMock);

    $this->container->set('acquia_contenthub.client.factory', $clientFactory);
  }

  /**
   * Processes items from filter queue and executes batch.
   *
   * @param \Drupal\acquia_contenthub_subscriber\ContentHubImportQueueByFilter $filterQueue
   *   Filter Queue.
   * @param array $filtersUuids
   *   Filter Uuids.
   */
  protected function processFilterQueue(ContentHubImportQueueByFilter $filterQueue, array $filtersUuids): void {
    $filterQueue->process($filtersUuids);

    $batch =& batch_get();
    $batch['progressive'] = FALSE;
    batch_process();
  }

}
