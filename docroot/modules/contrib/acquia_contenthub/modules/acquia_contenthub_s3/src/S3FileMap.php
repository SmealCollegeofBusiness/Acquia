<?php

namespace Drupal\acquia_contenthub_s3;

use Drupal\Component\Uuid\Uuid;
use Drupal\Core\Database\Connection;

/**
 * Responsible for storing information about file s3 source.
 *
 * @package Drupal\acquia_contenthub_s3
 */
class S3FileMap {

  /**
   * The file map table name.
   *
   * Changing this will affect the database table name too.
   * Make change with caution.
   */
  public const TABLE_NAME = 'acquia_contenthub_s3_file_map';

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  private $database;

  /**
   * S3FileOriginLocator constructor.
   *
   * @param \Drupal\Core\Database\Connection $conn
   *   The database connection.
   */
  public function __construct(Connection $conn) {
    $this->database = $conn;
  }

  /**
   * Database table scheme for acquia_contenthub_s3.
   *
   * @return array
   *   The table schema.
   */
  public static function schema(): array {
    return [
      'description' => 'Stores information of file source.',
      'fields' => [
        'file_uuid' => [
          'description' => 'The uuid of the file entity.',
          'type' => 'varchar',
          'length' => 128,
          'not null' => TRUE,
          'default' => '',
        ],
        'bucket' => [
          'description' => 'S3 bucket name of the origin.',
          'type' => 'varchar',
          'length' => 255,
          'not null' => TRUE,
          'default' => '',
        ],
        'root_folder' => [
          'description' => 'The s3 root key prefix of the origin',
          'type' => 'varchar',
          'length' => 255,
          'not null' => TRUE,
          'default' => '',
        ],
        'origin_uuid' => [
          'description' => 'The origin site uuid.',
          'type' => 'varchar',
          'length' => 128,
          'not null' => TRUE,
          'default' => '',
        ],
      ],
      'primary key' => ['file_uuid'],
      'collation' => 'utf8_bin',
    ];
  }

  /**
   * Records the origin of an s3 file.
   *
   * Updates the tracked file if it exists.
   *
   * @param string $file_uuid
   *   The file entity uuid.
   * @param string $bucket
   *   The source bucket name.
   * @param string $root_folder
   *   The source of the file. This is preferably the root_folder of the
   *   publisher site.
   * @param string $origin_uuid
   *   The publisher site's origin uuid.
   *
   * @throws \Exception
   */
  public function record(string $file_uuid, string $bucket, string $root_folder, string $origin_uuid): void {
    foreach (['file_uuid', 'origin_uuid'] as $uuid) {
      if (!Uuid::isValid(strtolower($$uuid))) {
        throw new \InvalidArgumentException("The provided uuid for $uuid is invalid: {$$uuid}");
      }
    }

    $this->isNew($file_uuid) ?
      $this->insert($file_uuid, $bucket, $root_folder, $origin_uuid) :
      $this->update($file_uuid, [
        'bucket' => $bucket,
        'root_folder' => $root_folder,
        'origin_uuid' => $origin_uuid,
      ]);
  }

  /**
   * Removes an element from the map table by its uuid.
   *
   * @param string $file_uuid
   *   The file uuid.
   */
  public function remove(string $file_uuid): void {
    $this->database->delete(self::TABLE_NAME)
      ->condition('file_uuid', $file_uuid, '=')
      ->execute();
  }

  /**
   * Checks if file is new.
   *
   * @param string $file_uuid
   *   The file uuid.
   *
   * @return bool
   *   TRUE if the file hasn't been recorded.
   */
  public function isNew(string $file_uuid): bool {
    return !$this->getFileByUuid($file_uuid);
  }

  /**
   * Get a file by it's uuid.
   *
   * @param string $file_uuid
   *   The file entity uuid.
   *
   * @return object|null
   *   The \stdClass or NULL if the file wasn't recorded.
   */
  public function getFileByUuid(string $file_uuid): ?\stdClass {
    $object = $this->database->select(self::TABLE_NAME, 'acs3')
      ->fields('acs3')
      ->condition('acs3.file_uuid', $file_uuid, '=')
      ->execute()
      ->fetchObject();
    return $object instanceof \stdClass ? $object : NULL;
  }

  /**
   * Returns the file by its uri.
   *
   * The search is carried out by filtering the main file_managed table using
   * the acquia_contenthub_s3 file map table.
   *
   * @param string $uri
   *   The uri of the file.
   *
   * @return object|null
   *   A matching object with the following fields:
   *     - file_uuid
   *     - bucket
   *     - root_folder
   *     - origin_uuid
   */
  public function getFileByUri(string $uri): ?\stdClass {
    $query = $this->database->select(S3FileMap::TABLE_NAME, 'acs3');
    $query->join('file_managed', 'fm', 'fm.uuid = acs3.file_uuid');
    $object = $query->fields('fm', ['uuid'])
      ->fields('acs3', ['bucket', 'root_folder', 'origin_uuid'])
      ->condition('fm.uri', $uri, '=')
      ->execute()
      ->fetchObject();

    return $object instanceof \stdClass ? $object : NULL;
  }

  /**
   * Inserts a new trackable file.
   *
   * @param string $file_uuid
   *   The file entity uuid.
   * @param string $bucket
   *   The source bucket.
   * @param string $root_folder
   *   The s3 root key prefix.
   * @param string $origin_uuid
   *   The publisher site's origin.
   *
   * @throws \Exception
   */
  private function insert(string $file_uuid, string $bucket, string $root_folder, string $origin_uuid): void {
    $this->database->insert(self::TABLE_NAME)
      ->fields([
        'file_uuid' => $file_uuid,
        'bucket' => $bucket,
        'root_folder' => $root_folder,
        'origin_uuid' => $origin_uuid,
      ])
      ->execute();
  }

  /**
   * Updates the fields by the file uuid.
   *
   * @param string $file_uuid
   *   The file uuid.
   * @param array $values
   *   The values to update the fields with. The following format should apply:
   * @code
   *   $values = [
   *     'bucket' => 'some-bucket-name',
   *     'root_folder' => 'root_folder',
   *     'origin_uuid' => '8b11ecea-faf3-4f38-97f0-45177a9d89ae',
   *   ];
   * @endcode
   */
  private function update(string $file_uuid, array $values = []): void {
    $this->database->update(self::TABLE_NAME)
      ->fields($values)
      ->condition('file_uuid', $file_uuid, '=')
      ->execute();
  }

}
