<?php

namespace Drupal\acquia_contenthub_publisher;

/**
 * Defines events for the acquia_contenthub_publisher module.
 *
 * @see \Drupal\acquia_contenthub_publisher\Event\SerializeCdfEntityFieldEvent
 */
final class ContentHubPublisherEvents {

  /**
   * Event name fired for eligibility of an entity to POST to ContentHub.
   *
   * This event determines the eligibility of an entity before enqueuing it to
   * POST to Content hub. Event subscribers receive a
   * \Drupal\acquia_contenthub_publisher\Event\ContentHubEntityEligibilityEvent
   * instance. A simple TRUE/FALSE on eligibility check is expected for each
   * event.
   */
  const ENQUEUE_CANDIDATE_ENTITY = 'enqueue_candidate_entity';

  /**
   * Event name fired if "staled" entities found during the cron operation.
   *
   * A "stale" entity is an entity that was not confirmed by the service within
   * the specified period of time. Please refer to the "Period threshold for
   * stale items" setting on "Acquia ContentHub" > "Export" administration page
   * to specify the threshold period (or disable it).
   * Event subscribers receive a
   * \Drupal\acquia_contenthub_publisher\EventNotConfirmedEntitiesFoundEvent
   * instance.
   */
  const NOT_CONFIRMED_ENTITIES_FOUND = 'not_confirmed_entities_found';

}
