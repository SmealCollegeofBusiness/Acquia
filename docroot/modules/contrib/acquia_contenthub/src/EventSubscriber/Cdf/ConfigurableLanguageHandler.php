<?php

namespace Drupal\acquia_contenthub\EventSubscriber\Cdf;

use Drupal\acquia_contenthub\AcquiaContentHubEvents;
use Drupal\acquia_contenthub\Event\CreateCdfEntityEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Handler for configurable languages.
 *
 * @package Drupal\acquia_contenthub\EventSubscriber\Cdf
 */
class ConfigurableLanguageHandler implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[AcquiaContentHubEvents::CREATE_CDF_OBJECT][] = ['onCreateCdf', 50];
    return $events;
  }

  /**
   * Adds the langcode metadata to configurable_language cdf objects.
   *
   * @param \Drupal\acquia_contenthub\Event\CreateCdfEntityEvent $event
   *   The parse cdf entity event object.
   */
  public function onCreateCdf(CreateCdfEntityEvent $event) {
    $entity = $event->getEntity();

    if ($entity->getEntityTypeId() !== 'configurable_language') {
      return;
    }
    $cdf = $event->getCdf($entity->uuid());
    $metadata = $cdf->getMetadata();
    $metadata['langcode'] = $entity->id();
    $cdf->setMetadata($metadata);
  }

}
