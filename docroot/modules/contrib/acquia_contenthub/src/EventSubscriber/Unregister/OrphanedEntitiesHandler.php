<?php

namespace Drupal\acquia_contenthub\EventSubscriber\Unregister;

use Drupal\acquia_contenthub\AcquiaContentHubEvents;
use Drupal\acquia_contenthub\Client\ClientFactory;
use Drupal\acquia_contenthub\Event\AcquiaContentHubUnregisterEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Discovers entities with given origin.
 *
 * @package Drupal\acquia_contenthub\EventSubscriber\Unregister
 */
class OrphanedEntitiesHandler implements EventSubscriberInterface {

  /**
   * The client factory.
   *
   * @var \Drupal\acquia_contenthub\Client\ClientFactory
   */
  protected $clientFactory;

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[AcquiaContentHubEvents::ACH_UNREGISTER][] = ['onDeleteWebhook'];
    return $events;
  }

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
   * Gathers information about orphaned entities.
   *
   * @param \Drupal\acquia_contenthub\Event\AcquiaContentHubUnregisterEvent $event
   *   The event being dispatched.
   *
   * @throws \Exception
   */
  public function onDeleteWebhook(AcquiaContentHubUnregisterEvent $event): void {
    $client = $this->clientFactory->getClient();
    $origin = !empty($event->getOriginUuid()) ? $event->getOriginUuid() : $client->getSettings()->getUuid();

    $list_entities = $client->listEntities(['origin' => $origin]);

    $orphaned_entites = $list_entities['total'] <= 1 ? 0 : $list_entities['total'];
    $event->setOrphanedEntitiesAmount($orphaned_entites);
    $event->setOrphanedEntities($list_entities['data']);
  }

}
