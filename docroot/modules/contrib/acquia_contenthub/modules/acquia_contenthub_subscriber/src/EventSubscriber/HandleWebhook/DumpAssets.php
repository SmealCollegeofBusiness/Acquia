<?php

namespace Drupal\acquia_contenthub_subscriber\EventSubscriber\HandleWebhook;

use Drupal\acquia_contenthub\AcquiaContentHubEvents;
use Drupal\acquia_contenthub\ContentHubCommonActions;
use Drupal\acquia_contenthub\Event\HandleWebhookEvent;
use Drupal\acquia_contenthub\Libs\Traits\HandleResponseTrait;
use Drupal\acquia_contenthub_subscriber\SubscriberTracker;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Class DumpAssets.
 *
 * Dumps a dependency list of untracked local entities of particular types.
 *
 * @package Drupal\acquia_contenthub_subscriber\EventSubscriber\HandleWebhook
 */
class DumpAssets implements EventSubscriberInterface {

  use HandleResponseTrait;

  /**
   * The subscriber tracker.
   *
   * @var \Drupal\acquia_contenthub_subscriber\SubscriberTracker
   */
  protected $tracker;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $manager;

  /**
   * The common actions.
   *
   * @var \Drupal\acquia_contenthub\ContentHubCommonActions
   */
  protected $common;

  /**
   * ImportUpdateAssets constructor.
   *
   * @param \Drupal\acquia_contenthub_subscriber\SubscriberTracker $tracker
   *   The subscription tracker.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $manager
   *   The entity type manager.
   * @param \Drupal\acquia_contenthub\ContentHubCommonActions $common
   *   The common actions.
   */
  public function __construct(SubscriberTracker $tracker, EntityTypeManagerInterface $manager, ContentHubCommonActions $common) {
    $this->tracker = $tracker;
    $this->manager = $manager;
    $this->common = $common;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[AcquiaContentHubEvents::HANDLE_WEBHOOK][] = ['onHandleWebhook', 100];
    return $events;
  }

  /**
   * Handles webhook events.
   *
   * @param \Drupal\acquia_contenthub\Event\HandleWebhookEvent $event
   *   The HandleWebhookEvent object.
   *
   * @throws \Exception
   */
  public function onHandleWebhook(HandleWebhookEvent $event): void {
    $payload = $event->getPayload();
    $client_uuid = $event->getClient()->getSettings()->getUuid();

    if ('pending' !== $payload['status'] || 'dump' !== $payload['crud'] || $payload['initiator'] === $client_uuid || empty($payload['types']) || !is_array($payload['types'])) {
      return;
    }

    $entities = [];
    foreach ($payload['types'] as $entity_type) {
      try {
        $loaded = $this->manager->getStorage($entity_type)->loadMultiple();
      }
      catch (\Exception $e) {
        $loaded = [];
      }
      $uuids = [];
      foreach ($loaded as $entity) {
        $uuids[$entity->uuid()] = $entity;
      }
      if ($uuids) {
        foreach ($this->tracker->getUntracked(array_keys($uuids)) as $uuid) {
          $entities[$uuid] = $uuids[$uuid];
        }
      }
    }

    if ($entities) {
      $document = $this->common->getLocalCdfDocument(...array_values($entities));
      $response = $this->getResponse($event, $document->toString());
      $event->setResponse($response);
      $event->stopPropagation();
    }

  }

}
