<?php

namespace Drupal\acquia_contenthub\Event;

use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Symfony\Component\EventDispatcher\Event;

/**
 * Event for configuration data.
 */
class ConfigDataEvent extends Event {

  /**
   * The data array that will be serialized for export.
   *
   * @var array
   */
  protected $data = [];

  /**
   * The config entity whose data is being serialized.
   *
   * @var \Drupal\Core\Config\Entity\ConfigEntityInterface
   */
  protected $entity;

  /**
   * ConfigDataEvent constructor.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $entity
   *   The config entity being serialized.
   */
  public function __construct(ConfigEntityInterface $entity) {
    $this->entity = $entity;
  }

  /**
   * The current data for the config entity.
   *
   * @return array
   *   Data for this configuration.
   */
  public function getData(): array {
    return $this->data;
  }

  /**
   * The entity being serialized.
   *
   * @return \Drupal\Core\Config\Entity\ConfigEntityInterface
   *   The configuration entity.
   */
  public function getEntity(): ConfigEntityInterface {
    return $this->entity;
  }

  /**
   * Set the data for serializing the entity.
   *
   * Event subscribers to this event should be mindful to use the
   * NestedArray::mergeDeepArray() method to merge data together and not
   * overwrite other event subscriber's data.
   *
   * @param array $data
   *   Data to set.
   */
  public function setData(array $data): void {
    $this->data = $data;
  }

}
