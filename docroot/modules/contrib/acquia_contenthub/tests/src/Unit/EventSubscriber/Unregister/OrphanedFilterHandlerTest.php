<?php

namespace Drupal\Tests\acquia_contenthub\Unit\EventSubscriber\Unregister;

use Acquia\ContentHubClient\ContentHubClient;
use Acquia\ContentHubClient\Webhook;
use Drupal\acquia_contenthub\Client\ClientFactory;
use Drupal\acquia_contenthub\Event\AcquiaContentHubUnregisterEvent;
use Drupal\acquia_contenthub\EventSubscriber\Unregister\OrphanedFilterHandler;
use Drupal\Tests\UnitTestCase;
use Prophecy\Argument;

/**
 * Tests orphaned filter handler.
 *
 * @group acquia_contenthub
 * @coversDefaultClass \Drupal\acquia_contenthub\EventSubscriber\Unregister\OrphanedFilterHandler
 *
 * @package Drupal\Tests\acquia_contenthub\Unit\EventSubscriber\Unregister
 */
class OrphanedFilterHandlerTest extends UnitTestCase {

  /**
   * The OrphanedFilterHandler object.
   *
   * @var \Drupal\acquia_contenthub\EventSubscriber\Unregister\OrphanedFilterHandler
   */
  protected $orphanedFilterHandler;

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
    $this->orphanedFilterHandler = new OrphanedFilterHandler($factory->reveal());
  }

  /**
   * @covers ::onDeleteWebhook
   *
   * @throws \Exception
   */
  public function testDeleteWebhookWithoutOrphanFilter(): void {
    $data = [
      new Webhook([
        'uuid' => '4e68da2e-a729-4c81-9c16-e4f8c05a11be',
        'client_uuid' => 'valid_client_uuid',
        'client_name' => 'client',
        'url' => 'https://example.com/acquia-contenthub/webhook',
        'version' => 2,
        'disable_retries' => 'false',
        'filters' => [
          'default_filter_client',
        ],
        'status' => 'ENABLED',
        'is_migrated' => FALSE,
        'suppressed_until' => 0,
      ]),
    ];

    $this->client
      ->getWebHooks()
      ->willReturn($data);

    $this->client
      ->getFilter(Argument::any())
      ->willReturn($this->getFilter('default_filter_client'));

    $event = new AcquiaContentHubUnregisterEvent('4e68da2e-a729-4c81-9c16-e4f8c05a11be', '4e68da2e-a729-4c81-9c16-e4f8c05a11be', FALSE);
    $this->orphanedFilterHandler->onDeleteWebhook($event);

    $this->assertEquals($data[0]->getClientName(), $event->getClientName());
    $this->assertEquals(FALSE, $event->isDeleteWebhookOnly());

    $this->assertEquals('default_filter_client', $event->getDefaultFilter());
    $this->assertEmpty($event->getOrphanedFilters());
  }

  /**
   * @covers ::onDeleteWebhook
   *
   * @throws \Exception
   */
  public function testDeleteWebhookWithOrphanFilter(): void {
    $data = [
      new Webhook([
        'uuid' => '4e68da2e-a729-4c81-9c16-e4f8c05a11be',
        'client_uuid' => 'valid_client_uuid',
        'client_name' => 'client',
        'url' => 'https://example.com/acquia-contenthub/webhook',
        'version' => 2,
        'disable_retries' => 'false',
        'filters' => [
          'filter_client',
        ],
        'status' => 'ENABLED',
        'is_migrated' => FALSE,
        'suppressed_until' => 0,
      ]),
    ];

    $this->client
      ->getWebHooks()
      ->willReturn($data);

    $this->client
      ->getFilter(Argument::any())
      ->willReturn($this->getFilter('filter_client'));

    $event = new AcquiaContentHubUnregisterEvent('4e68da2e-a729-4c81-9c16-e4f8c05a11be', '4e68da2e-a729-4c81-9c16-e4f8c05a11be', TRUE);
    $this->orphanedFilterHandler->onDeleteWebhook($event);

    $this->assertEquals($data[0]->getClientName(), $event->getClientName());
    $this->assertEquals(TRUE, $event->isDeleteWebhookOnly());
    $this->assertTrue($event->isPropagationStopped());
    $expected_orphan_filter['filter_client'] = $data[0]->getFilters()[0];
    $this->assertEqualsCanonicalizing($expected_orphan_filter, $event->getOrphanedFilters());
  }

  /**
   * Fetches filters.
   *
   * @param string $default_filter
   *   Filter name.
   *
   * @return array
   *   Filter data.
   */
  protected function getFilter(string $default_filter): array {
    return [
      'data' => [
        'name' => $default_filter,
      ],
    ];
  }

}
