<?php

namespace Drupal\acquia_contenthub\Plugin\FileSchemeHandler;

use Acquia\ContentHubClient\CDF\CDFObject;
use Acquia\ContentHubClient\CDFAttribute;
use Drupal\Core\File\Exception\FileException;
use Drupal\Core\File\Exception\FileWriteException;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\StreamWrapper\StreamWrapperManager;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Drupal\Core\Url;
use Drupal\file\FileInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The handler for files with a public file scheme.
 *
 * @FileSchemeHandler(
 *   id = "public",
 *   label = @Translation("Public file handler")
 * )
 */
class PublicFileSchemeHandler extends PluginBase implements FileSchemeHandlerInterface, ContainerFactoryPluginInterface {

  /**
   * The stream wrapper manager.
   *
   * @var \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface
   */
  protected $streamWrapperManager;

  /**
   * The file system object.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * PublicFileSchemeHandler constructor.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin id.
   * @param mixed $plugin_definition
   *   The definition.
   * @param \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface $stream_wrapper_manager
   *   The stream wrapper manager service.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system.
   */
  public function __construct(array $configuration, string $plugin_id, $plugin_definition, StreamWrapperManagerInterface $stream_wrapper_manager, FileSystemInterface $file_system) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->streamWrapperManager = $stream_wrapper_manager;
    $this->fileSystem = $file_system;
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
      $container->get('file_system')
    );
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Exception
   */
  public function addAttributes(CDFObject $object, FileInterface $file) {
    $uri = $file->getFileUri();
    $directory_path = $this->streamWrapperManager->getViaUri($uri)->getDirectoryPath();
    if (version_compare(\Drupal::VERSION, '8.8.0', '>=')) {
      $url = Url::fromUri('base:' . $directory_path . '/' . StreamWrapperManager::getTarget($uri), ['absolute' => TRUE])->toString();
    }
    else {
      $url = Url::fromUri('base:' . $directory_path . '/' . file_uri_target($uri), ['absolute' => TRUE])->toString();
    }
    $object->addAttribute('file_scheme', CDFAttribute::TYPE_STRING, 'public');
    $object->addAttribute('file_location', CDFAttribute::TYPE_STRING, $url);
    $object->addAttribute('file_uri', CDFAttribute::TYPE_STRING, $uri);
  }

  /**
   * {@inheritdoc}
   */
  public function getFile(CDFObject $object) {
    if ($object->getAttribute('file_location') && $object->getAttribute('file_uri')) {
      $url = $object->getAttribute('file_location')->getValue()[LanguageInterface::LANGCODE_NOT_SPECIFIED];
      $uri = $object->getAttribute('file_uri')->getValue()[LanguageInterface::LANGCODE_NOT_SPECIFIED];
      $dirname = $this->fileSystem->dirname($uri);
      if ($this->fileSystem->prepareDirectory($dirname, FileSystemInterface::CREATE_DIRECTORY)) {
        $contents = file_get_contents($url);
        return $this->saveData($contents, $uri, FileSystemInterface::EXISTS_REPLACE);
      }
    }
    return FALSE;
  }

  /**
   * Save the data to file system.
   *
   * @param string $data
   *   Data to save.
   * @param string $destination
   *   Destination of data.
   * @param int $replace
   *   Replacement option.
   *
   * @return bool|string
   *   String saved or bool if failed.
   */
  private function saveData(string $data, string $destination, int $replace) {
    try {
      return $this->fileSystem->saveData($data, $destination, $replace);
    }
    catch (FileWriteException $e) {
      \Drupal::messenger()->addError(t('The file could not be created.'));
      return FALSE;
    }
    catch (FileException $e) {
      return FALSE;
    }
  }

}
