<?php

namespace Drupal\acquia_contenthub_publisher\EventSubscriber\EnqueueEligibility;

use Drupal\acquia_contenthub_publisher\ContentHubPublisherEvents;
use Drupal\acquia_contenthub_publisher\Event\ContentHubEntityEligibilityEvent;
use Drupal\Core\Config\Config;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Exclude selected entity types and/or bundles from export.
 *
 * @package Drupal\acquia_contenthub_publisher\EventSubscriber\EnqueueEligibility
 */
class EntityTypeOrBundleExclude implements EventSubscriberInterface {

  /**
   * Exclude settings object.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $config;

  /**
   * EntityTypeOrBundleExclude constructor.
   *
   * @param \Drupal\Core\Config\Config $config
   *   The config object with the exclude settings.
   */
  public function __construct(Config $config) {
    $this->config = $config;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[ContentHubPublisherEvents::ENQUEUE_CANDIDATE_ENTITY][] =
      ['onEnqueueCandidateEntity', 400];
    return $events;
  }

  /**
   * Prevents export for selected entity types and/or bundles.
   *
   * @param \Drupal\acquia_contenthub_publisher\Event\ContentHubEntityEligibilityEvent $event
   *   The event to determine entity eligibility.
   *
   * @throws \Exception
   */
  public function onEnqueueCandidateEntity(ContentHubEntityEligibilityEvent $event) {
    $entity = $event->getEntity();
    $entity_type_id = $entity->getEntityTypeId();
    $bundle_id = $entity->bundle();

    $exclude_types = $this->config->get('exclude_entity_types') ?? [];
    $exclude_bundles = $this->config->get('exclude_bundles') ?? [];

    if (
      in_array($entity_type_id, $exclude_types) ||
      in_array(self::formatTypeBundle($entity_type_id, $bundle_id), $exclude_bundles)
    ) {
      $event->setEligibility(FALSE);
      $event->stopPropagation();
    }
  }

  /**
   * Formats the type and bundle combination.
   *
   * @param string $type
   *   The entity type.
   * @param string $bundle
   *   The bundle.
   *
   * @return string
   *   The formatted combination.
   */
  public static function formatTypeBundle(string $type, string $bundle): string {
    return $type . ':' . $bundle;
  }

}
