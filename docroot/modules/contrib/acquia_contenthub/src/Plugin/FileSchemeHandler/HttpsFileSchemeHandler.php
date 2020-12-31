<?php

namespace Drupal\acquia_contenthub\Plugin\FileSchemeHandler;

use Acquia\ContentHubClient\CDF\CDFObject;
use Drupal\Core\Plugin\PluginBase;
use Drupal\file\FileInterface;

/**
 * Class HttpsFileSchemeHandler.
 *
 * @FileSchemeHandler(
 *   id = "https",
 *   label = @Translation("Https file handler")
 * )
 */
class HttpsFileSchemeHandler extends PluginBase implements FileSchemeHandlerInterface {

  /**
   * {@inheritdoc}
   */
  public function addAttributes(CDFObject $object, FileInterface $file) {}

  /**
   * {@inheritdoc}
   */
  public function getFile(CDFObject $object) {}

}
