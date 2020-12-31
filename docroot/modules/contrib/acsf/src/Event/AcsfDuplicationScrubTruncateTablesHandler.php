<?php

namespace Drupal\acsf\Event;

/**
 * Truncates various undesirable Drupal core tables.
 */
class AcsfDuplicationScrubTruncateTablesHandler extends AcsfEventHandler {

  /**
   * Implements AcsfEventHandler::handle().
   */
  public function handle() {
    $this->consoleLog(dt('Entered @class', ['@class' => get_class($this)]));

    $tables = [];

    // Clear search indexes and associated caches.
    if (\Drupal::moduleHandler()->moduleExists('search')) {
      // In Drupal 8.8.0 and later, we need to use
      // \Drupal\search\SearchIndex::clear() to avoid deprecated code.
      // But in previous versions of Drupal, we need to use
      // search_index_clear(), which we need to invoke in an obscure way so that
      // PHPStan doesn't get mad at us for calling a deprecated function.
      if (version_compare(\Drupal::VERSION, '8.8.0', '>=')) {
        try {
          \Drupal::service('search.index')->clear();
        }
        catch (\Exception $e) {
          $this->consoleLog(dt('Error occurred during clearing search indexes: @error', ['@error' => $e->getMessage()]));
        }
      }
      else {
        call_user_func('search_index_clear');
      }
      // search_index_clear() does not truncate the following tables:
      $tables[] = 'search_total';
    }

    $tables[] = 'node_counter';
    $tables[] = 'batch';
    $tables[] = 'queue';
    $tables[] = 'semaphore';
    $tables[] = 'sessions';
    $tables[] = 'themebuilder_session';

    $this->truncateTables($tables);
  }

  /**
   * Truncates database tables.
   *
   * @param array $tables
   *   The list of tables to be truncated.
   */
  public function truncateTables(array $tables = []) {
    $connection = \Drupal::database();
    foreach ($tables as $table) {
      if ($connection->schema()->tableExists($table)) {
        $connection->truncate($table)->execute();
      }
    }
  }

}
