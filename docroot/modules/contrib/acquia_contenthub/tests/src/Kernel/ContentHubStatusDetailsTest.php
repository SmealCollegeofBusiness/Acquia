<?php

namespace Drupal\Tests\acquia_contenthub\Kernel;

use Acquia\ContentHubClient\ContentHubClient;
use Acquia\ContentHubClient\Settings;
use Drupal\acquia_contenthub\Client\ClientFactory;
use Drupal\acquia_contenthub_publisher\Controller\StatusReportDetailsController;
use Drupal\acquia_contenthub_test\MockDataProvider;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;

/**
 * Tests the Content Hub settings form.
 *
 * @group acquia_contenthub
 */
class ContentHubStatusDetailsTest extends EntityKernelTestBase {

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
    'datetime',
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
    $this->installEntitySchema('date_format');
    $this->installSchema('acquia_contenthub_publisher', ['acquia_contenthub_publisher_export_tracking']);
    $this->entityTypeManager->getStorage('date_format')->create([
      'langcode' => 'en',
      'status' => TRUE,
      'dependencies' => [],
      'id' => 'long',
      'label' => 'Default long date',
      'locked' => FALSE,
      'pattern' => 'l, F j, Y - H:i',
    ])->save();
  }

  /**
   * Tests status report details build.
   *
   * @param string $client_uuid
   *   UUID of the client passed to controller.
   * @param string $webhook_uuid
   *   Webhook uuid that corresponds to client, see MockDataProvider.
   * @param array $details
   *   Details that display on details controller.
   * @param array $publisher_status
   *   Attached drupalSettings for publisher statuses.
   * @param array $publisher_color
   *   Attached drupalSettings for publisher colors.
   * @param array $subscriber_status
   *   Attached drupalSettings for subscriber statuses.
   * @param array $subscriber_color
   *   Attached drupalSettings for subscriber colors.
   *
   * @dataProvider providerTestContentHubStatusReportDetails
   *
   * @throws \Exception
   */
  public function testContentHubStatusReportDetails($client_uuid, $webhook_uuid, array $details, array $publisher_status, array $publisher_color, array $subscriber_status, array $subscriber_color) {
    $settings = $this->prophesize(Settings::class);
    $settings->getUuid()->willReturn('3a89ff1b-8869-419d-b931-f2282aca3e88');
    $settings->getName()->willReturn('foo');
    $settings->getUrl()->willReturn('http://www.example.com');
    $settings->getApiKey()->willReturn('apikey');
    $settings->getSecretKey()->willReturn('apisecret');

    $client = $this->prophesize(ContentHubClient::class);
    $clientFactory = $this->prophesize(ClientFactory::class);
    $clientFactory->getSettings()->willReturn($settings->reveal());
    $client->getSettings()->willReturn($settings->reveal());

    $client->getEntity($client_uuid)
      ->willReturn(MockDataProvider::getClient($client_uuid));
    $client->getInterestsByWebhook($webhook_uuid)->willReturn([$client_uuid]);
    $clientFactory->getClient()->willReturn($client->reveal());
    $controller = new StatusReportDetailsController($clientFactory->reveal());
    $build = $controller->getWebhookDetails($client_uuid);

    // Check if graph ids are set.
    if (!empty($build['single_details_page']['publisher_graph'])) {
      $this->assertTrue($this->getMarkupValue($build['single_details_page']['publisher_graph']['#attributes']['id']) === 'publisher_chart',
        'Publisher chart id is correct.');
    }
    if (!empty($build['single_details_page']['subscriber_graph'])) {
      $this->assertTrue($this->getMarkupValue($build['single_details_page']['subscriber_graph']['#attributes']['id']) === 'subscriber_chart',
        'Subscriber chart id is correct.');
    }

    // Check the important data details.
    foreach ($details as $key => $value) {
      $this->assertTrue($this->getMarkupValue($build['single_details_page']['details'][$key]['#value']) === $value);
    }
    foreach ($publisher_status as $status => $number) {
      $this->assertTrue($this->getMarkupValue($build['#attached']['drupalSettings']['acquia_contenthub_publisher_status'][$status]) === $number);
    }
    foreach ($publisher_color as $status => $color) {
      $this->assertTrue($this->getMarkupValue($build['#attached']['drupalSettings']['acquia_contenthub_publisher_color'][$status]) === $color);
    }
    foreach ($subscriber_status as $status => $number) {
      $this->assertTrue($this->getMarkupValue($build['#attached']['drupalSettings']['acquia_contenthub_subscriber_status'][$status]) === $number);
    }
    foreach ($subscriber_color as $status => $color) {
      $this->assertTrue($this->getMarkupValue($build['#attached']['drupalSettings']['acquia_contenthub_subscriber_color'][$status]) === $color);
    }
  }

  /**
   * Provider for testContentHubStatusReportDetails()
   *
   * @return array
   *   Array of details data to check against.
   */
  public function providerTestContentHubStatusReportDetails() {
    $data = [];

    // Local client test with minimal data.
    $data['00000000-00ab-489f-52fa-404bdf8df699']['client_uuid'] = '00000000-00ab-489f-52fa-404bdf8df699';
    $data['00000000-00ab-489f-52fa-404bdf8df699']['webhook_uuid'] = '00000000-55aa-42f5-50d1-2e35b72ae26d';
    $data['00000000-00ab-489f-52fa-404bdf8df699']['details'] = [
      'webhook_url' => 'Domainhttp://pubsub.example.com',
      'webhook_uuid' => 'UUID00000000-55aa-42f5-50d1-2e35b72ae26d',
    ];
    $data['00000000-00ab-489f-52fa-404bdf8df699']['publisher_status'] = [];
    $data['00000000-00ab-489f-52fa-404bdf8df699']['publisher_color'] = [];
    $data['00000000-00ab-489f-52fa-404bdf8df699']['subscriber_status'] = [];
    $data['00000000-00ab-489f-52fa-404bdf8df699']['subscriber_color'] = [];

    // Publisher and Subscriber test with data for both.
    $data['00000000-12bc-442f-46f5-d2694d553429']['client_uuid'] = '00000000-12bc-442f-46f5-d2694d553429';
    $data['00000000-12bc-442f-46f5-d2694d553429']['webhook_uuid'] = '00000000-4b0b-4c99-5b1d-0177597c2ca7';
    $data['00000000-12bc-442f-46f5-d2694d553429']['details'] = [
      'webhook_url' => 'Domainhttp://pubsubonline.example.com',
      'webhook_uuid' => 'UUID00000000-4b0b-4c99-5b1d-0177597c2ca7',
      'publisher_details' => 'Publisher Status3 confirmed out of 3 entities',
      'publisher_updated' => 'Last UpdatedFriday, November 1, 2013 - 01:13',
      'subscriber_details' => 'Subscriber Status57 imported, 5 queued out of 62 entities',
      'subscriber_updated' => 'Last UpdatedSunday, January 1, 2017 - 11:00',
    ];
    $data['00000000-12bc-442f-46f5-d2694d553429']['publisher_status'] = [
      'confirmed' => '3',
      'total' => '3',
    ];
    $data['00000000-12bc-442f-46f5-d2694d553429']['publisher_color'] = [
      'confirmed' => '#7CD7F2',
    ];
    $data['00000000-12bc-442f-46f5-d2694d553429']['subscriber_status'] = [
      'imported' => '57',
      'queued' => '5',
      'total' => '62',
    ];
    $data['00000000-12bc-442f-46f5-d2694d553429']['subscriber_color'] = [
      'imported' => '#33D1FF',
      'queued' => '#555555',
    ];

    // Subscriber test with data.
    $data['00000000-42bf-4860-6d03-4e3411ee32b4']['client_uuid'] = '00000000-42bf-4860-6d03-4e3411ee32b4';
    $data['00000000-42bf-4860-6d03-4e3411ee32b4']['webhook_uuid'] = '00000000-72b6-4df8-710b-59790112588e';
    $data['00000000-42bf-4860-6d03-4e3411ee32b4']['details'] = [
      'webhook_url' => 'Domainhttp://subonline.example.com',
      'webhook_uuid' => 'UUID00000000-72b6-4df8-710b-59790112588e',
      'subscriber_details' => 'Subscriber Status34 imported, 23 queued out of 57 entities',
      'subscriber_updated' => 'Last UpdatedSunday, January 1, 2017 - 11:00',
    ];
    $data['00000000-42bf-4860-6d03-4e3411ee32b4']['publisher_status'] = [];
    $data['00000000-42bf-4860-6d03-4e3411ee32b4']['publisher_color'] = [];
    $data['00000000-42bf-4860-6d03-4e3411ee32b4']['subscriber_status'] = [
      'imported' => '34',
      'queued' => '23',
      'total' => '57',
    ];
    $data['00000000-42bf-4860-6d03-4e3411ee32b4']['subscriber_color'] = [
      'imported' => '#33D1FF',
      'queued' => '#555555',
    ];

    // Publisher test with data.
    $data['00000000-9987-4b2a-74b9-d758c8b60d12']['client_uuid'] = '00000000-9987-4b2a-74b9-d758c8b60d12';
    $data['00000000-9987-4b2a-74b9-d758c8b60d12']['webhook_uuid'] = '00000000-5ac3-4e9f-7fe9-776b56a389c0';
    $data['00000000-9987-4b2a-74b9-d758c8b60d12']['details'] = [
      'webhook_url' => 'Domainhttp://pubonline.example.com',
      'webhook_uuid' => 'UUID00000000-5ac3-4e9f-7fe9-776b56a389c0',
      'publisher_details' => 'Publisher Status30 confirmed, 5 exported out of 35 entities',
      'publisher_updated' => 'Last UpdatedSunday, January 1, 2017 - 11:00',
    ];
    $data['00000000-9987-4b2a-74b9-d758c8b60d12']['publisher_status'] = [
      'confirmed' => '30',
      'exported' => '5',
      'total' => '35',
    ];
    $data['00000000-9987-4b2a-74b9-d758c8b60d12']['publisher_color'] = [
      'confirmed' => '#7CD7F2',
      'exported' => '#29A8E1',
    ];
    $data['00000000-9987-4b2a-74b9-d758c8b60d12']['subscriber_status'] = [];
    $data['00000000-9987-4b2a-74b9-d758c8b60d12']['subscriber_color'] = [];

    return $data;
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

}
