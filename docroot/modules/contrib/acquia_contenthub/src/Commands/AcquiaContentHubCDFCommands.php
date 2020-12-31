<?php

namespace Drupal\acquia_contenthub\Commands;

use Acquia\ContentHubClient\CDF\CDFObject;
use Acquia\ContentHubClient\CDFDocument;
use Drupal\Component\Serialization\Json;
use Drupal\Component\Uuid\Uuid;
use Drupal\Core\Serialization\Yaml;
use Drush\Commands\DrushCommands;

/**
 * Class AcquiaContentHubCommands.
 *
 * @package Drupal\acquia_contenthub\Commands
 */
class AcquiaContentHubCDFCommands extends DrushCommands {

  /**
   * AcquiaContentHubCDFCommands constructor.
   */
  public function __construct() {
  }

  /**
   * Generates a CDF Document from a manifest file.
   *
   * @param string $manifest
   *   The location of the manifest file.
   *
   * @command acquia:contenthub-export-local-cdf
   * @aliases ach-elc
   *
   * @return false|string
   *   The json output if successful or false.
   *
   * @throws \Exception
   */
  public function exportCdf($manifest) {
    if (!file_exists($manifest)) {
      throw new \Exception("The provided manifest file does not exist in the specified location.");
    }
    $manifest = Yaml::decode(file_get_contents($manifest));
    $entities = [];
    $entityTypeManager = \Drupal::entityTypeManager();
    /** @var \Drupal\Core\Entity\EntityRepositoryInterface $repository */
    $repository = \Drupal::service('entity.repository');
    foreach ($manifest['entities'] as $entity) {
      [$entity_type_id, $entity_id] = explode(":", $entity);
      if (!Uuid::isValid($entity_id)) {
        $entities[] = $entityTypeManager->getStorage($entity_type_id)->load($entity_id);
        continue;
      }
      $entities[] = $repository->loadEntityByUuid($entity_type_id, $entity_id);
    }
    if (!$entities) {
      throw new \Exception("No entities loaded from the manifest.");
    }
    /** @var \Drupal\acquia_contenthub\ContentHubCommonActions $common */
    $common = \Drupal::service('acquia_contenthub_common_actions');
    return $common->getLocalCdfDocument(...$entities)->toString();
  }

  /**
   * Imports entities from a CDF Document.
   *
   * @param string $location
   *   The location of the cdf file.
   *
   * @command acquia:contenthub-import-local-cdf
   * @aliases ach-ilc
   *
   * @throws \Exception
   */
  public function importCdf($location): void {
    if (!file_exists($location)) {
      throw new \Exception("The cdf to import was not found in the specified location.");
    }
    $json = file_get_contents($location);
    $data = Json::decode($json);
    $document_parts = [];
    foreach ($data['entities'] as $entity) {
      $document_parts[] = CDFObject::fromArray($entity);
    }
    $cdf_document = new CDFDocument(...$document_parts);

    /** @var \Drupal\acquia_contenthub\ContentHubCommonActions $common */
    $common = \Drupal::service('acquia_contenthub_common_actions');
    $stack = $common->importEntityCdfDocument($cdf_document);
    $this->output->writeln(dt("Imported @items from @location.", [
      '@items' => count($stack->getDependencies()),
      '@location' => $location,
    ]));
  }

}
