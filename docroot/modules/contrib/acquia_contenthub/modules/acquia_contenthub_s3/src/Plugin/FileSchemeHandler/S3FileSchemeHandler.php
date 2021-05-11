<?php

namespace Drupal\acquia_contenthub_s3\Plugin\FileSchemeHandler;

use Acquia\ContentHubClient\CDF\CDFObject;
use Acquia\ContentHubClient\CDFAttribute;
use Drupal\acquia_contenthub\Plugin\FileSchemeHandler\FileSchemeHandlerInterface;
use Drupal\acquia_contenthub_s3\S3FileMapper;
use Drupal\Core\Config\Config;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Drupal\file\FileInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * S3 file scheme handler.
 *
 * @FileSchemeHandler(
 *   id = "s3",
 *   label = @Translation("Amazon S3 file handler")
 * )
 */
class S3FileSchemeHandler extends PluginBase implements FileSchemeHandlerInterface, ContainerFactoryPluginInterface {

  /**
   * The stream wrapper manager.
   *
   * @var \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface
   */
  protected $streamWrapperManager;

  /**
   * The s3fs.settings configuration.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $s3fsConfig;

  /**
   * The s3 file mapper service.
   *
   * @var \Drupal\acquia_contenthub_s3\S3FileMapper
   */
  protected $s3FileMapper;

  /**
   * S3FileSchemeHandler constructor.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin id.
   * @param mixed $plugin_definition
   *   The definition.
   * @param \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface $stream_wrapper_manager
   *   The stream wrapper manager service.
   * @param \Drupal\Core\Config\Config $config
   *   The s3fs.settings configuration object.
   * @param \Drupal\acquia_contenthub_s3\S3FileMapper $s3_file_mapper
   *   The s3 file mapper service.
   */
  public function __construct(array $configuration, string $plugin_id, $plugin_definition, StreamWrapperManagerInterface $stream_wrapper_manager, Config $config, S3FileMapper $s3_file_mapper) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->streamWrapperManager = $stream_wrapper_manager;
    $this->s3fsConfig = $config;
    $this->s3FileMapper = $s3_file_mapper;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('stream_wrapper_manager'),
      $container->get('config.factory')->get('s3fs.settings'),
      $container->get('acquia_contenthub_s3.file_mapper')
    );
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Exception
   */
  public function addAttributes(CDFObject $object, FileInterface $file) {
    $uri = $file->getFileUri();
    $streamWrapper = $this->streamWrapperManager->getViaUri($uri);
    $url = $streamWrapper->getExternalUrl();

    $object->addAttribute('file_scheme', CDFAttribute::TYPE_STRING, 's3');
    $object->addAttribute('file_location', CDFAttribute::TYPE_STRING, $url);
    $object->addAttribute('file_uri', CDFAttribute::TYPE_STRING, $uri);
    $object->addAttribute('ach_s3_bucket', CDFAttribute::TYPE_STRING, $this->s3fsConfig->get('bucket'));
    $object->addAttribute('ach_s3_source', CDFAttribute::TYPE_STRING, $this->s3fsConfig->get('root_folder'));
  }

  /**
   * {@inheritdoc}
   */
  public function getFile(CDFObject $object) {
    // No import needed, file is served from s3 bucket.
    return TRUE;
  }

}
