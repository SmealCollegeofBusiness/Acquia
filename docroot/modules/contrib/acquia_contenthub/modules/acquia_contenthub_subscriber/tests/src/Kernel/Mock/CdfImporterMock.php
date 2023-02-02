<?php

namespace Drupal\Tests\acquia_contenthub_subscriber\Kernel\Mock;

use Acquia\ContentHubClient\CDFDocument;
use Drupal\acquia_contenthub_subscriber\CdfImporterInterface;
use Drupal\depcalc\DependencyStack;

/**
 * Replaces CdfImporter service.
 */
class CdfImporterMock implements CdfImporterInterface {

  /**
   * The entities that are meant to be republished.
   *
   * @var array
   */
  public $entitiesToRepublish = [];

  /**
   * {@inheritdoc}
   */
  public function importEntities(string ...$uuids) {}

  /**
   * {@inheritdoc}
   */
  public function importEntityCdfDocument(CDFDocument $document, ?DependencyStack $stack = NULL): DependencyStack {
    return new DependencyStack();
  }

  /**
   * {@inheritdoc}
   */
  public function getCdfDocument(DependencyStack $stack, string ...$uuids): CDFDocument {
    return new CDFDocument();
  }

  /**
   * {@inheritdoc}
   */
  public function requestToRepublishEntities(array $entities_by_origin): void {
    $this->entitiesToRepublish = $entities_by_origin;
  }

  /**
   * {@inheritdoc}
   */
  public function getWebhookUrlFromClientOrigin(string $origin): string {
    return '';
  }

}
