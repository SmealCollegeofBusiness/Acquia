<?php

namespace Drupal\acquia_contenthub_subscriber\Commands;

use Acquia\ContentHubClient\CDF\CDFObject;
use Acquia\ContentHubClient\CDFDocument;
use Drupal\acquia_contenthub_subscriber\CdfImporter;
use Drupal\Component\Serialization\Json;
use Drush\Commands\DrushCommands;

/**
 * Provides CDF import related commands.
 *
 * @package Drupal\acquia_contenthub_subscriber\Commands
 */
class AcquiaContentHubSubscriberCdfImportCommands extends DrushCommands {

  /**
   * CDF importer service.
   *
   * @var \Drupal\acquia_contenthub_subscriber\CdfImporter
   */
  protected $importer;

  /**
   * AcquiaContentHubSubscriberCdfImportCommands constructor.
   *
   * @param \Drupal\acquia_contenthub_subscriber\CdfImporter $cdf_importer
   *   CDF importer service.
   */
  public function __construct(CdfImporter $cdf_importer) {
    $this->importer = $cdf_importer;
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

    $stack = $this->importer->importEntityCdfDocument($cdf_document);
    $this->output->writeln(dt("Imported @items from @location.", [
      '@items' => count($stack->getDependencies()),
      '@location' => $location,
    ]));
  }

}
