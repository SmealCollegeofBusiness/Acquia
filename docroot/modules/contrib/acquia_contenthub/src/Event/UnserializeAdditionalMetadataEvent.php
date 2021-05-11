<?php

namespace Drupal\acquia_contenthub\Event;

use Acquia\ContentHubClient\CDF\CDFObject;
use Drupal\Core\Entity\ContentEntityInterface;
use Symfony\Component\EventDispatcher\Event;

/**
 * The event dispatched to unserialize additional metadata for a Content Entity.
 *
 * This event allows to unserialize additional attached metadata
 * for a content entity which is not
 * handled by fields. E.g. Webform Elements.
 */
class UnserializeAdditionalMetadataEvent extends Event {

  /**
   * The entity being serialized.
   *
   * @var \Drupal\Core\Entity\ContentEntityInterface
   */
  protected $entity;

  /**
   * The CDF Object generated for this entity.
   *
   * @var \Acquia\ContentHubClient\CDF\CDFObject
   */
  protected $cdf;

  /**
   * CreateCdfEntityEvent constructor.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity being serialized.
   * @param \Acquia\ContentHubClient\CDF\CDFObject $cdf
   *   The CDF object generated for this entity.
   */
  public function __construct(ContentEntityInterface $entity, CDFObject $cdf) {
    $this->entity = $entity;
    $this->cdf = $cdf;
  }

  /**
   * Get the current CDF object event holds.
   *
   * @return \Acquia\ContentHubClient\CDF\CDFObject
   *   The CDF object.
   */
  public function getCdf(): CDFObject {
    return $this->cdf;
  }

  /**
   * Set the CDF object event holds.
   *
   * @param \Acquia\ContentHubClient\CDF\CDFObject $cdf
   *   The CDF Object.
   */
  public function setCdf(CDFObject $cdf) {
    $this->cdf = $cdf;
  }

  /**
   * Returns entity, event currently holds.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   Content Entity object.
   */
  public function getEntity(): ContentEntityInterface {
    return $this->entity;
  }

  /**
   * Sets the current entity event holds.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   Content entity object.
   */
  public function setEntity(ContentEntityInterface $entity) {
    $this->entity = $entity;
  }

}
