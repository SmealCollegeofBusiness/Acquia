<?php

namespace Drupal\acquia_contenthub_subscriber\EventSubscriber\PromoteEntityStatusTrack;

use Drupal\acquia_contenthub\AcquiaContentHubEvents;
use Drupal\acquia_contenthub\Event\BuildClientCdfEvent;
use Drupal\acquia_contenthub_subscriber\SubscriberTracker;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Class TrackTotals to track subscribed entities in the client CDF object.
 *
 * @package Drupal\acquia_contenthub_subscriber\EventSubscriber\PromoteEntityStatusTrack
 */
class TrackTotals implements EventSubscriberInterface {

  /**
   * Keep track of the tracker table.
   *
   * @var \Drupal\acquia_contenthub_subscriber\SubscriberTracker
   */
  protected $tracker;

  /**
   * TrackTotals constructor.
   *
   * @param \Drupal\acquia_contenthub_subscriber\SubscriberTracker $tracker
   *   Subscriber tracker.
   */
  public function __construct(SubscriberTracker $tracker) {
    $this->tracker = $tracker;
  }

  /**
   * Retrieve subscribed events.
   *
   * @return array
   *   Array of subscribed events.
   */
  public static function getSubscribedEvents(): array {
    return [
      AcquiaContentHubEvents::BUILD_CLIENT_CDF => 'onPromoteEntityStatusTrackTotals',
    ];
  }

  /**
   * Event Handler onPromoteEntityStatusTrackTotals.
   *
   * @param \Drupal\acquia_contenthub\Event\BuildClientCdfEvent $event
   *   The build client cdf event data.
   */
  public function onPromoteEntityStatusTrackTotals(BuildClientCdfEvent $event): void {
    $cdf = $event->getCdf();
    $metadata = $cdf->getMetadata();
    $metadata['metrics']['subscriber'] = $this->tracker->getStatusMetrics($this->tracker::IMPORT_TRACKING_TABLE, 'last_imported');
    $cdf->setMetadata($metadata);
  }

}
