<?php

namespace Drupal\acquia_contenthub_s3;

use Acquia\ContentHubClient\CDF\CDFObjectInterface;
use Drupal\acquia_contenthub\ContentHubCommonActions;
use Drupal\Core\Config\Config;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManager;

/**
 * Responsible for locating the s3 source of the given file url.
 *
 * @package Drupal\acquia_contenthub_s3
 */
class S3FileOriginLocator {

  /**
   * The s3 file mapper.
   *
   * @var \Drupal\acquia_contenthub_s3\S3FileMap
   */
  protected $s3FileMap;

  /**
   * File entity storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $fileEntityStorage;

  /**
   * Common actions of content hub.
   *
   * @var \Drupal\acquia_contenthub\ContentHubCommonActions
   */
  protected $commonActions;

  /**
   * Acquia Content Hub settings object.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $chConfig;

  /**
   * S3FileOriginLocator constructor.
   *
   * @param \Drupal\acquia_contenthub_s3\S3FileMap $file_map
   *   The database connection.
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The file entity storage.
   * @param \Drupal\acquia_contenthub\ContentHubCommonActions $common
   *   The ContentHub common actions service.
   * @param \Drupal\Core\Config\Config $config
   *   The acquia_contenthub.admin_settings config object.
   */
  public function __construct(S3FileMap $file_map, EntityStorageInterface $storage, ContentHubCommonActions $common, Config $config) {
    $this->fileEntityStorage = $storage;
    $this->s3FileMap = $file_map;
    $this->commonActions = $common;
    $this->chConfig = $config;
  }

  /**
   * Returns the source of the s3 file.
   *
   * @param string $uri
   *   The s3 originated file uri.
   *
   * @return array
   *   The s3 source with the bucket and root_folder.
   *
   * @throws \Exception
   */
  public function getS3FileSource(string $uri): array {
    $target_uri = StreamWrapperManager::getTarget($uri);
    if ($target_uri) {
      $uri = $target_uri;
    }

    $uri = $this->getOriginalFileUrl($uri);
    $tracked_s3_file = $this->s3FileMap->getFileByUri($uri);
    if (!$tracked_s3_file) {
      $tracked_s3_file = $this->getRemoteFile($uri);
      if (!$tracked_s3_file) {
        return $this->getLocalS3FileSource();
      }
    }

    if ($this->chConfig->get('origin') === $tracked_s3_file->origin_uuid) {
      return $this->getLocalS3FileSource();
    }

    return [
      'bucket' => $tracked_s3_file->bucket,
      'root_folder' => $tracked_s3_file->root_folder,
    ];
  }

  /**
   * Returns the remote file by the uri if there is one, NULL otherwise.
   *
   * @param string $uri
   *   The uri to identify the file.
   *
   * @return object|null
   *   An object with the following fields:
   *
   * @see \Drupal\acquia_contenthub_s3\S3FileMap::getFileByUuid()
   *
   * @throws \Exception
   */
  protected function getRemoteFile(string $uri): ?\stdClass {
    $files = $this->fileEntityStorage->loadByProperties(['uri' => $uri]);
    if (!$files) {
      return NULL;
    }

    // Use the tracker to avoid unnecessary requests to Content Hub.
    $container = \Drupal::getContainer();
    $tracker = $container->has('acquia_contenthub_publisher.tracker') ?
      $container->get('acquia_contenthub_publisher.tracker') : NULL;
    // Due to the current implementation of s3fs it is possible that a site
    // stores multiple file entities with the same uri. We pick and store the
    // one that was actually exported and can be located in ContentHub.
    foreach ($files as $file) {
      if (!is_null($tracker)) {
        // Do not initiate entity retrieval since it is already in the export
        // table.
        $tracked_file = $tracker->get($file->uuid());
        if ($tracked_file) {
          continue;
        }
      }

      $remote_file = $this->commonActions->getRemoteEntity($file->uuid());
      if ($remote_file) {
        $this->s3FileMap->record(
          $remote_file->getUuid(),
          $this->getAttributeValue($remote_file, 'ach_s3_bucket'),
          $this->getAttributeValue($remote_file, 'ach_s3_source'),
          $remote_file->getOrigin()
        );

        return $this->s3FileMap->getFileByUuid($remote_file->getUuid());
      }
    }

    return NULL;
  }

  /**
   * Returns the original file url from the current request.
   *
   * Transforms the image style provided derivative url back to the original.
   *
   * @param string $uri
   *   The uri to transform.
   *
   * @return string
   *   The original url.
   */
  protected function getOriginalFileUrl(string $uri): string {
    $parts = explode('s3/', $uri);
    $transformed_uri = isset($parts[1]) ? $parts[1] : $parts[0];
    return "s3://$transformed_uri";
  }

  /**
   * Returns the value of an arbitrary CDF attribute.
   *
   * @param \Acquia\ContentHubClient\CDF\CDFObjectInterface $object
   *   The object to get the attribute value from.
   * @param string $attribute
   *   The attribute to get.
   *
   * @return mixed
   *   The value of the attribute.
   */
  protected function getAttributeValue(CDFObjectInterface $object, string $attribute) {
    $attr = $object->getAttribute($attribute);
    if (!$attr) {
      return '';
    }

    return $attr->getValue()[LanguageInterface::LANGCODE_NOT_SPECIFIED];
  }

  /**
   * Returns the current site's s3fs original configuration.
   *
   * @return array
   *   The s3 source, bucket and root_folder.
   */
  protected function getLocalS3FileSource(): array {
    // We need the original unmodified s3fs config object.
    // phpcs:ignore
    $config = \Drupal::config('s3fs.settings');
    return [
      'bucket' => $config->get('bucket'),
      'root_folder' => $config->get('root_folder'),
    ];
  }

}
