<?php

namespace Drupal\acquia_contenthub_subscriber;

use Acquia\ContentHubClient\CDFDocument;
use Drupal\depcalc\DependencyStack;

/**
 * Defines the fundamental behaviour of the CDF importer service.
 */
interface CdfImporterInterface {

  /**
   * Import a group of entities by their uuids from the ContentHub Service.
   *
   * The uuids passed are just the list of entities you absolutely want,
   * ContentHub will calculate all the missing entities and ensure they are
   * installed on your site.
   *
   * @param string ...$uuids @codingStandardsIgnoreLine
   *   The list of uuids to import.
   *
   * @return \Drupal\depcalc\DependencyStack
   *   The DependencyStack object.
   */
  public function importEntities(string ...$uuids);

  /**
   * Imports a list of entities from a CDFDocument object.
   *
   * @param \Acquia\ContentHubClient\CDFDocument $document
   *   The CDF document representing the entities to import.
   * @param \Drupal\depcalc\DependencyStack|null $stack
   *   Dependency stack.
   *
   * @return \Drupal\depcalc\DependencyStack
   *   The DependencyStack object.
   */
  public function importEntityCdfDocument(CDFDocument $document, ?DependencyStack $stack = NULL): DependencyStack;

  /**
   * Retrieves entities and dependencies by uuid and returns a CDFDocument.
   *
   * @param \Drupal\depcalc\DependencyStack $stack
   *   Dependency stack.
   * @param string ...$uuids
   *   The list of uuids to retrieve.
   *
   * @return \Acquia\ContentHubClient\CDFDocument
   *   The CDFDocument object.
   */
  public function getCdfDocument(DependencyStack $stack, string ...$uuids): CDFDocument;

  /**
   * Request to republish an entity via Webhook.
   *
   * @param array $entities_by_origin
   *   An array of dependency UUIDs.
   */
  public function requestToRepublishEntities(array $entities_by_origin): void;

  /**
   * Obtains the webhook from the registered webhooks, given origin.
   *
   * @param string $origin
   *   The origin of the site.
   *
   * @return string
   *   The webhook URL if it can be obtained otherwise empty string.
   */
  public function getWebhookUrlFromClientOrigin(string $origin): string;

}
