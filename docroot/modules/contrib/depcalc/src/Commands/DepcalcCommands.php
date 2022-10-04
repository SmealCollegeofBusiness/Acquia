<?php

namespace Drupal\depcalc\Commands;

use Drupal\depcalc\Cache\DepcalcCacheBackend;
use Drush\Commands\DrushCommands;

/**
 * Drush commands for Depcalc.
 *
 * @package Drupal\depcalc\Commands
 */
class DepcalcCommands extends DrushCommands {

  /**
   * The Depcalc Cache backend.
   *
   * @var \Drupal\depcalc\Cache\DepcalcCacheBackend
   */
  protected $cache;

  /**
   * Public Constructor.
   *
   * @param \Drupal\depcalc\Cache\DepcalcCacheBackend $depcalc_cache
   *   The Depcalc Cache Backend.
   */
  public function __construct(DepcalcCacheBackend $depcalc_cache) {
    $this->cache = $depcalc_cache;
  }

  /**
   * Depcalc clear cache command.
   *
   * @usage depcalc:clear-cache
   *   This will clear depcalc cache.
   *
   * @command depcalc:clear-cache
   * @aliases dep-cc
   */
  public function clearDepcalcCache(): void {
    $this->cache->deleteAllPermanent();
    $this->logger()->success(dt('Cleared depcalc cache.'));
  }

}
