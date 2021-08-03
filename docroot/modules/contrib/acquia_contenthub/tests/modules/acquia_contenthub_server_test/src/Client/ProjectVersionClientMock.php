<?php

namespace Drupal\acquia_contenthub_server_test\Client;

use Drupal\acquia_contenthub\Client\ProjectVersionClient;

/**
 * Mocks the ProjectVersionClient service.
 *
 * @package Drupal\acquia_contenthub_server_test\Client
 */
class ProjectVersionClientMock extends ProjectVersionClient {

  /**
   * {@inheritDoc}
   */
  public function getContentHubReleases(): array {
    return ['latest' => '8.x-2.25'];
  }

  /**
   * {@inheritDoc}
   */
  public function getDrupalReleases(string $drupal_version): array {
    return ['also_available' => '9.2.1', 'latest' => '8.9.16'];
  }

}
