<?php

namespace Drupal\acquia_contenthub\Event;

use Acquia\ContentHubClient\CDF\CDFObject;
use Drupal\Core\Entity\EntityInterface;
use Drupal\depcalc\DependentEntityWrapperInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * The event which adds custom CDF attributes to the CDF objects.
 */
class CdfAttributesEvent extends Event {

  /**
   * The CDF Object for which to create attributes.
   *
   * @var \Acquia\ContentHubClient\CDF\CDFObject
   */
  protected $cdf;

  /**
   * The entity which corresponds to the CDF object.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected $entity;

  /**
   * An entity wrapper class for finding and tracking dependencies of an entity.
   *
   * @var \Drupal\depcalc\DependentEntityWrapperInterface
   */
  protected $wrapper;

  /**
   * CdfAttributesEvent constructor.
   *
   * @param \Acquia\ContentHubClient\CDF\CDFObject $cdf
   *   The CDF object.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   * @param \Drupal\depcalc\DependentEntityWrapperInterface $wrapper
   *   Dependent entity wrapper.
   */
  public function __construct(CDFObject $cdf, EntityInterface $entity, DependentEntityWrapperInterface $wrapper) {
    $this->cdf = $cdf;
    $this->entity = $entity;
    $this->wrapper = $wrapper;
  }

  /**
   * Get the CDF being created.
   *
   * @return \Acquia\ContentHubClient\CDF\CDFObject
   *   The CDF object.
   */
  public function getCdf(): CDFObject {
    return $this->cdf;
  }

  /**
   * Get the entity being processed.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The entity being processed.
   */
  public function getEntity(): EntityInterface {
    return $this->entity;
  }

  /**
   * Get the Dependent entity wrapper.
   *
   * @return \Drupal\depcalc\DependentEntityWrapperInterface
   *   Dependent entity wrapper.
   */
  public function getWrapper(): DependentEntityWrapperInterface {
    return $this->wrapper;
  }

}
