<?php

namespace Drupal\Tests\acquia_contenthub\Kernel\Stubs;

use Acquia\ContentHubClient\CDF\CDFObject;
use Drupal\acquia_contenthub\Plugin\FileSchemeHandler\PublicFileSchemeHandler as OriginalPublicFileSchemeHandler;
use Drupal\Core\File\Exception\FileException;
use Drupal\Core\File\Exception\FileWriteException;
use Drupal\Core\File\FileSystemInterface;

/**
 * Class PublicFileSchemeHandler.
 *
 * @package Drupal\Tests\acquia_contenthub\Kernel\Stubs
 */
class PublicFileSchemeHandler extends OriginalPublicFileSchemeHandler {

  /**
   * {@inheritdoc}
   */
  public function getFile(CDFObject $object) {
    if ($object->getAttribute('file_location') && $object->getAttribute('file_uri')) {
      $url = $object->getAttribute('file_location')->getValue()['und'];

      $url = str_replace('module::', drupal_get_path('module', 'acquia_contenthub'), $url);

      $contents = file_get_contents($url);
      $uri = $object->getAttribute('file_uri')->getValue()['und'];
      try {
        return \Drupal::service('file_system')->saveData($contents, $uri, FileSystemInterface::EXISTS_REPLACE);
      }
      catch (FileWriteException $e) {
        \Drupal::messenger()->addError(t('The file could not be created.'));
        return FALSE;
      }
      catch (FileException $e) {
        return FALSE;
      }
    }
    return FALSE;
  }

}
