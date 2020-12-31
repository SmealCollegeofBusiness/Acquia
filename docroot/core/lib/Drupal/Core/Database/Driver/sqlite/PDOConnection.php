<?php

namespace Drupal\Core\Database\Driver\sqlite;

/**
 * SQLite-specific implementation of a PDO connection.
 *
 * Since SQLite does not support row level locks, we have to acquire a reserved
 * lock on the database immediately. Because of https://bugs.php.net/42766 we
 * have to create such a transaction manually which also means we cannot use
 * \PDO::commit() or \PDO::rollback() for SQLite.
 */
class PDOConnection extends \PDO {

  /**
   * {@inheritdoc}
   */
  public function beginTransaction() {
    return $this->exec('BEGIN IMMEDIATE TRANSACTION') !== FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function commit() {
    return $this->exec('COMMIT') !== FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function rollBack() {
    return $this->exec('ROLLBACK') !== FALSE;
  }

}
