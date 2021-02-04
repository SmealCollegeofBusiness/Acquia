<?php

namespace Drupal\acquia_contenthub\Commands;

use Drupal\acquia_contenthub\Client\ClientFactory;
use Drupal\Component\Uuid\Uuid;
use Drush\Commands\DrushCommands;

/**
 * Drush commands for listing entities from Acquia Content Hub.
 *
 * @package Drupal\acquia_contenthub\Commands
 */
class AcquiaContentHubListCommands extends DrushCommands {

  /**
   * The client factory.
   *
   * @var \Drupal\acquia_contenthub\Client\ClientFactory
   */
  protected $clientFactory;

  /**
   * AcquiaContentHubListCommands constructor.
   *
   * @param \Drupal\acquia_contenthub\Client\ClientFactory $client_factory
   *   The client factory.
   */
  public function __construct(ClientFactory $client_factory) {
    $this->clientFactory = $client_factory;
  }

  /**
   * List entities from the Content Hub using the listEntities() method.
   *
   * @param array $options
   *   An associative array of options whose values come from cli, aliases,
   *   config, etc.
   *
   * @option limit
   *   The number of entities to be listed.
   * @option start
   *   The offset to start listing the entities (Useful for pagination).
   * @option origin
   *   The Client's Origin UUID.
   * @option language
   *   The Language that will be used to filter field values.
   * @option attributes
   *   The attributes to display for all listed entities
   * @option type
   *   The entity type
   * @option filters
   *   Filters entities according to a set of of conditions as a key=value pair
   *   separated by commas. You could use regex too.
   * @option decode
   *   Decodes the metadata 'data' element to make it easier to understand the
   *   content of each CDF entity stored in Content Hub.
   *
   * @command acquia:contenthub-list
   * @aliases ach-list,acquia-contenthub-list
   *
   * @return mixed
   *   Content Hub list.
   *
   * @throws \Exception
   */
  public function contenthubList(array $options = ['limit' => NULL, 'start' => NULL, 'origin' => NULL, 'language' => NULL, 'attributes' => NULL, 'type' => NULL, 'filters' => NULL, 'decode' => NULL]) { // @codingStandardsIgnoreLine.
    $client = $this->clientFactory->getClient();
    if (!$client) {
      throw new \Exception(dt('Error trying to connect to the Content Hub. Make sure this site is registered to Content hub.'));
    }
    $list_options = [];

    // Obtaining the limit.
    $limit = $options['limit'];
    if (isset($limit)) {
      $limit = (int) $limit;
      if ($limit < 1 || $limit > 1000) {
        throw new \Exception(dt("The limit has to be an integer from 1 to 1000."));
      }
      else {
        $list_options['limit'] = $limit;
      }
    }

    // Obtaining the offset.
    $start = $options['start'];
    if (isset($start)) {
      if (!is_numeric($start)) {
        throw new \Exception(dt("The start offset has to be numeric starting from 0."));
      }
      else {
        $list_options['start'] = $start;
      }
    }

    // Filtering by origin.
    $origin = $options['origin'];
    if (isset($origin)) {
      if (Uuid::isValid($origin)) {
        $list_options['origin'] = $origin;
      }
      else {
        throw new \Exception(dt("The origin has to be a valid UUID."));
      }
    }

    // Filtering by language.
    // @todo Add a query to validate languages in plexus.
    $language = $options['language'];
    if (isset($language)) {
      $list_options['language'] = $language;
    }

    // Filtering by fields.
    $fields = $options['attributes'];
    if (isset($fields)) {
      $list_options['fields'] = $fields;
    }

    // Filtering by type.
    $type = $options['type'];
    if (isset($type)) {
      $list_options['type'] = $type;
    }

    // Building the filters.
    $filters = $options['filters'];
    if (isset($filters)) {
      $filters = isset($filters) ? explode(",", $filters) : FALSE;
      foreach ($filters as $key => $filter) {
        [$name, $value] = explode("=", $filter);
        $filters[$name] = $value;
        unset($filters[$key]);
      }
      $list_options['filters'] = $filters;
    }

    $list = $client->listEntities($list_options);

    // Decode the base64 'data' element in 'metadata'.
    if ($options['decode'] && is_array($list) && $list['success'] === TRUE && is_array($list['data'])) {
      foreach ($list['data'] as &$cdf_entity) {
        $this->decodeEntityArrayMetadata($cdf_entity);
      }
    }

    $this->output()->writeln(print_r($list, TRUE));
  }

  /**
   * Decodes the base64 'data' element inside a CDF entity 'metadata'.
   *
   * @param array $cdf_entity
   *   The CDF entity array before it is written to the output.
   */
  protected function decodeEntityArrayMetadata(array &$cdf_entity) {
    $types = [
      'drupal8_content_entity',
      'drupal8_config_entity',
      'rendered_entity',
    ];

    if (in_array($cdf_entity['type'], $types)) {
      $cdf_entity['metadata']['data'] = base64_decode($cdf_entity['metadata']['data']);
    }
  }

}
