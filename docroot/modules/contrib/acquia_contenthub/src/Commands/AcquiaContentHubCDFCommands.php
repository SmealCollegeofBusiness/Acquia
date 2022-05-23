<?php

namespace Drupal\acquia_contenthub\Commands;

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
   * Generates a CDF Document from a manifest file.
   *
   * @todo move this command to publisher in export optimization/refactor.
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

}
