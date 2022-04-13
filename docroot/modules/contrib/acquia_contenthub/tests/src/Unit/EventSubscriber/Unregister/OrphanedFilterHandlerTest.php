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
    $this->client
      ->getFilter(Argument::any())
      ->willReturn($this->getFilter('default_filter_client'));

    $data = $this->getWebhooks(['filters' => ['default_filter_client']]);
    $event = $this->getUnregisterEvent($data);

    $this->assertEquals($data[0]->getClientName(), $event->getClientName());
    $this->assertEquals(TRUE, $event->isDeleteWebhookOnly());
    $this->assertEquals('default_filter_client', $event->getDefaultFilter());
    $this->assertEmpty($event->getOrphanedFilters());
  }

  /**
   * @covers ::onDeleteWebhook
   *
   * @throws \Exception
   */
  public function testDeleteWebhookWithOrphanFilter(): void {
    $this->client
      ->getFilter(Argument::any())
      ->willReturn($this->getFilter('filter_client'));

    $data = $this->getWebhooks(['filters' => ['filter_client']]);
    $event = $this->getUnregisterEvent($data);

    $this->assertEquals($data[0]->getClientName(), $event->getClientName());
    $this->assertEquals(TRUE, $event->isDeleteWebhookOnly());
    $this->assertTrue($event->isPropagationStopped());
    $expected_orphan_filter['filter_client'] = $data[0]->getFilters()[0];
    $this->assertEqualsCanonicalizing($expected_orphan_filter, $event->getOrphanedFilters());
  }

  /**
   * @covers ::onDeleteWebhook
   *
   * @throws \Exception
   */
  public function testDeleteWebhookWithNullFilter(): void {
    $data = $this->getWebhooks(['filters' => NULL]);
    $event = $this->getUnregisterEvent($data);

    $this->assertEquals($data[0]->getClientName(), $event->getClientName());
    $this->assertEquals(TRUE, $event->isDeleteWebhookOnly());
    $this->assertTrue($event->isPropagationStopped());
    $this->assertEmpty($event->getOrphanedFilters());
    $this->assertEmpty($event->getDefaultFilter());
  }

  /**
   * @covers ::onDeleteWebhook
   *
   * @throws \Exception
   */
  public function testDeleteWebhookWithEmptyFilter(): void {
    $data = $this->getWebhooks(['filters' => []]);
    $event = $this->getUnregisterEvent($data);

    $this->assertEquals($data[0]->getClientName(), $event->getClientName());
    $this->assertEquals(TRUE, $event->isDeleteWebhookOnly());
    $this->assertTrue($event->isPropagationStopped());
    $this->assertEmpty($event->getOrphanedFilters());
    $this->assertEmpty($event->getDefaultFilter());
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

  /**
   * Returns an arbitrary set of webhooks.
   *
   * @todo Refactor into a mock generator service and generalize the method.
   *
   * @param array $definition_overrides
   *   Webhook attribute overrides.
   *
   * @return \Acquia\ContentHubClient\Webhook[]
   *   Mock webhook response.
   */
  protected function getWebhooks(array $definition_overrides = []): array {
    $definition = [
      'uuid' => '4e68da2e-a729-4c81-9c16-e4f8c05a11be',
      'client_uuid' => 'valid_client_uuid',
      'client_name' => 'client',
      'url' => 'https://example.com/acquia-contenthub/webhook',
      'version' => 2,
      'disable_retries' => 'false',
      'filters' => ['5e68da2e-a729-4c81-9c16-e4f8c05a11be'],
      'status' => 'ENABLED',
      'is_migrated' => FALSE,
      'suppressed_until' => 0,
    ];
    if (!empty($definition_overrides)) {
      $definition = array_merge($definition, $definition_overrides);
    }

    return [
      new Webhook($definition),
    ];
  }

  /**
   * Event dispatched on webhook deletion.
   *
   * @param array $data
   *   Mock webhook response.
   *
   * @return \Drupal\acquia_contenthub\Event\AcquiaContentHubUnregisterEvent
   *   Event dispatched on webhook deletion.
   *
   * @throws \Exception
   */
  protected function getUnregisterEvent(array $data): AcquiaContentHubUnregisterEvent {
    $this->client
      ->getWebHooks()
      ->willReturn($data);

    $event = new AcquiaContentHubUnregisterEvent('4e68da2e-a729-4c81-9c16-e4f8c05a11be', '4e68da2e-a729-4c81-9c16-e4f8c05a11be', TRUE);
    $this->orphanedFilterHandler->onDeleteWebhook($event);

    return $event;
  }

}
