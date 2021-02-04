<?php

namespace Drupal\depcalc\Cache;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Cache\DatabaseBackend;
use Drupal\depcalc\DependencyCalculatorEvents;
use Drupal\depcalc\Event\InvalidateDependenciesEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Class DepcalcCacheBackend
 *
 * Provides a depcalc specific cache backend that can invalidate depcalc cache
 * tags in the database table. This class also works as an adapter, so if
 * another cache backend is passed to it, it will proxy calls, including tag
 * invalidation, to that backend.
 *
 * @package Drupal\depcalc\Cache
 */
class DepcalcCacheBackend implements CacheBackendInterface, CacheTagsInvalidatorInterface {

  /**
   * The cache backend to decorate.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $backend;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $dispatcher;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * The bin name.
   *
   * @var string
   */
  protected $bin;

  /**
   * DepcalcCacheBackend constructor.
   *
   * @param \Drupal\Core\Cache\CacheBackendInterface $backend
   *   The CacheBackendInterface object to decorate.
   *
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $dispatcher
   *   The event dispatcher.
   *
   * @throws \ReflectionException
   */
  public function __construct(CacheBackendInterface $backend, EventDispatcherInterface $dispatcher) {
    $this->backend = $backend;
    $this->dispatcher = $dispatcher;
    if ($backend instanceof DatabaseBackend) {
      $this->getProperties($backend);
    }
  }

  /**
   * Get the DatabaseBackend instance's database connection.
   *
   * We don't want to get our own database connection or cache bin, so we just
   * reflect it out of the object we're decorating.
   *
   * @param \Drupal\Core\Cache\DatabaseBackend $backend
   *   The database backend object from which to extract necessary properties.
   *
   * @throws \ReflectionException
   */
  private function getProperties(DatabaseBackend $backend) : void {
    $r = new \ReflectionObject($backend);

    $p = $r->getProperty('connection');
    $p->setAccessible(TRUE);
    $this->connection = $p->getValue($backend);

    $p = $r->getProperty('bin');
    $p->setAccessible(TRUE);
    $this->bin = $p->getValue($backend);
  }

  /**
   * {@inheritdoc}
   */
  public function invalidateMultiple(array $cids) {
    $cache_objects = $this->getMultiple($cids);
    $this->backend->invalidateMultiple($cids);
    if (!$cache_objects) {
      return;
    }
    /** @var \Drupal\depcalc\DependentEntityWrapperInterface[] $wrappers */
    $wrappers = array_map(function($cache) {return $cache->data;}, $cache_objects);
    $event = new InvalidateDependenciesEvent($wrappers);
    $this->dispatcher->dispatch(DependencyCalculatorEvents::INVALIDATE_DEPENDENCIES, $event);
  }

  /**
   * {@inheritdoc}
   */
  public function get($cid, $allow_invalid = FALSE) {
    return $this->backend->get($cid, $allow_invalid);
  }

  /**
   * {@inheritdoc}
   */
  public function getMultiple(&$cids, $allow_invalid = FALSE) {
    return $this->backend->getMultiple($cids, $allow_invalid);
  }

  /**
   * {@inheritdoc}
   */
  public function set($cid, $data, $expire = Cache::PERMANENT, array $tags = []) {
    $this->backend->set($cid, $data, $expire, $tags);
  }

  /**
   * {@inheritdoc}
   */
  public function setMultiple(array $items) {
    $this->backend->setMultiple($items);
  }

  /**
   * {@inheritdoc}
   */
  public function delete($cid) {
    // Invalidate first to handle the dependencies.
    $this->invalidate($cid);
    $this->backend->delete($cid);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteMultiple(array $cids) {
    $this->backend->deleteMultiple($cids);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteAll() {
    $this->backend->deleteAll();
  }

  /**
   * {@inheritdoc}
   */
  public function invalidate($cid) {
    $this->invalidateMultiple([$cid]);
  }

  /**
   * {@inheritdoc}
   */
  public function invalidateAll() {
    $this->backend->invalidateAll();
  }

  /**
   * {@inheritdoc}
   */
  public function garbageCollection() {
    $this->backend->garbageCollection();
  }

  /**
   * {@inheritdoc}
   */
  public function removeBin() {
    $this->backend->removeBin();
  }

  /**
   * {@inheritdoc}
   */
  public function invalidateTags(array $tags) {
    if ($this->backend instanceof DatabaseBackend) {
      // On module install, this will get called, so let's check that the table
      // exists before doing anything.
      if (!$this->connection->schema()->tableExists($this->bin)) {
        return;
      }
      foreach ($tags as $tag) {
        $result = $this->connection->select($this->bin, 'bin')
          ->fields('bin', ['cid'])
          ->condition('tags', "%{$this->connection->escapeLike($tag)}%", 'LIKE')
          ->execute();
        if (!$result) {
          continue;
        }
        $this->invalidateMultiple($result->fetchCol());
      }
    }
    elseif ($this->backend instanceof CacheTagsInvalidatorInterface) {
      $this->backend->invalidateTags($tags);
    }
  }

}
