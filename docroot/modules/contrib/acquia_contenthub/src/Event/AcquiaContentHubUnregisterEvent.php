<?php

namespace Drupal\acquia_contenthub\Event;

use Symfony\Component\EventDispatcher\Event;

/**
 * Event dispatched on webhook deletion.
 *
 * @package Drupal\acquia_contenthub\Event
 */
class AcquiaContentHubUnregisterEvent extends Event {

  /**
   * Uuid of the webhook.
   *
   * @var string
   *   Uuid of webhook.
   */
  protected $webhookUuid;

  /**
   * Client/origin UUID.
   *
   * @var string
   *   Client/origin UUID.
   */
  protected $originUuid;

  /**
   * Client name.
   *
   * @var string
   *   Client name.
   */
  protected $clientName;

  /**
   * Uuid of the default filter.
   *
   * @var string
   *   Uuid of default filter.
   */
  protected $defaultFilter;

  /**
   * Uuid's of orphaned filters.
   *
   * @var array
   *   Array containing the orphaned filter uuids.
   */
  protected $orphanedFilters;

  /**
   * Amount of orphaned entities.
   *
   * @var int
   *   Amount of orphaned entites.
   */
  protected $orphanedEntitiesCount;

  /**
   * Array of orphaned entities.
   *
   * @var array
   *   Array of orphaned entities.
   */
  protected $orphanedEntities;

  /**
   * Bool to decide what to delete.
   *
   * @var bool
   *   TRUE if we only want to delete the webhook.
   */
  protected $deleteWebhookOnly;

  /**
   * AcquiaContentHubUnregisterEvent constructor.
   *
   * @param string $webhook_uuid
   *   Webhook uuid.
   * @param string $client_uuid
   *   Client uuid. SHOULD BE ONLY PASSED IF WE DO AN OPERATION ON A DIFFERENT
   *   SITE, NOT ON THE CURRENT. Otherwise we get it from settings.
   * @param bool $delete_webhook_only
   *   Pass TRUE if we delete only webhook.
   */
  public function __construct(string $webhook_uuid, string $client_uuid = '', bool $delete_webhook_only = FALSE) {
    $this->webhookUuid = $webhook_uuid;
    $this->originUuid = $client_uuid;
    $this->deleteWebhookOnly = $delete_webhook_only;
  }

  /**
   * Returns webhook UUID.
   *
   * @return string
   *   Webhook UUID:
   */
  public function getWebhookUuid(): string {
    return $this->webhookUuid;
  }

  /**
   * Returns info about delete process.
   *
   * @return bool
   *   TRUE if we delete only webhook and not client.
   */
  public function isDeleteWebhookOnly(): bool {
    return $this->deleteWebhookOnly;
  }

  /**
   * Sets default filter.
   *
   * @param string $uuid
   *   Default filter UUID.
   */
  public function setDefaultFilter(string $uuid): void {
    $this->defaultFilter = $uuid;
  }

  /**
   * Returns origin UUID.
   *
   * @return string
   *   Client/origin UUID.
   */
  public function getOriginUuid(): string {
    return $this->originUuid;
  }

  /**
   * Sets client name.
   *
   * @param string $client_name
   *   Client name.
   */
  public function setClientName(string $client_name): void {
    $this->clientName = $client_name;
  }

  /**
   * Returns client name.
   *
   * @return string
   *   Client name.
   */
  public function getClientName(): string {
    return $this->clientName;
  }

  /**
   * Returns default filter UUID.
   *
   * @return string|null
   *   Default filter UUID.
   */
  public function getDefaultFilter(): ?string {
    return $this->defaultFilter;
  }

  /**
   * Sets orphaned filter UUIDs.
   *
   * @param array $filters
   *   Array containing orphaned filters.
   */
  public function setOrphanedFilters(array $filters): void {
    $this->orphanedFilters = $filters;
  }

  /**
   * Returns orphaned filters UUIDs.
   *
   * @return array|null
   *   Array containing orphaned filters.
   */
  public function getOrphanedFilters(): ?array {
    return $this->orphanedFilters;
  }

  /**
   * Sets the amount of orphaned entities.
   *
   * @param int $amount
   *   Amount of orphaned entities.
   */
  public function setOrphanedEntitiesAmount(int $amount): void {
    $this->orphanedEntitiesCount = $amount;
  }

  /**
   * Returns the amount of orphaned entities.
   *
   * @return int
   *   Amount of orphaned entities.
   */
  public function getOrphanedEntitiesAmount(): int {
    return $this->orphanedEntitiesCount;
  }

  /**
   * Sets the array of orphaned entities.
   *
   * @param array $entities
   *   Array of orphaned entities.
   */
  public function setOrphanedEntities(array $entities): void {
    $this->orphanedEntities = $entities;
  }

  /**
   * Returns the orphaned entities.
   *
   * @return array
   *   Array of orphaned entities.
   */
  public function getOrphanedEntities(): array {
    return $this->orphanedEntities;
  }

}
