<?php

namespace Drupal\Tests\acquia_contenthub\Kernel;

use Acquia\ContentHubClient\CDF\CDFObject;
use Acquia\ContentHubClient\ContentHubClient;
use Acquia\ContentHubClient\Settings;
use Drupal\acquia_contenthub\Client\ClientFactory;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Prophecy\Argument;
use Psr\Http\Message\ResponseInterface;

/**
 * Tests deletion of entities.
 *
 * @group acquia_contenthub
 *
 * @package Drupal\Tests\acquia_contenthub\Kernel
 */
class DeleteEntityTest extends EntityKernelTestBase {

  public static $modules = [
    'user',
    'system',
    'field',
    'text',
    'filter',
    'depcalc',
    'acquia_contenthub',
    'acquia_contenthub_publisher',
  ];

  /**
   * The user object.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $user;

  /**
   * The AcquiaContentHub Client Mock.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy
   */
  protected $settings;

  /**
   * The ContentHubClient Mock.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy
   */
  protected $client;

  /**
   * The client factory mock.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy
   */
  protected $factory;

  /**
   * The publisher tracker.
   *
   * @var \Drupal\acquia_contenthub_publisher\PublisherTracker
   */
  protected $tracker;

  /**
   * {@inheritdoc}
   *
   * @throws \Exception
   */
  protected function setUp() {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installSchema('user', 'users_data');
    $this->installSchema('acquia_contenthub_publisher', 'acquia_contenthub_publisher_export_tracking');

    $origin_uuid = '00000000-0000-0001-0000-123456789123';

    $cdfObject = new CDFObject('drupal8_content_entity', '00000000-0000-0001-0000-123456789124', 'foo', 'bar', $origin_uuid);

    $this->settings = $this->prophesize(Settings::class);
    $this->settings->getUuid()->willReturn($origin_uuid);
    $this->settings->getWebhook('uuid')->willReturn($origin_uuid);

    $deleteEntityResponse = $this->prophesize(ResponseInterface::class);
    $deleteEntityResponse->getStatusCode()->willReturn(202);

    $deleteInterestResponse = $this->prophesize(ResponseInterface::class);
    $deleteInterestResponse->getStatusCode()->willReturn(200);

    $this->client = $this->prophesize(ContentHubClient::class);
    $this->client->getEntity(Argument::any())->willReturn($cdfObject);
    $this->client->getSettings()->willReturn($this->settings->reveal());
    $this->client->deleteEntity(Argument::any())->willReturn($deleteEntityResponse->reveal());
    $this->client->deleteInterest(Argument::any(), $origin_uuid)->willReturn($deleteInterestResponse->reveal());

    $this->factory = $this->prophesize(ClientFactory::class);
    $this->factory->isConfigurationSet()->willReturn(TRUE);
    $this->factory->getClient()->willReturn($this->client->reveal());
    $this->container->set('acquia_contenthub.client.factory', $this->factory->reveal());

    $this->tracker = $this->container->get('acquia_contenthub_publisher.tracker');
    $this->user = $this->createUser();
  }

  /**
   * Tests the expected flow of a full entity delete process.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testEntityDelete() {
    $this->assertNotNull($this->tracker->get($this->user->uuid()));
    $this->user->delete();
    // Assert user entity has been deleted implicitly.
    $this->assertFalse($this->tracker->get($this->user->uuid()));
    $this->client->getEntity($this->user->uuid())->shouldBeCalled();
    $this->client->deleteEntity($this->user->uuid())->shouldBeCalled();
    $this->client->deleteInterest($this->user->uuid(), $this->container->get('acquia_contenthub.client.factory')->getClient()->getSettings()->getWebhook('uuid'))->shouldBeCalled();
  }

}
