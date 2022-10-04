<?php

namespace Drupal\Tests\acquia_contenthub\Kernel\Traits;

use Acquia\ContentHubClient\CDF\CDFObject;
use Acquia\ContentHubClient\CDFDocument;
use Drupal\Component\Serialization\Json;
use Drupal\Tests\acquia_contenthub\Kernel\Stubs\DrupalVersion;

/**
 * Trait containing helper methods to create a CDF document.
 */
trait CdfDocumentCreatorTrait {

  use DrupalVersion;

  /**
   * Creates CDF document from fixture.
   *
   * @param string $fixture_filename
   *   Fixture file name.
   * @param string $module_name
   *   Module name.
   *
   * @return \Acquia\ContentHubClient\CDFDocument
   *   CDF document.
   *
   * @throws \ReflectionException
   */
  protected function createCdfDocumentFromFixtureFile(string $fixture_filename, string $module_name = 'acquia_contenthub'): CDFDocument {
    $data = $this->getCdfData($fixture_filename, $module_name);
    $document_parts = [];
    foreach ($data['entities'] as $entity) {
      $document_parts[] = $this->populateCdfObject($entity);
    }

    return new CDFDocument(...$document_parts);
  }

  /**
   * Loads a fixture cdf.
   *
   * @param string $fixture_filename
   *   Fixture filename.
   * @param string $module_name
   *   Module name.
   *
   * @return mixed
   *   Decoded data.
   */
  protected function getCdfData(string $fixture_filename, string $module_name = 'acquia_contenthub') {
    $version_directory = $this->getDrupalVersion();
    $path = \Drupal::service('module_handler')->getModule($module_name)->getPath();
    $path_to_fixture = sprintf("%s/tests/fixtures/import/$version_directory/%s",
      $path,
      $fixture_filename
    );
    $json = file_get_contents($path_to_fixture);
    return Json::decode($json);
  }

  /**
   * Populates CDF object from array.
   *
   * @param array $entity
   *   Entity.
   *
   * @return \Acquia\ContentHubClient\CDF\CDFObject
   *   Populated CDF object.
   *
   * @throws \Exception
   * @throws \ReflectionException
   *
   * @see \Acquia\ContentHubClient\ContentHubClient::getEntities()
   */
  protected function populateCdfObject(array $entity): CDFObject {
    $object = new CDFObject($entity['type'], $entity['uuid'], $entity['created'], $entity['modified'], $entity['origin'], $entity['metadata']);

    foreach ($entity['attributes'] as $attribute_name => $values) {
      // Refactor ClientHub.php: get rid of duplicated code blocks.
      if (!$attribute = $object->getAttribute($attribute_name)) {
        $class = !empty($object->getMetadata()['attributes'][$attribute_name]) ? $object->getMetadata()['attributes'][$attribute_name]['class'] : FALSE;
        if ($class && class_exists($class)) {
          $object->addAttribute($attribute_name, $values['type'], NULL, 'und', $class);
        }
        else {
          $object->addAttribute($attribute_name, $values['type'], NULL);
        }
        $attribute = $object->getAttribute($attribute_name);
      }

      $value_property = (new \ReflectionClass($attribute))->getProperty('value');
      $value_property->setAccessible(TRUE);
      $value_property->setValue($attribute, $values['value']);
    }

    return $object;
  }

}
