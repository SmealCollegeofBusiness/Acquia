<?php

namespace Drupal\acquia_contenthub_publisher\EventSubscriber\PromoteEntityStatusTrack;

use Drupal\acquia_contenthub\AcquiaContentHubEvents;
use Drupal\acquia_contenthub\Event\BuildClientCdfEvent;
use Drupal\acquia_contenthub_publisher\PublisherTracker;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Class TrackTotals to track published entities in the client CDF object.
 *
 * @package Drupal\acquia_contenthub_publisher\EventSubscriber\PromoteEntityStatusTrack
 */
class TrackTotals implements EventSubscriberInterface {

  /**
   * Keep track of the tracker table.
   *
   * @var \Drupal\acquia_contenthub_publisher\PublisherTracker
   */
  protected $tracker;

  /**
   * TrackTotals constructor.
   *
   * @param \Drupal\acquia_contenthub_publisher\PublisherTracker $tracker
   *   Publisher tracker.
   */
  public function __construct(PublisherTracker $tracker) {
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
      AcquiaContentHubEvents::BUILD_CLIENT_CDF => 'onPromotePublisherEntityStatusTrackTotals',
    ];
  }

  /**
   * Event Handler onPromotePublisherEntityStatusTrackTotals.
   *
   * @param \Drupal\acquia_contenthub\Event\BuildClientCdfEvent $event
   *   The build client cdf event data.
   */
  public function onPromotePublisherEntityStatusTrackTotals(BuildClientCdfEvent $event): void {
    $cdf = $event->getCdf();
    $metadata = $cdf->getMetadata();
    $metadata['metrics']['publisher'] = $this->tracker->getStatusMetrics($this->tracker::EXPORT_TRACKING_TABLE, 'modified');
    $cdf->setMetadata($metadata);
  }

}
