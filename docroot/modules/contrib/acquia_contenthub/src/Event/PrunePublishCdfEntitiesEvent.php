<?php

namespace Drupal\acquia_contenthub\Event;

use Acquia\ContentHubClient\CDFDocument;
use Acquia\ContentHubClient\ContentHubClient;
use Symfony\Component\EventDispatcher\Event;

/**
 * Class TrackTotalsEvent.
 *
 * @package Drupal\acquia_contenthub\Event
 */
class PrunePublishCdfEntitiesEvent extends Event {

  /**
   * Keep track of the CH client.
   *
   * @var \Acquia\ContentHubClient\ContentHubClient
   */
  protected $client;

  /**
   * CDF Document.
   *
   * @var \Acquia\ContentHubClient\CDFDocument
   */
  private $document;

  /**
   * Origin.
   *
   * @var string
   */
  private $origin;

  /**
   * TrackTotalsEvent constructor.
   *
   * @param \Acquia\ContentHubClient\ContentHubClient $client
   *   Content Hub Client.
   * @param \Acquia\ContentHubClient\CDFDocument $document
   *   CDF Document.
   * @param string $origin
   *   Origin.
   */
  public function __construct(ContentHubClient $client, CDFDocument $document, string $origin) {
    $this->client = $client;
    $this->document = $document;
    $this->origin = $origin;
  }

  /**
   * Get Origin.
   *
   * @return string
   *   Origin value.
   */
  public function getOrigin(): string {
    return $this->origin;
  }

  /**
   * Get Document.
   *
   * @return \Acquia\ContentHubClient\CDFDocument
   *   CDF Document.
   */
  public function getDocument(): CDFDocument {
    return $this->document;
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
