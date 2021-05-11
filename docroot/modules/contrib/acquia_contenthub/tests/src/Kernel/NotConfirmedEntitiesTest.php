<?php

namespace Drupal\Tests\acquia_contenthub\Kernel;

use Drupal\acquia_contenthub_publisher\ContentHubPublisherEvents;
use Drupal\Component\Datetime\Time;
use Drupal\Component\EventDispatcher\ContainerAwareEventDispatcher;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Database\Database;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Prophecy\Argument;

/**
 * Tests entities that are not confirmed.
 *
 * @group orca_ignore
 *
 * @package Drupal\Tests\acquia_contenthub\Kernel
 */
class NotConfirmedEntitiesTest extends EntityKernelTestBase {

  public static $modules = [
    'depcalc',
    'acquia_contenthub',
    'acquia_contenthub_publisher',
  ];

  /**
   * The Config Factory service mock.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy|\Drupal\Core\Config\ConfigFactory
   */
  protected $configFactory;

  /**
   * The DateTime service mock.
   *
   * @var \Drupal\Component\Datetime\Time
   */
  protected $dateTimeTime;

  /**
   * The Immutable Config service mock.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy|\Drupal\Core\Config\ImmutableConfig
   */
  protected $immutableConfig;

  /**
   * The Event Dispatcher service mock.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy|\Drupal\Component\EventDispatcher\ContainerAwareEventDispatcher
   */
  protected $eventDispatcher;

  /**
   * The list of actual items (passed with the event).
   *
   * @var array
   */
  protected $actualItems = [];

  /**
   * {@inheritdoc}
   *
   * @throws \Exception
   */
  protected function setUp() {
    parent::setUp();

    $this->installSchema('acquia_contenthub_publisher', ['acquia_contenthub_publisher_export_tracking']);

    $this->immutableConfig = $this->prophesize(ImmutableConfig::class);
    $this->configFactory = $this->prophesize(ConfigFactory::class);
    $this->dateTimeTime = $this->prophesize(Time::class);

    $actual_items = & $this->actualItems;
    $this->eventDispatcher = $this->prophesize(ContainerAwareEventDispatcher::class);
    $this->eventDispatcher
      ->dispatch(
        ContentHubPublisherEvents::NOT_CONFIRMED_ENTITIES_FOUND,
        Argument::any())->will(
          function ($args) use (&$actual_items) {
            $actual_items = array_keys($args[1]->getItems());
          }
        );
    $this->container->set('event_dispatcher', $this->eventDispatcher->reveal());

    $query = Database::getConnection()->insert('acquia_contenthub_publisher_export_tracking')
      ->fields([
        'entity_uuid',
        'entity_type',
        'entity_id',
        'status',
        'modified',
      ]);
    foreach ($this->getTestDataset() as $record) {
      $query->values($record);
    }
    $query->execute();
  }

  /**
   * Sets the threshold value.
   *
   * @param int|string $threshold
   *   A threshold value in seconds or empty string if no threshold (disabled).
   */
  protected function setThreshold($threshold) {
    $this->immutableConfig = $this->prophesize(ImmutableConfig::class);
    $this->immutableConfig->get('threshold_stale_entities')->willReturn($threshold);
    $this->configFactory = $this->prophesize(ConfigFactory::class);
    $this->configFactory->get('acquia_contenthub_publisher.settings')->willReturn($this->immutableConfig->reveal());
    $this->container->set('config.factory', $this->configFactory->reveal());
  }

  /**
   * Sets the request time value.
   *
   * @param int $time
   *   Timestamp.
   */
  protected function setRequestTime($time) {
    $this->dateTimeTime->getRequestTime()->willReturn($time);
    $this->container->set('datetime.time', $this->dateTimeTime->reveal());
  }

  /**
   * Tests "stale" entities functionality.
   *
   * @param int|string $threshold
   *   The threshold.
   * @param int $request_time
   *   The request time.
   * @param array $expected_items
   *   The list of expected items.
   *
   * @dataProvider notConfirmedEntitiesDataProvider
   */
  public function testNotConfirmedEntities($threshold, $request_time, array $expected_items) {
    $this->setThreshold($threshold);
    $this->setRequestTime($request_time);

    _acquia_contenthub_dispatch_not_confirmed_entities_event();

    $this->assertEquals($expected_items, $this->actualItems);
  }

  /**
   * The data provider for testNotConfirmedEntities.
   *
   * @see testNotConfirmedEntities
   *
   * @return array
   *   A set of "threshold"/"request time"/"expected items" test data.
   */
  public function notConfirmedEntitiesDataProvider() {
    return [
      [
        '',
        1558428300,
        [],
      ],
      [
        200000,
        1558428300,
        [],
      ],
      [
        1800,
        1558428300,
        [
          '2839ff16-7a1d-4514-a965-61522bda81e3',
          '8b1057f6-180e-4bec-b896-68aff120e5ec',
          '411bf681-9d0b-4ea3-9ad0-6aa62d039e6d',
          '9918e112-f3d8-401f-8804-a47738778060',
          '910938cc-81ac-4d61-ac86-c1c6cc6bc195',
          '787072d3-c508-43b3-9e3e-99b9c4946f76',
          '08dd5545-28ac-4998-830d-24b35c19cde3',
          '0ff90c9f-31f7-405d-9395-99a5a43bc572',
        ],
      ],
      [
        3600,
        1558428300,
        [
          '2839ff16-7a1d-4514-a965-61522bda81e3',
          '8b1057f6-180e-4bec-b896-68aff120e5ec',
          '411bf681-9d0b-4ea3-9ad0-6aa62d039e6d',
          '9918e112-f3d8-401f-8804-a47738778060',
          '910938cc-81ac-4d61-ac86-c1c6cc6bc195',
          '787072d3-c508-43b3-9e3e-99b9c4946f76',
          '08dd5545-28ac-4998-830d-24b35c19cde3',
        ],
      ],
      [
        7200,
        1558428300,
        [
          '2839ff16-7a1d-4514-a965-61522bda81e3',
          '8b1057f6-180e-4bec-b896-68aff120e5ec',
          '411bf681-9d0b-4ea3-9ad0-6aa62d039e6d',
          '9918e112-f3d8-401f-8804-a47738778060',
          '910938cc-81ac-4d61-ac86-c1c6cc6bc195',
          '787072d3-c508-43b3-9e3e-99b9c4946f76',
        ],
      ],
      [
        10800,
        1558428300,
        [
          '2839ff16-7a1d-4514-a965-61522bda81e3',
          '8b1057f6-180e-4bec-b896-68aff120e5ec',
          '411bf681-9d0b-4ea3-9ad0-6aa62d039e6d',
          '9918e112-f3d8-401f-8804-a47738778060',
          '910938cc-81ac-4d61-ac86-c1c6cc6bc195',
        ],
      ],
      [
        21600,
        1558428300,
        [
          '2839ff16-7a1d-4514-a965-61522bda81e3',
          '8b1057f6-180e-4bec-b896-68aff120e5ec',
          '411bf681-9d0b-4ea3-9ad0-6aa62d039e6d',
          '9918e112-f3d8-401f-8804-a47738778060',
        ],
      ],
      [
        43200,
        1558428300,
        [
          '2839ff16-7a1d-4514-a965-61522bda81e3',
          '8b1057f6-180e-4bec-b896-68aff120e5ec',
          '411bf681-9d0b-4ea3-9ad0-6aa62d039e6d',
        ],
      ],
      [
        86400,
        1558428300,
        [
          '2839ff16-7a1d-4514-a965-61522bda81e3',
          '8b1057f6-180e-4bec-b896-68aff120e5ec',
        ],
      ],
      [
        172800,
        1558428300,
        [
          '2839ff16-7a1d-4514-a965-61522bda81e3',
        ],
      ],
    ];
  }

  /**
   * Returns the test data to fill the database table in.
   *
   * @return array
   *   Test data as a provider.
   */
  protected function getTestDataset() {
    return [
      [
        '0ff90c9f-31f7-405d-9395-99a5a43bc572',
        'node',
        1,
        'exported',
        '2019-05-21T08:14:59+00:00',
      ],
      [
        '08dd5545-28ac-4998-830d-24b35c19cde3',
        'node',
        2,
        'exported',
        '2019-05-21T07:44:59+00:00',
      ],
      [
        'b1f7fb8f-2671-4119-9114-ef2c5dfb40f5',
        'node',
        3,
        'confirmed',
        '2019-05-17T10:05:00+00:00',
      ],
      [
        'ccea7a36-f3fb-4d46-9705-8b67aaac1730',
        'node',
        4,
        'confirmed',
        '2019-05-17T09:05:00+00:00',
      ],
      [
        '787072d3-c508-43b3-9e3e-99b9c4946f76',
        'node',
        5,
        'exported',
        '2019-05-21T06:44:59+00:00',
      ],
      [
        '910938cc-81ac-4d61-ac86-c1c6cc6bc195',
        'node',
        15,
        'exported',
        '2019-05-21T05:44:59+00:00',
      ],
      [
        '9918e112-f3d8-401f-8804-a47738778060',
        'taxonomy_term',
        999,
        'exported',
        '2019-05-21T02:44:59+00:00',
      ],
      [
        '411bf681-9d0b-4ea3-9ad0-6aa62d039e6d',
        'node',
        16,
        'exported',
        '2019-05-20T20:44:59+00:00',
      ],
      [
        '8b1057f6-180e-4bec-b896-68aff120e5ec',
        'user',
        1,
        'exported',
        '2019-05-20T08:44:59+00:00',
      ],
      [
        '2839ff16-7a1d-4514-a965-61522bda81e3',
        'user',
        2,
        'exported',
        '2019-05-19T08:44:59+00:00',
      ],
      [
        '89f45d6d-84aa-4d5a-a9e4-70d184136d39',
        'user',
        3,
        'exported',
        '2019-05-21T08:45:00+00:00',
      ],
      [
        '64cf7b00-5832-42ca-ab43-c7faa82cf7f8',
        'user',
        4,
        'foo status',
        '2019-05-17T11:04:20+00:00',
      ],
      [
        'c030f85d-4e3b-4f8e-b75d-cb8afdfe329e',
        'user',
        5,
        'confirmed',
        '2019-05-17T07:35:11+00:00',
      ],
      [
        '7065e976-30c6-496a-b161-72b2c0050935',
        'user',
        6,
        'confirmed',
        '2019-05-17T01:25:13+00:00',
      ],
    ];
  }

}
