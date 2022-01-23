<?php

namespace Drupal\acquia_contenthub\EventSubscriber\Unregister;

use Drupal\acquia_contenthub\AcquiaContentHubEvents;
use Drupal\acquia_contenthub\Client\ClientFactory;
use Drupal\acquia_contenthub\Event\AcquiaContentHubUnregisterEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Discovers filters which belongs to given webhook.
 *
 * @package Drupal\acquia_contenthub\EventSubscriber\Unregister
 */
class OrphanedFilterHandler implements EventSubscriberInterface {

  /**
   * The client factory.
   *
   * @var \Drupal\acquia_contenthub\Client\ClientFactory
   */
  protected $clientFactory;

  /**
   * OrphanedFilterHandler constructor.
   *
   * @param \Drupal\acquia_contenthub\Client\ClientFactory $factory
   *   ACH Client Factory.
   */
  public function __construct(ClientFactory $factory) {
    $this->clientFactory = $factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[AcquiaContentHubEvents::ACH_UNREGISTER][] = ['onDeleteWebhook', 100];
    return $events;
  }

  /**
   * Gathers information about orphaned filters.
   *
   * @param \Drupal\acquia_contenthub\Event\AcquiaContentHubUnregisterEvent $event
   *   The event being dispatched.
   *
   * @throws \Exception
   */
  public function onDeleteWebhook(AcquiaContentHubUnregisterEvent $event): void {

    $client = $this->clientFactory->getClient();
    $webhooks = $client->getWebHooks();

    $deleted_webhook_filters = [];
    $other_webhook_filters = [];
    $client_name = '';

    foreach ($webhooks as $webhook) {
      if ($webhook->getUuid() === $event->getWebhookUuid()) {
        // Filters belong to webhook which will be deleted.
        $deleted_webhook_filters = $webhook->getFilters() ?? [];
        // Client name which belongs to webhook which will be deleted.
        $client_name = $webhook->getClientName();
        $event->setClientName($client_name);
        continue;
      }

      // Filters list belong to other webhooks.
      $other_webhook_filters = array_merge($webhook->getFilters() ?? [], $other_webhook_filters);
    }

    // Filters which belong only to deleted webhook.
    $orphaned_filters = array_diff($deleted_webhook_filters, $other_webhook_filters);
    $this->setFiltersInEvent($orphaned_filters, $client_name, $event);
  }

  /**
   * Sets default and orphaned filters in event.
   *
   * @param array $filters
   *   Orphaned filters.
   * @param string $client_name
   *   Client name.
   * @param \Drupal\acquia_contenthub\Event\AcquiaContentHubUnregisterEvent $event
   *   The event.
   *
   * @throws \Exception
   */
  protected function setFiltersInEvent(array $filters, string $client_name, AcquiaContentHubUnregisterEvent $event) {
    $result = [];
    $client = $this->clientFactory->getClient();

    foreach ($filters as $filter) {
      $filter_info = $client->getFilter($filter);
      if ($filter_info['data']['name'] === 'default_filter_' . $client_name) {
        $event->setDefaultFilter($filter);
        continue;
      }

      $result[$filter_info['data']['name']] = $filter;
    }

    $event->setOrphanedFilters($result);

    if ($event->isDeleteWebhookOnly()) {
      $event->stopPropagation();
    }
  }

}
