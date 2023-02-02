<?php

namespace Drupal\acquia_contenthub\Libs\Common;

use Acquia\ContentHubClient\ContentHubClient;
use Drupal\acquia_contenthub\Exception\PlatformIncompatibilityException;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Messenger\MessengerInterface;

/**
 * Responsible for platform related compatibility checks.
 */
class PlatformCompatibilityChecker {

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The acquia_contenthub logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * Constructs a new object.
   *
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $channel
   *   The acquia_contenthub logger channel.
   */
  public function __construct(MessengerInterface $messenger, LoggerChannelInterface $channel) {
    $this->messenger = $messenger;
    $this->logger = $channel;
  }

  /**
   * Checks platform compatibility and returns an appropriate value.
   *
   * If the subscription supports new features provided by Content Hub,
   * the client is returned, otherwise the return value is NULL.
   *
   * @param \Acquia\ContentHubClient\ContentHubClient $client
   *   The registered client to check if the platform is compatible.
   *
   * @return \Acquia\ContentHubClient\ContentHubClient|null
   *   The ContentHubClient if the platform is compatible or null.
   *
   * @throws \Exception
   */
  public function intercept(ContentHubClient $client): ?ContentHubClient {
    if (!$client->isFeatured()) {
      $this->messenger->addWarning(PlatformIncompatibilityException::$incompatiblePlatform);
      $this->logger->warning(PlatformIncompatibilityException::$incompatiblePlatform);
      return NULL;
    }
    return $client;
  }

  /**
   * Checks the subscription and deletes the client if the check fails.
   *
   * @param \Acquia\ContentHubClient\ContentHubClient $client
   *   The registered client to check.
   *
   * @return \Acquia\ContentHubClient\ContentHubClient
   *   The client in case of successful check.
   *
   * @throws \Drupal\acquia_contenthub\Exception\PlatformIncompatibilityException
   */
  public function interceptAndDelete(ContentHubClient $client): ContentHubClient {
    if (!$client->isFeatured()) {
      $client->deleteClient($client->getSettings()->getUuid());
      throw new PlatformIncompatibilityException(
        PlatformIncompatibilityException::$incompatiblePlatform
      );
    }
    return $client;
  }

}
