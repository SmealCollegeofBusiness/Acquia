<?php

namespace Drupal\acquia_contenthub\Client;

use Acquia\ContentHubClient\CDF\CDFObject;
use Acquia\ContentHubClient\CDF\ClientCDFObject;
use Acquia\ContentHubClient\ContentHubClient;
use Drupal\acquia_contenthub\AcquiaContentHubEvents;
use Drupal\acquia_contenthub\Event\BuildClientCdfEvent;
use Drupal\Core\Config\Config;
use Drupal\Core\Logger\LoggerChannelInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Updates Client CDF metrics to CH service.
 */
class CdfMetricsManager {

  /**
   * Content Hub Client.
   *
   * @var \Acquia\ContentHubClient\ContentHubClient
   */
  protected $client;

  /**
   * ACH Config.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $config;

  /**
   * Event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $dispatcher;

  /**
   * Settings object.
   *
   * @var \Acquia\ContentHubClient\Settings
   */
  protected $settings;

  /**
   * ACH Logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * CdfMetricsManager constructor.
   *
   * @param \Drupal\acquia_contenthub\Client\ClientFactory $factory
   *   Content Hub client factory.
   * @param \Drupal\Core\Config\Config $config
   *   CH Config settings.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   ACH Logger.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $dispatcher
   *   The event dispatcher.
   */
  public function __construct(
    ClientFactory $factory,
    Config $config,
    LoggerChannelInterface $logger,
    EventDispatcherInterface $dispatcher
  ) {
    $this->client = $factory->getClient();
    $this->config = $config;
    $this->logger = $logger;
    $this->dispatcher = $dispatcher;
    $this->settings = $factory->getSettings();
  }

  /**
   * Sends client cdf metrics updates.
   *
   * @param \Acquia\ContentHubClient\ContentHubClient|null $client
   *   (Optional) The Content Hub client.
   *
   * @throws \Exception
   */
  public function sendClientCdfUpdates(?ContentHubClient $client = NULL): void {
    if ($client !== NULL) {
      $this->client = $client;
      $this->settings = $client->getSettings();
    }

    if (!$this->isStableConnection()) {
      return;
    }

    $send_clientcdf_update = $this->config->get('send_clientcdf_updates') ?? TRUE;
    $send_update = $this->config->get('send_contenthub_updates') ?? TRUE;

    // Only send Client CDF updates, if send update flag
    // and send client cdf update both are TRUE.
    if (!$send_update || !$send_clientcdf_update) {
      return;
    }

    if ($this->client->getRemoteSettings()) {
      $event = new BuildClientCdfEvent(ClientCDFObject::create($this->settings->getUuid(), ['settings' => $this->settings->toArray()]));
      $this->dispatcher->dispatch(AcquiaContentHubEvents::BUILD_CLIENT_CDF, $event);
      $local_cdf = $event->getCdf();
      $this->updateClientCdf($local_cdf);
    }
  }

  /**
   * Checks if the client and the settings are properly set.
   *
   * @return bool
   *   True if both are set.
   */
  protected function isStableConnection(): bool {
    if (!$this->client) {
      $this->logger->error('Could not instantiate Content Hub Client.');
      return FALSE;
    }

    if (!$this->settings) {
      $this->logger->error('Could not retrieve Content Hub settings.');
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Updates Client CDF.
   *
   * Send the clientCDFObject if it doesn't exist in CH yet or doesn't
   * match what exists in CH today.
   *
   * Don't update the ClientCDF if the remote object matches the local one.
   *
   * @param \Acquia\ContentHubClient\CDF\ClientCDFObject $local_cdf
   *   Local client cdf.
   *
   * @return bool
   *   TRUE if successful; FALSE otherwise.
   *
   * @throws \Exception
   */
  protected function updateClientCdf(ClientCDFObject $local_cdf): bool {
    /** @var \Acquia\ContentHubClient\CDF\ClientCDFObject $remote_cdf */
    $remote_cdf = $this->client->getEntity($this->settings->getUuid());

    if ($remote_cdf instanceof ClientCDFObject && $this->compareHashes($remote_cdf, $local_cdf)) {
      return TRUE;
    }
    $response = $this->client->putEntities($local_cdf);
    if ($response->getStatusCode() === 202) {
      return TRUE;
    }

    $this->logger->debug('Updating Client CDF failed with http status %error', [
      '%error' => $response->getStatusCode(),
    ]);
    return FALSE;
  }

  /**
   * Compares hashes of local and remote client CDFs.
   *
   * @param \Acquia\ContentHubClient\CDF\ClientCDFObject $remote_cdf
   *   Remote client cdf object.
   * @param \Acquia\ContentHubClient\CDF\ClientCDFObject $local_cdf
   *   Local client cdf object.
   *
   * @return bool
   *   Returns true if hash exists and matches otherwise false.
   */
  protected function compareHashes(ClientCDFObject $remote_cdf, ClientCDFObject $local_cdf): bool {
    return $remote_cdf->getAttribute('hash') &&
      $this->getClientCdfHash($remote_cdf) === $this->getClientCdfHash($local_cdf);
  }

  /**
   * Returns Client Cdf Hash.
   *
   * @param \Acquia\ContentHubClient\CDF\ClientCDFObject $cdf
   *   Client cdf object.
   *
   * @return string
   *   Client cdf hash.
   */
  protected function getClientCdfHash(ClientCDFObject $cdf): string {
    $hash_attribute = $cdf->getAttribute('hash');
    return $hash_attribute ? $hash_attribute->getValue()[CDFObject::LANGUAGE_UNDETERMINED] : '';
  }

}
