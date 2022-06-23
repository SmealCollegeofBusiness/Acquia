<?php

namespace Drupal\acquia_contenthub_publisher\Event;

use Drupal\Core\Entity\EntityInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Event fired for eligibility of an entity to POST to ContentHub.
 *
 * Subscribers to this event should manipulate $this->setEligibility() to
 * prevent entities from being considered eligible for ContentHub POSTing.
 * Entities are considered eligible by default.
 *
 * @see \Drupal\acquia_contenthub_publisher\ContentHubPublisherEvents
 */
class ContentHubEntityEligibilityEvent extends Event {

  /**
   * The entity being evaluated for eligibility.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected $entity;

  /**
   * The operation being performed.
   *
   * Should be a value of "insert" or "update".
   *
   * @var string
   */
  protected $operation;

  /**
   * Whether the entity is eligible for the ContentHub queue.
   *
   * @var bool
   */
  protected $eligibility = TRUE;

  /**
   * The reason of ineligibility.
   *
   * @var string
   */
  protected $reason = 'Not set.';

  /**
   * Whether the entity should go through a full dependency calculation.
   *
   * @var bool
   */
  protected $calculateDependencies = TRUE;

  /**
   * ContentHubEntityEligibilityEvent constructor.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity being evaluated for eligibility.
   * @param string $op
   *   Whether this is being inserted or updated.
   */
  public function __construct(EntityInterface $entity, $op) {
    $this->entity = $entity;
    $this->operation = $op;
  }

  /**
   * Sets the reason of ineligibility.
   *
   * @param string $reason
   *   The reason of ineligibility.
   */
  public function setReason(string $reason): void {
    $this->reason = $reason;
  }

  /**
   * Returns the reason of ineligibility.
   *
   * @return string
   *   Ineligibility reason.
   */
  public function getReason(): string {
    return $this->reason;
  }

  /**
   * The entity being evaluated for eligibility.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The entity that is being evaluated for eligibility.
   */
  public function getEntity() {
    return $this->entity;
  }

  /**
   * The operation being performed.
   *
   * @return string
   *   The operation identifier that's being performed.
   */
  public function getOperation() {
    return $this->operation;
  }

  /**
   * Whether the entity is eligible for the ContentHub queue.
   *
   * @return bool
   *   TRUE if eligible; FALSE otherwise.
   */
  public function getEligibility(): bool {
    return $this->eligibility;
  }

  /**
   * Set the eligibility of this entity for the ContentHub queue.
   *
   * @param bool $eligible
   *   TRUE if eligible; FALSE otherwise.
   *
   * @throws \Exception
   */
  public function setEligibility($eligible) {
    if (!is_bool($eligible)) {
      throw new \Exception("Eligibility must be a boolean value.");
    }
    $this->eligibility = $eligible;
  }

  /**
   * Whether the entity should go through a full dependency calculation.
   *
   * @return bool
   *   TRUE if full dependency calculation should be performed; FALSE otherwise.
   */
  public function getCalculateDependencies(): bool {
    return $this->calculateDependencies;
  }

  /**
   * Set whether the entity should go through a full dependency calculation.
   *
   * @param bool $calculate_dependencies
   *   TRUE if full dependency calculation should be performed; FALSE otherwise.
   *
   * @throws \Exception
   */
  public function setCalculateDependencies($calculate_dependencies) {
    if (!is_bool($calculate_dependencies)) {
      throw new \Exception("calculateDependencies must be a boolean value.");
    }
    $this->calculateDependencies = $calculate_dependencies;
  }

}
