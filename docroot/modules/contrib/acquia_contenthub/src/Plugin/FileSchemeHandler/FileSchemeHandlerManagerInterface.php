<?php

namespace Drupal\acquia_contenthub\Plugin\FileSchemeHandler;

use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\file\FileInterface;

/**
 * Interface for file scheme handler managers.
 *
 * @package Drupal\acquia_contenthub\Plugin\FileSchemeHandler
 */
interface FileSchemeHandlerManagerInterface extends PluginManagerInterface {

  /**
   * Returns file scheme handler.
   *
   * @param \Drupal\file\FileInterface $file
   *   File.
   *
   * @return FileSchemeHandlerInterface
   *   File scheme handler.
   */
  public function getHandlerForFile(FileInterface $file);

  /**
   * Checks whether the current file has a supported scheme handler.
   *
   * @param \Drupal\file\FileInterface $file
   *   The file.
   *
   * @return bool
   *   TRUE if scheme handler is supported, FALSE otherwise.
   */
  public function isSupportedFileScheme(FileInterface $file);

}
