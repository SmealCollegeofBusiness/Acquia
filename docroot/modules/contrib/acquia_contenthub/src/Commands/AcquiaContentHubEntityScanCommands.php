<?php

namespace Drupal\acquia_contenthub\Commands;

use Acquia\ContentHubClient\CDF\CDFObjectInterface;
use Drupal\acquia_contenthub\Client\ClientFactory;
use Drupal\acquia_contenthub\Commands\Traits\ColorizedOutputTrait;
use Drupal\acquia_contenthub\Commands\Traits\OutputFormatterTrait;
use Drupal\acquia_contenthub\ContentHubConnectionManager;
use Drupal\acquia_contenthub\Libs\Depcalc\DepcalcCacheOperator;
use Drupal\acquia_contenthub\Libs\Depcalc\DepcalcCacheRebuildTrait;
use Drush\Commands\DrushCommands;
use Psr\Log\LogLevel;

/**
 * Bundle of commands related to entity scans.
 *
 * @package Drupal\acquia_contenthub\Commands
 */
class AcquiaContentHubEntityScanCommands extends DrushCommands {

  use ColorizedOutputTrait;
  use OutputFormatterTrait;
  use DepcalcCacheRebuildTrait;

  /**
   * Content Hub Client Factory.
   *
   * @var \Acquia\ContentHubClient\ContentHubClient|bool
   */
  protected $client;

  /**
   * An array of Cloud Filters.
   *
   * @var array
   */
  protected $cloudFilters;

  /**
   * Depcalc cache operator.
   *
   * @var \Drupal\acquia_contenthub\Libs\Depcalc\DepcalcCacheOperator
   */
  protected $operator;

  /**
   * Constructs an AcquiaContentHubPublisherEntityScanCommands object.
   *
   * @param \Drupal\acquia_contenthub\Libs\Depcalc\DepcalcCacheOperator $operator
   *   The depcalc cache operator service.
   * @param \Drupal\acquia_contenthub\Client\ClientFactory $client_factory
   *   The Content Hub client factory.
   */
  public function __construct(
    DepcalcCacheOperator $operator,
    ClientFactory $client_factory
  ) {
    $this->operator = $operator;
    $this->client = $client_factory->getClient();
  }

  /**
   * Validates depcalc state before command execution.
   *
   * @throws \Exception
   */
  public function validateDepcalc(bool $rebuild_cache): void {
    if (!$this->operator->tableExists()) {
      throw new \Exception(sprintf('Cannot find table "%s". Make sure the "depcalc" module is properly installed.', DepcalcCacheOperator::DEPCALC_TABLE));
    }

    if (!$rebuild_cache && $this->operator->cacheIsEmpty()) {
      throw new \Exception('WARNING: The Depcalc Cache is empty.' .
        'We "CANNOT" reliably find upstream dependencies for the entity ' .
        'provided. Use --rebuild-cache flag to run rebuild depcalc cache' .
        'table. It might take a few minutes, run --help to get more' .
        'information.'
      );
    }
  }

  /**
   * Prints filter details.
   *
   * The command scans Content Hub and lists all the filters that would produce
   * a match for the provided entity. It also takes into consideration those
   * entities that list the entity as their dependency. Those entities are
   * marked as Parent in the table.
   *
   * If --rebuild-cache is provided the command will attempt to rebuild the
   * depcalc cache table by going through the entries in the tracking table.
   *
   * @param string $uuid
   *   The entity uuid to scan.
   * @param array $options
   *   Command flags.
   *
   * @option rebuild-cache
   *   Whether to initiate a depcalc cache rebuild using the tracking table
   *   before running scan. This might take a couple of minutes.
   *
   * @command acquia:contenthub:entity-scan:filter
   * @aliases ach-esf, ach-es-f
   *
   * @usage drush acquia:contenthub:scan-entity:filter 848e7343-c079-4235-9693-0f9e6386c7ed
   *   | Scans entity by filter.
   * @usage drush acquia:contenthub:scan-entity:filter --rebuild-cache 848e7343-c079-4235-9693-0f9e6386c7ed
   *   | Rebuild depcalc cache before running the scan.
   *
   * @throws \Exception
   */
  public function scanByFilters(string $uuid, array $options = ['rebuild-cache' => FALSE]): int {
    try {
      $this->validateDepcalc($options['rebuild-cache']);
    }
    catch (\Exception $e) {
      $this->output()->writeln($this->toYellow($e->getMessage()));
      return 1;
    }

    $this->cloudFilters = $this->getFilters();

    if ($options['rebuild-cache']) {
      $this->rebuildDepalcCache();
    }

    $headers = [
      dt('Filter UUID'),
      dt('Filter Name'),
      dt('Entity UUID'),
      dt('Entity Type'),
      dt('Entity Bundle'),
      dt('Dependency Relation'),
      dt('Match'),
    ];
    $message = sprintf('Checking if Entity with UUID = "%s" or any known upstream dependency matches any filter:', $this->comment($uuid));
    $rows = $this->getEntitiesByMatchingFilters($uuid);

    $this->printTableOutput(PHP_EOL . $message, $headers, $rows, $this->output);
    $this->printMatchingFiltersAndSites($rows, $uuid);
    return 0;
  }

  /**
   * Obtains a list of all filters matching the entity and its parents.
   *
   * @param string $entity_uuid
   *   The Entity UUID.
   *
   * @return array
   *   An array of rows.
   *
   * @throws \Exception
   */
  protected function getEntitiesByMatchingFilters(string $entity_uuid): array {
    $rows = [];
    $parents = $this->operator->getParentDependencies($entity_uuid);
    array_unshift($parents, $entity_uuid);
    foreach ($parents as $uuid) {
      $dependency = $uuid == $entity_uuid ?
        $this->info(dt('Original')) :
        $this->comment(dt('Parental'));
      $rows = array_merge($rows, $this->getEntityMatchedFilters($uuid, $dependency));
    }
    return $rows;
  }

  /**
   * Obtains all the filters matched by a particular entity.
   *
   * @param string $uuid
   *   The Entity UUID.
   * @param string $dependency
   *   The type of dependency relation: Original / Parental.
   *
   * @return array
   *   An array of rows.
   *
   * @throws \Exception
   */
  protected function getEntityMatchedFilters(string $uuid, string $dependency): array {
    $rows = [];
    $entity = $this->client->getEntity($uuid);
    if (!$entity instanceof CDFObjectInterface) {
      if (isset($entity['error']['message'])) {
        throw new \Exception($entity['error']['message']);
      }
      throw new \Exception('Unexpected error.');
    }
    $entity_type = $entity->getAttribute('entity_type')->getValue()['und'];
    $entity_bundle = $entity->getAttribute('bundle') ? $entity->getAttribute('bundle')
      ->getValue()['und'] : '';

    foreach ($this->cloudFilters as $cloud_filter) {
      $query = $cloud_filter['data'];
      // Adding Entity.
      $match = $this->checkEntityMatchesFilter($uuid, $query);
      $json_match = json_encode($match);
      $rows[] = [
        $cloud_filter['uuid'],
        $match ? $this->info($cloud_filter['name']) : $cloud_filter['name'],
        $uuid,
        $entity_type,
        $entity_bundle,
        $dependency,
        $match ? $this->info($json_match) : $this->error($json_match),
      ];
    }
    return $rows;
  }

  /**
   * Prints matching filters and sites where the content should be imported.
   *
   * @param array $rows
   *   An array of rows used in previous chart.
   * @param string $uuid
   *   The Entity UUID.
   *
   * @throws \Exception
   */
  protected function printMatchingFiltersAndSites(array $rows, string $uuid): void {
    $filters = [];
    foreach ($rows as $row) {
      if (strpos($row[6], 'true') !== FALSE) {
        $filters[$row[0]] = $row[1];
      }
    }
    if (empty($filters)) {
      $this->output()
        ->writeln(dt('We did not detect any filters matching entity with UUID = "@uuid" or its upstream dependencies.',
          ['@uuid' => $this->comment($uuid)]
        ));
      return;
    }

    $sites = [];
    $webhooks = $this->client->getWebHooks();
    foreach ($webhooks as $webhook) {
      $assigned_filters = $webhook->getFilters() ?? [];

      foreach (array_keys($filters) as $filter_uuid) {
        if (in_array($filter_uuid, $assigned_filters)) {
          $sites[$filter_uuid][] = str_replace('/acquia-contenthub/webhook', '', $webhook->getUrl());
        }
      }
    }
    $messages = [];
    foreach ($filters as $filter_uuid => $filter_name) {
      $messages[] = [
        $filter_uuid,
        $filter_name,
        $sites[$filter_uuid] ? implode(PHP_EOL, $sites[$filter_uuid]) : '',
      ];
    }
    $header = [
      dt('Filter UUID'),
      dt('Filter Name'),
      dt('Webhook URLs assigned to the Filter'),
    ];

    $this->output()->writeln(PHP_EOL . dt('Results:'));
    $this->output()
      ->writeln(str_repeat('-', 57));
    $message = dt('Entity with UUID = "@uuid" or any of its known upstream dependencies matched the following filters and SHOULD be imported to the following sites:',
      ['@uuid' => $this->comment($uuid)]
    );
    $this->printTableOutput($message, $header, $messages, $this->output);
  }

  /**
   * Checks if an entity matches a particular filter.
   *
   * @param string $uuid
   *   The Entity UUID.
   * @param array $query
   *   The filter array.
   *
   * @return bool
   *   TRUE if it matches, FALSE otherwise.
   *
   * @throws \Exception
   */
  protected function checkEntityMatchesFilter(string $uuid, array $query): bool {
    unset($query['highlight']);
    $query['query']['bool']['must'][] = [
      'term' => [
        '_id' => $uuid,
      ],
    ];

    $result = $this->client->searchEntity($query);
    return $result['hits']['total'] === 1;
  }

  /**
   * Obtains the list of filters for this Content Hub subscription.
   *
   * @return array
   *   An array of Content Hub Filters.
   *
   * @throws \Exception
   */
  protected function getFilters(): array {
    $filters = [];
    $cloud_filters = $this->client->listFilters();
    if (empty($cloud_filters['data'])) {
      $this->logger->log(LogLevel::NOTICE, dt('There are no cloud filters defined in this subscription.'));
      return [];
    }
    foreach ($cloud_filters['data'] as $cloud_filter) {
      if (str_starts_with($cloud_filter['name'], ContentHubConnectionManager::DEFAULT_FILTER)) {
        continue;
      }
      $filters[] = $cloud_filter;
    }
    return $filters;
  }

}
