<?php

namespace Drupal\acquia_contenthub_s3;

use Acquia\ContentHubClient\CDF\CDFObject;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Drupal\file\FileInterface;

/**
 * Class S3FileMapper.
 *
 * @package Drupal\acquia_contenthub_s3
 */
class S3FileMapper {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The file system.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * The s3 file mapper.
   *
   * @var \Drupal\acquia_contenthub_s3\S3FileMap
   */
  protected $s3FileMap;

  /**
   * The stream wrapper manager used to instantiate appropriate stream wrapper.
   *
   * @var \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface
   *
   * @see \Drupal\s3fs\StreamWrapper\S3fsStream
   */
  protected $swManager;

  /**
   * S3FileMapper constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The drupal file system service.
   * @param \Drupal\acquia_contenthub_s3\S3FileMap $file_map
   *   The s3 file map.
   * @param \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface $stream_wrapper_manager
   *   The stream wrapper manager service.
   */
  public function __construct(ConfigFactoryInterface $config_factory, FileSystemInterface $file_system, S3FileMap $file_map, StreamWrapperManagerInterface $stream_wrapper_manager) {
    $this->configFactory = $config_factory;
    $this->fileSystem = $file_system;
    $this->s3FileMap = $file_map;
    $this->swManager = $stream_wrapper_manager;
  }

  /**
   * Maps the file entity and its s3 source.
   *
   * @param \Acquia\ContentHubClient\CDF\CDFObject $cdf
   *   The cdf representation of the file entity.
   * @param \Drupal\file\FileInterface $file
   *   The file entity to map to.
   *
   * @throws \Exception
   */
  public function mapS3File(CDFObject $cdf, FileInterface $file): void {
    $root_folder = $cdf->getAttribute('ach_s3_source');
    $bucket = $cdf->getAttribute('ach_s3_bucket');
    if (!$root_folder || !$bucket) {
      return;
    }

    $root_folder = $root_folder->getValue()[LanguageInterface::LANGCODE_NOT_SPECIFIED];
    $bucket = $bucket->getValue()[LanguageInterface::LANGCODE_NOT_SPECIFIED];
    $uuid = $file->uuid();
    $this->s3FileMap->record($uuid, $bucket, $root_folder, $cdf->getOrigin());
    if (!$this->s3FileMap->isNew($uuid)) {
      $this->invalidateFileCache($file);
    }
  }

  /**
   * Relocates s3 file if it comes from external bucket.
   *
   * @param \Drupal\file\FileInterface $file
   *   The file entity in question.
   *
   * @todo: Activate the feature when auto update disabling has been resolved.
   * @todo: acquia_contenthub_unsubscribe module
   *
   * @throws \Exception
   */
  public function relocateS3File(FileInterface $file) {
    $uuid = $file->uuid();
    $mapping = $this->s3FileMap->getFileByUuid($uuid);
    $origin = $this->configFactory
      ->get('acquia_contenthub.admin_settings')
      ->get('origin');

    if (!$mapping || $mapping->origin_uuid === $origin) {
      return;
    }

    $s3fs_settings = $this->configFactory->get('s3fs.settings');
    $stream_wrapper = $this->swManager->getViaUri($file->getFileUri());
    $data = file_get_contents($stream_wrapper->getExternalUrl());

    $this->s3FileMap->record(
      $file->uuid(),
      $s3fs_settings->get('bucket'),
      $s3fs_settings->get('root_folder'),
      $origin
    );

    // The fully qualified s3 uri will be constructed from the previously saved
    // data of s3 file map. Therefore the stream wrapper can operate with the
    // updated data of the table.
    $this->fileSystem->saveData(
      $data,
      $file->getFileUri(),
      FileSystemInterface::EXISTS_REPLACE
    );

    $this->invalidateFileCache($file);
  }

  /**
   * Invalidates file cache.
   *
   * @param \Drupal\file\FileInterface $file
   *   The cached file to invalidate.
   */
  protected function invalidateFileCache(FileInterface $file) {
    Cache::invalidateTags(['file:' . $file->id()]);
  }

}
