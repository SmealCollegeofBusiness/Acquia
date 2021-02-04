<?php

namespace Drupal\depcalc\Cache;

use Drupal\Core\Cache\CacheFactoryInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class DepcalcCacheFactory implements CacheFactoryInterface{

  /**
   * The core cache factory.
   *
   * @var \Drupal\Core\Cache\CacheFactoryInterface
   */
  protected $factory;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $dispatcher;

  /**
   * DepcalcCacheFactory constructor.
   *
   * @param \Drupal\Core\Cache\CacheFactoryInterface $factory
   *   The core cache factory.
   */
  public function __construct(CacheFactoryInterface $factory, EventDispatcherInterface $dispatcher) {
    $this->factory = $factory;
    $this->dispatcher = $dispatcher;
  }

  /**
   * {@inheritdoc}
   */
  public function get($bin) {
    $backend = $this->factory->get('depcalc');
    return new DepcalcCacheBackend($backend, $this->dispatcher);
  }

}
