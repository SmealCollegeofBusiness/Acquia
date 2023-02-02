<?php

namespace Drupal\acquia_contenthub_server_test\Client;

use Acquia\ContentHubClient\ContentHubClient;
use Acquia\ContentHubClient\Settings;
use Drupal\acquia_contenthub\Client\ClientFactory;
use Drupal\acquia_contenthub\Exception\PlatformIncompatibilityException;
use Drupal\Component\Uuid\Uuid;

/**
 * Mocks the client factory service.
 */
class ClientFactoryMock extends ClientFactory {

  /**
   * Override original, and replace Content Hub client with mock.
   */
  public function registerClient(string $name, string $url, string $api_key, string $secret, string $api_version = 'v2'): ContentHubClient {
    if ($name === 'accountIsNotFeatured') {
      throw new PlatformIncompatibilityException(
        PlatformIncompatibilityException::$incompatiblePlatform
      );
    }

    return ContentHubClientMock::register($this->logger, $this->dispatcher, $name, $url, $api_key, $secret);
  }

  /**
   * {@inheritdoc}
   */
  public function getClient(Settings $settings = NULL) {
    if (isset($this->client)) {
      return $this->client;
    }

    if (!$this->settings
      || !Uuid::isValid($this->settings->getUuid())
      || empty($this->settings->getName())
      || empty($this->settings->getUrl())
      || empty($this->settings->getApiKey())
      || empty($this->settings->getSecretKey())
    ) {
      return FALSE;
    }

    // Override configuration.
    $config = [
      'base_url' => $this->settings->getUrl(),
      'client-user-agent' => $this->getClientUserAgent(),
    ];

    $this->client = new ContentHubClientMock(
      $this->logger,
      $this->settings,
      $this->settings->getMiddleware(),
      $this->dispatcher,
      $config
    );

    return $this->client;
  }

  /**
   * {@inheritDoc}
   */
  public function isConfigurationSet(Settings $settings = NULL): bool {
    return TRUE;
  }

}
