<?php

namespace Drupal\acquia_contenthub\Plugin\FileSchemeHandler;

use Acquia\ContentHubClient\CDF\CDFObject;
use Drupal\Core\Plugin\PluginBase;
use Drupal\file\FileInterface;

/**
 * The file scheme handler for files without schemes.
 *
 * @FileSchemeHandler(
 *   id = "empty",
 *   label = @Translation("Empty scheme file handler")
 * )
 */
class EmptySchemeHandler extends PluginBase implements FileSchemeHandlerInterface {

  /**
   * {@inheritdoc}
   */
  public function addAttributes(CDFObject $object, FileInterface $file) {}

  /**
   * {@inheritdoc}
   */
  public function getFile(CDFObject $object) {}

}
