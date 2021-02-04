<?php

namespace Drupal\acquia_contenthub\Commands;

use Drupal\acquia_contenthub\Client\ClientFactory;
use Drush\Commands\DrushCommands;

/**
 * Drush commands for printing Acquia Content Hub mappings.
 *
 * @package Drupal\acquia_contenthub\Commands
 */
class AcquiaContentHubMappingCommands extends DrushCommands {

  /**
   * The client factory.
   *
   * @var \Drupal\acquia_contenthub\Client\ClientFactory
   */
  protected $clientFactory;

  /**
   * AcquiaContentHubMappingCommands constructor.
   *
   * @param \Drupal\acquia_contenthub\Client\ClientFactory $client_factory
   *   The client factory.
   */
  public function __construct(ClientFactory $client_factory) {
    $this->clientFactory = $client_factory;
  }

  /**
   * Shows Elastic Search field mappings from Content Hub.
   *
   * @command acquia:contenthub-mapping
   * @aliases ach-mapping,acquia-contenthub-mapping
   *
   * @throws \Exception
   */
  public function contenthubMapping() {
    $client = $this->clientFactory->getClient();

    if (!$client) {
      throw new \Exception(dt('Error trying to connect to the Content Hub. Make sure this site is registered to Content hub.'));
    }
    $output = $client->mapping();

    if ($output) {
      $this->output()->writeln(print_r($output, TRUE));
    }
    else {
      throw new \Exception(dt("Error trying to print the elastic search field mappings."));
    }
  }

}
