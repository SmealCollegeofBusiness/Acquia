<?php

namespace Drupal\Tests\acquia_contenthub\Kernel;

use Acquia\ContentHubClient\ContentHubClient;
use Acquia\ContentHubClient\Settings;
use Drupal\acquia_contenthub\Client\ClientFactory;
use Drupal\acquia_contenthub_publisher\Controller\StatusReportController;
use Drupal\acquia_contenthub_test\MockDataProvider;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests the Content Hub settings form.
 *
 * @group acquia_contenthub
 */
class ContentHubStatusReportTest extends EntityKernelTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = [
    'depcalc',
    'acquia_contenthub',
    'acquia_contenthub_test',
    'acquia_contenthub_publisher',
    'user',
  ];

  /**
   * Mocked client factory.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy
   */
  protected $clientFactory;

  /**
   * Mocked client.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy
   */
  protected $client;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $settings = $this->prophesize(Settings::class);
    $settings->getUuid()->willReturn('3a89ff1b-8869-419d-b931-f2282aca3e88');
    $settings->getName()->willReturn('foo');
    $settings->getUrl()->willReturn('http://www.example.com');
    $settings->getApiKey()->willReturn('apikey');
    $settings->getSecretKey()->willReturn('apisecret');

    $this->client = $this->prophesize(ContentHubClient::class);
    $clientFactory = $this->prophesize(ClientFactory::class);
    $clientFactory->getSettings()->willReturn($settings->reveal());
    $this->client->getSettings()->willReturn($settings->reveal());
    $this->clientFactory = $clientFactory;
  }

  /**
   * Test Status Report with No Clients.
   */
  public function testContentHubStatusReportWithNoClients() {
    $options = [
      "from" => 0,
      "query" => [
        "bool" => [
          "filter" => [
            [
              "term" => ["data.type" => 'client'],
            ],
          ],
        ],
      ],
      "size" => 50,
      "sort" => [
        "data.modified" => "desc",
      ],
    ];
    $this->client->searchEntity($options)->willReturn([]);
    $this->clientFactory->getClient()->willReturn($this->client->reveal());
    $this->container->set('acquia_contenthub.client.factory',
      $this->clientFactory->reveal());
    $controller = new StatusReportController($this->container->get('acquia_contenthub.client.factory'), $this->container->has('pager.manager') ? $this->container->get('pager.manager') : NULL);
    $request = new Request();
    $build = $controller->statusReportPage($request);

    $this->assertFalse(array_key_exists(0, $build[0]['clients_table']), 'Empty Search Response returns no data.');
  }

  /**
   * Tests report with a variety of data.
   */
  public function testContentHubStatusReportWithClients() {
    $options = [
      "from" => 0,
      "query" => [
        "bool" => [
          "filter" => [
            [
              "term" => ["data.type" => 'client'],
            ],
          ],
        ],
      ],
      "size" => 50,
      "sort" => [
        "data.modified" => "desc",
      ],
    ];
    $this->client->searchEntity($options)->willReturn(MockDataProvider::searchResponse());

    // Responses for each webhook. See MockDataProvider::searchResponse().
    $this->client->getInterestsByWebhook('00000000-55aa-42f5-50d1-2e35b72ae26d')->willReturn([]);
    $this->client->getInterestsByWebhook('00000000-4b0b-4c99-5b1d-0177597c2ca7')->willReturn(['00000000-12bc-442f-46f5-d2694d553429']);
    $this->client->getInterestsByWebhook('00000000-72b6-4df8-710b-59790112588e')->willReturn(['00000000-42bf-4860-6d03-4e3411ee32b4']);
    $this->client->getInterestsByWebhook('00000000-5ac3-4e9f-7fe9-776b56a389c0')->willReturn(['00000000-9987-4b2a-74b9-d758c8b60d12']);

    $this->clientFactory->getClient()->willReturn($this->client->reveal());
    $this->container->set('acquia_contenthub.client.factory', $this->clientFactory->reveal());
    $controller = new StatusReportController($this->container->get('acquia_contenthub.client.factory'), $this->container->has('pager.manager') ? $this->container->get('pager.manager') : NULL);
    $request = new Request();
    $build = $controller->statusReportPage($request);

    $r = new \ReflectionClass($controller);
    $method = $r->getMethod('getTimeAgo');
    $method->setAccessible(TRUE);
    $last_updated_time = $method->invoke($controller, 1483228800);

    // Test response as publisher only.
    $this->assertTrue(strip_tags((string) $build[0]['clients_table'][3]['name']['#markup']) === 'pub',
      'Publisher contains correct name.');
    $this->assertTrue(strip_tags((string) $build[0]['clients_table'][3]['type']['#markup']) === 'Publisher',
      'Publisher contains expected type.');
    $this->assertTrue(strip_tags((string) $build[0]['clients_table'][3]['uuid']['#markup']) === 'http://pubonline.example.com',
      'Publisher webhook domain is correct.');
    $this->assertTrue(strip_tags((string) $build[0]['clients_table'][3]['status']['#markup']) === 'In Progress',
      'Publisher status is correct.');
    $this->assertTrue(strip_tags((string) $build[0]['clients_table'][3]['percent_exported']['#markup']) === '86%',
      'Publisher contains expected publisher export percent.');
    $this->assertTrue(strip_tags((string) $build[0]['clients_table'][3]['percent_imported']['#markup']) === 'Not Available',
      'Publisher correctly contains no imported data.');
    $this->assertTrue(strip_tags((string) $build[0]['clients_table'][3]['updated']['#markup']) === $last_updated_time,
      'Publisher updated date is correct.');
    $this->assertTrue(strpos((string) $build[0]['clients_table'][3]['details']['#markup'], '00000000-9987-4b2a-74b9-d758c8b60d12') !== FALSE,
      'Publisher contains correct uuid in more details link.');

    // Test response as subscriber only.
    $this->assertTrue(strip_tags((string) $build[0]['clients_table'][2]['name']['#markup']) === 'sub',
      'Subscriber contains correct name.');
    $this->assertTrue(strip_tags((string) $build[0]['clients_table'][2]['type']['#markup']) === 'Subscriber',
      'Subscriber contains expected type.');
    $this->assertTrue(strip_tags((string) $build[0]['clients_table'][2]['uuid']['#markup']) === 'http://subonline.example.com',
      'Subscriber webhook domain is correct.');
    $this->assertTrue(strip_tags((string) $build[0]['clients_table'][2]['status']['#markup']) === 'In Progress',
      'Subscriber status is correct.');
    $this->assertTrue(strip_tags((string) $build[0]['clients_table'][2]['percent_exported']['#markup']) === 'Not Available',
      'Subscriber contains expected publisher export percent.');
    $this->assertTrue(strip_tags((string) $build[0]['clients_table'][2]['percent_imported']['#markup']) === '60%',
      'Subscriber correctly contains no imported data.');
    $this->assertTrue(strip_tags((string) $build[0]['clients_table'][2]['updated']['#markup']) === $last_updated_time,
      'Subscriber updated date is correct.');
    $this->assertTrue(strpos((string) $build[0]['clients_table'][2]['details']['#markup'], '00000000-42bf-4860-6d03-4e3411ee32b4') !== FALSE,
      'Subscriber contains correct uuid in more details link.');

    // Test response as both publisher and subscriber.
    $this->assertTrue(strip_tags((string) $build[0]['clients_table'][1]['name']['#markup']) === 'pubsub',
      'PubSub contains correct name.');
    $this->assertTrue(strip_tags((string) $build[0]['clients_table'][1]['type']['#markup']) === 'Publisher, Subscriber',
      'PubSub contains expected type.');
    $this->assertTrue(strip_tags((string) $build[0]['clients_table'][1]['uuid']['#markup']) === 'http://pubsubonline.example.com',
      'PubSub webhook domain is correct.');
    $this->assertTrue(strip_tags((string) $build[0]['clients_table'][1]['status']['#markup']) === 'In Progress',
      'PubSub status is correct.');
    $this->assertTrue(strip_tags((string) $build[0]['clients_table'][1]['percent_exported']['#markup']) === '100%',
      'PubSub contains expected publisher export percent.');
    $this->assertTrue(strip_tags((string) $build[0]['clients_table'][1]['percent_imported']['#markup']) === '92%',
      'PubSub correctly contains no imported data.');
    $this->assertTrue(strip_tags((string) $build[0]['clients_table'][1]['updated']['#markup']) === $last_updated_time,
      'PubSub updated date is correct.');
    $this->assertTrue(strpos((string) $build[0]['clients_table'][1]['details']['#markup'], '00000000-12bc-442f-46f5-d2694d553429') !== FALSE,
      'PubSub contains correct uuid in more details link.');

    // Test response without data metrics.
    $this->assertTrue(strip_tags((string) $build[0]['clients_table'][0]['name']['#markup']) === 'localpubsub',
      'Local client contains correct name.');
    $this->assertTrue(strip_tags((string) $build[0]['clients_table'][0]['type']['#markup']) === 'Publisher, Subscriber',
      'Local client contains expected type.');
    $this->assertTrue(strip_tags((string) $build[0]['clients_table'][0]['uuid']['#markup']) === 'http://pubsub.example.com',
      'Local client webhook domain is correct.');
    $this->assertTrue(strip_tags((string) $build[0]['clients_table'][0]['status']['#markup']) === 'Not Available',
      'Local client status is correct.');
    $this->assertTrue(strip_tags((string) $build[0]['clients_table'][0]['percent_exported']['#markup']) === 'Not Available',
      'Local client contains expected publisher export percent.');
    $this->assertTrue(strip_tags((string) $build[0]['clients_table'][0]['percent_imported']['#markup']) === 'Not Available',
      'Local client correctly contains no imported data.');
    $this->assertTrue(strip_tags((string) $build[0]['clients_table'][0]['updated']['#markup']) === 'Not Available',
      'Local client updated date is correct.');
    $this->assertTrue(strpos((string) $build[0]['clients_table'][0]['details']['#markup'], '00000000-00ab-489f-52fa-404bdf8df699') !== FALSE,
      'Local client contains correct uuid in more details link.');

  }

  /**
   * Strips html tags and converts to string.
   *
   * @param mixed $element
   *   Value to convert.
   *
   * @return string
   *   String value without html tags.
   */
  protected function getMarkupValue($element) : string {
    return strip_tags((string) $element);
  }

  /**
   * Creates cloned prophetic client.
   *
   * @return \Prophecy\Prophecy\ObjectProphecy
   *   Clones client so data isn't changed.
   */
  private function getClient() {
    return clone $this->client;
  }

}
