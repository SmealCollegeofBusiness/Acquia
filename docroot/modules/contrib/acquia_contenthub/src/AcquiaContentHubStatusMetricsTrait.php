<?php

namespace Drupal\acquia_contenthub;

/**
 * Trait to set status metrics for Content Hub client cdf entities.
 *
 * @package Drupal\acquia_contenthub
 */
trait AcquiaContentHubStatusMetricsTrait {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Calculates metrics timestamped and indexed by status.
   *
   * @return array
   *   Array of metric statuses.
   */
  public function getStatusMetrics($table_name, $modified_column_name) {
    if (!$this->database->schema()->tableExists($table_name)) {
      return [];
    }
    $query = $this->database
      ->select($table_name, 't')
      ->fields('t', ['status'])
      ->groupBy('t.status');

    $query->addExpression('count(t.status)', 'count');

    $metrics = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);

    $update_time = $this->getMostRecentUpdateTime($table_name, $modified_column_name);

    return [
      'data' => array_combine(
        array_column($metrics, 'status'),
        array_column($metrics, 'count')
      ),
      'last_updated' => $update_time,
    ];
  }

  /**
   * Calculates updated time of most recent published tracked entity.
   *
   * @return false|int
   *   Timestamp of most recent record
   */
  public function getMostRecentUpdateTime($table_name, $modified_column_name) {
    $query = $this->database
      ->select($table_name, 't')
      ->fields('t', [$modified_column_name])
      ->orderBy($modified_column_name, 'DESC')
      ->range(0, 1);

    $last_updated_record = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
    $updateTime = !empty($last_updated_record) ? strtotime($last_updated_record[0][$modified_column_name]) : 0;
    return $updateTime;
  }

}
