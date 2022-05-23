<?php

namespace Drupal\acquia_contenthub\Libs\Depcalc;

use Drupal\Core\Database\Connection;

/**
 * Responsible for executing operations related to depcalc cache.
 */
class DepcalcCacheOperator {

  /**
   * The table name of depcalc cache.
   */
  public const DEPCALC_TABLE = 'cache_depcalc';

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Constructs a DepcalcCacheOperator object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   */
  public function __construct(Connection $database) {
    $this->database = $database;
  }

  /**
   * Checks whether depcalc cache table exists.
   *
   * @return bool
   *   True if it exists.
   */
  public function tableExists(): bool {
    return $this->database->schema()->tableExists(self::DEPCALC_TABLE);
  }

  /**
   * Checks whether depcalc cache table is empty.
   *
   * @return bool
   *   True if empty.
   */
  public function cacheIsEmpty(): bool {
    $count = (int) $this->database->select(self::DEPCALC_TABLE, 'bin')
      ->fields('bin', ['cid'])
      ->countQuery()
      ->execute()
      ->fetchField();
    return $count === 0;
  }

  /**
   * Returns parent dependencies of the entity in question.
   *
   * @param string $entity_uuid
   *   The entity to check.
   *
   * @return array
   *   The parent entities.
   */
  public function getParentDependencies(string $entity_uuid): array {
    $result = $this->database->select(self::DEPCALC_TABLE, 'bin')
      ->fields('bin', ['cid'])
      ->condition('tags', "%{$this->database->escapeLike($entity_uuid)}%", 'LIKE')
      ->execute();
    return $result->fetchCol();
  }

}
