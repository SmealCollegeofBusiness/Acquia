<?php

namespace Drupal\Tests\acquia_contenthub_s3\Kernel;

use Drupal\acquia_contenthub_s3\S3FileMap;
use PDO;

/**
 * Provides helper methods for s3 file map table interactions.
 *
 * @package Drupal\Tests\acquia_contenthub_s3\Kernel
 */
trait S3FileMapTestTrait {

  /**
   * Clears s3 file map table.
   */
  protected function truncateS3FileMap(): void {
    \Drupal::database()
      ->truncate(S3FileMap::TABLE_NAME)
      ->execute();
  }

  /**
   * Returns every entry from S3FileMap table.
   *
   * @return array
   *   Associative array, keyed by file_uuid.
   *
   * @throws \Exception
   */
  protected function fetchAllData(): array {
    return \Drupal::database()
      ->select(S3FileMap::TABLE_NAME, 'acs3')
      ->fields('acs3')
      ->execute()
      ->fetchAllAssoc('file_uuid', PDO::FETCH_ASSOC);
  }

}
