<?php

namespace Drupal\acquia_contenthub_subscriber\Commands;

use Drupal\acquia_contenthub\Client\ClientFactory;
use Drupal\acquia_contenthub\Commands\AcquiaContentHubEntityScanCommands;
use Drupal\acquia_contenthub\Libs\Depcalc\DepcalcCacheOperator;
use Drupal\Core\Config\Config;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Entity scan related commands.
 */
class AcquiaContentHubSubscriberEntityScanCommands extends AcquiaContentHubEntityScanCommands {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * The acquia_contenthub.admin_settings config.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $chConfig;

  /**
   * Constructs a new AcquiaContentHubSubscriberEntityScanCommands object.
   *
   * @param \Drupal\acquia_contenthub\Libs\Depcalc\DepcalcCacheOperator $operator
   *   Depcalc cache operator service.
   * @param \Drupal\acquia_contenthub\Client\ClientFactory $client_factory
   *   Client factory service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   * @param \Drupal\Core\Config\Config $config
   *   The acquia_contenthub.admin_settings config.
   */
  public function __construct(
    DepcalcCacheOperator $operator,
    ClientFactory $client_factory,
    EntityTypeManagerInterface $entity_type_manager,
    Connection $connection,
    Config $config
  ) {
    parent::__construct($operator, $client_factory);
    $this->entityTypeManager = $entity_type_manager;
    $this->connection = $connection;
    $this->chConfig = $config;
  }

  /**
   * Prints filter details.
   *
   * The command scans Content Hub and lists all the filters that would produce
   * a match for the provided entity. It also takes into consideration those
   * entities that list the entity as their dependency. Those entities are
   * marked as Parent in the table.
   *
   * Subscriber module is enabled, which extended the command functionality. The
   * command will check the interest list and the database as well.
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
    $code = parent::scanByFilters($uuid, $options);
    if ($code !== 0) {
      return $code;
    }
    $this->printInterestListAndDatabaseScanDetails($uuid);
    return 0;
  }

  /**
   * Prints out the results of interest list and database lookup in a table.
   *
   * @param string $uuid
   *   The entity uuid to execute the lookup against.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Exception
   */
  public function printInterestListAndDatabaseScanDetails(string $uuid): void {
    $header = [
      dt('Entity found on Interest List'),
      dt('Entity found in Database'),
    ];
    $content[] = [
      $this->checkEntityOnInterestList($uuid) ? $this->info(dt('Yes')) : $this->error(dt('No')),
      $this->checkEntityInDatabase($uuid) ? $this->info(dt('Yes')) : $this->error(dt('No')),
    ];
    $this->printTableOutput(dt('Interest list and Database information:'), $header, $content, $this->output);
  }

  /**
   * Checks if the entity is on the interest list.
   *
   * @param string $uuid
   *   The entity uuid.
   *
   * @return bool
   *   True if it exists.
   *
   * @throws \Exception
   */
  public function checkEntityOnInterestList(string $uuid): bool {
    $webhook = $this->chConfig->get('webhook');
    if (!$webhook) {
      throw new \Exception('Webhook cannot be found');
    }
    $list = $this->client->getInterestsByWebhook($webhook['uuid']);
    return in_array($uuid, $list, TRUE);
  }

  /**
   * Checks if the entity exists in database.
   *
   * @param string $uuid
   *   The entity uuid.
   *
   * @return bool
   *   True if it exists.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function checkEntityInDatabase(string $uuid): bool {
    $repository = $this->getEntityRepository();
    foreach ($this->entityTypeManager->getDefinitions() as $definition) {
      $entity = $repository->loadEntityByUuid($definition->id(), $uuid);
      if (!$entity) {
        continue;
      }
      return TRUE;
    }
    return FALSE;
  }

}
