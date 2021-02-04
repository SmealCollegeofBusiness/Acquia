<?php

namespace Drupal\acquia_contenthub\Event;

use Acquia\ContentHubClient\ContentHubClient;
use Symfony\Component\EventDispatcher\Event;

/**
 * Event for tracking totals for status metrics.
 *
 * @package Drupal\acquia_contenthub\Event
 */
class TrackTotalsEvent extends Event {

  /**
   * Keep track of the CH client.
   *
   * @var \Acquia\ContentHubClient\ContentHubClient
   */
  protected $client;

  /**
   * TrackTotalsEvent constructor.
   *
   * @param \Acquia\ContentHubClient\ContentHubClient $client
   *   Content Hub Client.
   */
  public function __construct(ContentHubClient $client) {
    $this->client = $client;
  }

  /**
   * Exposes the client.
   *
   * @return \Acquia\ContentHubClient\ContentHubClient
   *   Content Hub Client.
   */
  public function getClient(): ContentHubClient {
    return $this->client;
  }

}
