<?php

namespace Drupal\acquia_contenthub_translations\EntityHandler;

use Drupal\Core\Config\Config;

/**
 * Provides an interface to access handler information regarding entities.
 */
class HandlerRegistry {

  /**
   * Unspecified handler.
   */
  public const UNSPECIFIED = 'unspecified';

  /**
   * Path to unspecified list in acquia_contenthub_translation config.
   */
  public const UNSPECIFIED_CONFIG_KEY = 'nt_entity_registry.unspecified';

  /**
   * Path to handler mapping in acquia_contenthub_translation config.
   */
  public const ENTITY_HANDLERS_CONFIG_KEY = 'nt_entity_registry.handler_mapping';

  /**
   * Default settings for specific non-translatable files.
   *
   * @var string[]
   */
  protected $default = [
    // File entities are flexible in terms of their language. They are created
    // in the site's default language.
    'file' => 'flexible',
    // The redirect entity applies for every language if langcode is und.
    // It can be safely removed if the langcode is specified, because the rest
    // of the translations were already removed, the rest of the version are
    // residual.
    'redirect' => 'removable',
    // Path aliases are similar to redirect entities, in a way that on every
    // translation a new is being created.
    'path_alias' => 'removable',
  ];

  /**
   * The acquia_contenthub_translations config.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $config;

  /**
   * Constructs a new object.
   *
   * @param \Drupal\Core\Config\Config $config
   *   The acquia_contenthub_translations config.
   */
  public function __construct(Config $config) {
    $this->config = $config;
  }

  /**
   * Returns the handler id for an entity.
   *
   * @param string $entity_type
   *   The entity type to check.
   *
   * @return string
   *   The handler id.
   */
  public function getHandlerIdFor(string $entity_type): string {
    $collection = array_merge($this->default, $this->getUnspecified(), $this->getHandlerMapping());
    return $collection[$entity_type] ?? static::UNSPECIFIED;
  }

  /**
   * Returns unspecified entities.
   *
   * @return array
   *   The unspecified registry.
   */
  public function getUnspecified(): array {
    return $this->config->get(static::UNSPECIFIED_CONFIG_KEY) ?? [];
  }

  /**
   * Adds a new element to the unspecified list.
   *
   * @param string $entity_type
   *   The entity type to register.
   */
  public function addToUnspecified(string $entity_type): void {
    $current = $this->getUnspecified();
    $current[$entity_type] = static::UNSPECIFIED;
    $this->config->set(static::UNSPECIFIED_CONFIG_KEY, $current)->save();
  }

  /**
   * Returns if the given entity is unspecified.
   *
   * @param string $entity_type
   *   The entity type id.
   *
   * @return bool
   *   TRUE if the entity is not specififed.
   */
  public function isUnspecified(string $entity_type): bool {
    return isset($this->getUnspecified()[$entity_type]);
  }

  /**
   * Adds entities to the handler mapping list.
   *
   * @param string $entity_type
   *   The entity type to add.
   * @param string $handler_id
   *   The handler id to assign to the entity.
   */
  public function addEntityToRegistry(string $entity_type, string $handler_id): void {
    $current = $this->getHandlerMapping();
    $current = array_merge($current, [$entity_type => $handler_id]);
    $this->config->set(static::ENTITY_HANDLERS_CONFIG_KEY, $current)->save();
  }

  /**
   * Returns the current entity mapping.
   *
   * @return array
   *   The handler mapping.
   */
  public function getHandlerMapping(): array {
    return $this->config->get(static::ENTITY_HANDLERS_CONFIG_KEY) ?? [];
  }

}
