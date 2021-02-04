<?php

namespace Drupal\acquia_contenthub\Plugin\FileSchemeHandler;

use Drupal\acquia_contenthub\Annotation\FileSchemeHandler;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\StreamWrapper\StreamWrapperManager;
use Drupal\file\FileInterface;

/**
 * The file scheme handler manager.
 *
 * @package Drupal\acquia_contenthub\Plugin\FileSchemeHandler
 */
class FileSchemeHandlerManager extends DefaultPluginManager implements FileSchemeHandlerManagerInterface {

  /**
   * The file system.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * Constructs a new \Drupal\Core\Block\BlockManager object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct('Plugin/FileSchemeHandler', $namespaces, $module_handler, FileSchemeHandlerInterface::class, FileSchemeHandler::class);

    $this->alterInfo('file_scheme_handler');
    $this->setCacheBackend($cache_backend, 'file_scheme_handler_plugins');
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Exception
   */
  public function getHandlerForFile(FileInterface $file) {
    $scheme = $this->getFileScheme($file);
    return $this->createInstance($scheme);
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Exception
   */
  public function isSupportedFileScheme(FileInterface $file) {
    $scheme = $this->getFileScheme($file);
    return in_array($scheme, array_keys($this->getDefinitions()));
  }

  /**
   * Gets the File Scheme.
   *
   * @param \Drupal\file\FileInterface $file
   *   The file.
   *
   * @return bool|mixed|string
   *   The file scheme.
   *
   * @throws \Exception
   */
  protected function getFileScheme(FileInterface $file) {
    $uri = $file->getFileUri();
    $scheme = version_compare(\Drupal::VERSION, '8.8.0', '>=') ? StreamWrapperManager::getScheme($uri) : \Drupal::service('file_system')->uriScheme($uri);
    if (!$scheme) {
      throw new \Exception(sprintf('Failed to load file scheme for %s URI.', $uri));
    }
    return $scheme;
  }

}
