<?php

namespace Drupal\acquia_contenthub\Plugin\FileSchemeHandler;

use Acquia\ContentHubClient\CDF\CDFObject;
use Drupal\Core\Plugin\PluginBase;
use Drupal\file\FileInterface;

/**
 * The file scheme handler for http schemes.
 *
 * @FileSchemeHandler(
 *   id = "http",
 *   label = @Translation("Http file handler")
 * )
 */
class HttpFileSchemeHandler extends PluginBase implements FileSchemeHandlerInterface {

  /**
   * {@inheritdoc}
   */
  public function addAttributes(CDFObject $object, FileInterface $file) {}

  /**
   * {@inheritdoc}
   */
  public function getFile(CDFObject $object) {}

}
