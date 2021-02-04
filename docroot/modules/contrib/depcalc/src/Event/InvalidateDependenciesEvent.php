<?php

namespace Drupal\depcalc\Event;

use Symfony\Component\EventDispatcher\Event;

class InvalidateDependenciesEvent extends Event {

  /**
   * The list of DependentEntityWrappers being invalidated.
   *
   * @var \Drupal\depcalc\DependentEntityWrapperInterface[]
   */
  protected $wrappers;

  /**
   * InvalidateDependenciesEvent constructor.
   *
   * @param \Drupal\depcalc\DependentEntityWrapperInterface[] $wrappers
   */
  public function __construct(array $wrappers) {
    $this->wrappers = $wrappers;
  }

  /**
   * @return \Drupal\depcalc\DependentEntityWrapperInterface[]
   */
  public function getWrappers(): array {
    return $this->wrappers;
  }

}
