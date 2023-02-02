<?php

namespace Drupal\acquia_contenthub_dashboard\Libs;

use Drupal\acquia_contenthub\Client\ClientFactory;

/**
 * Service for fetching the registered publisher webhooks.
 *
 * @package Drupal\acquia_contenthub_dashboard\Libs
 */
class ContentHubAllowedOrigins {

  /**
   * Content Hub client factory.
   *
   * @var \Drupal\acquia_contenthub\Client\ClientFactory
   */
  protected $clientFactory;

  /**
   * ContentHubAllowedOrigins constructor.
   *
   * @param \Drupal\acquia_contenthub\Client\ClientFactory $client_factory
   *   Content Hub Client Factory.
   */
  public function __construct(ClientFactory $client_factory) {
    $this->clientFactory = $client_factory;
  }

  /**
   * Get Content Hub Publisher registered webhooks.
   *
   * @return array
   *   Publisher registered webhooks.
   *
   * @throws \Exception
   */
  public function getAllowedOrigins(): array {
    $client = $this->clientFactory->getClient();
    if (!$client) {
      return [];
    }

    $allowed_origins = [];
    $client_entities = $client->queryEntities([
      'type' => 'client',
      'fields' => 'publisher',
    ]);
    foreach ($client_entities['data'] as $client_entity) {
      $publisher = $client_entity['attributes']['publisher']['und'] ?? FALSE;
      $webhook_url = $client_entity['metadata']['settings']['webhook']['settings_url'] ?? [];
      if (!$publisher || empty($webhook_url)) {
        continue;
      }

      $allowed_origins[] = $webhook_url;
    }

    return array_unique($allowed_origins);
  }

}
