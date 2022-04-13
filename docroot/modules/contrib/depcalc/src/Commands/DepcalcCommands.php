<?php

namespace Drupal\depcalc\Commands;

use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\depcalc\Cache\DepcalcCacheBackend;
use Drush\Commands\DrushCommands;

/**
 * Drush commands for Depcalc.
 *
 * @package Drupal\depcalc\Commands
 */
class DepcalcCommands extends DrushCommands {

  /**
   * Logger Service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * The Depcalc Cache backend.
   *
   * @var \Drupal\depcalc\Cache\DepcalcCacheBackend
   */
  protected $cache;

  /**
   * Public Constructor.
   *
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   The Depcalc logger channel.
   * @param \Drupal\depcalc\Cache\DepcalcCacheBackend $depcalc_cache
   *   The Depcalc Cache Backend.
   */
  public function __construct(LoggerChannelInterface $logger, DepcalcCacheBackend $depcalc_cache) {
    $this->logger = $logger;
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
