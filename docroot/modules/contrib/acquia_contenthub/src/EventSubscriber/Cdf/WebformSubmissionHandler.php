<?php

namespace Drupal\acquia_contenthub\EventSubscriber\Cdf;

use Drupal\acquia_contenthub\AcquiaContentHubEvents;
use Drupal\acquia_contenthub\Event\SerializeAdditionalMetadataEvent;
use Drupal\acquia_contenthub\Event\UnserializeAdditionalMetadataEvent;
use Drupal\webform\WebformSubmissionInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * The Webform submission entity handler.
 *
 * @see \Drupal\acquia_contenthub\Event\SerializeAdditionalMetadataEvent
 * @see \Drupal\acquia_contenthub\Event\UnserializeAdditionalMetadataEvent
 */
class WebformSubmissionHandler implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[AcquiaContentHubEvents::SERIALIZE_ADDITIONAL_METADATA][] = [
      'onSerializeWebformElements',
      100,
    ];
    $events[AcquiaContentHubEvents::UNSERIALIZE_ADDITIONAL_METADATA][] = [
      'onUnserializeWebformElements',
      100,
    ];
    return $events;
  }

  /**
   * Serialize webform elements for webform submission entity.
   *
   * @param \Drupal\acquia_contenthub\Event\SerializeAdditionalMetadataEvent $event
   *   Serialize event for additional metadata.
   */
  public function onSerializeWebformElements(SerializeAdditionalMetadataEvent $event) {
    $entity = $event->getEntity();
    // Bail early if this isn't a webform submission entity.
    if (!$entity instanceof WebformSubmissionInterface) {
      return;
    }
    $cdf = $event->getCdf();
    $metadata = $cdf->getMetadata();
    // Get webform elements data.
    $webform_elements = $entity->getData();
    if ($webform_elements) {
      $metadata['additional_data']['webform_elements'] = base64_encode(json_encode($webform_elements));
      $cdf->setMetadata($metadata);
      $event->setCdf($cdf);
    }
    $event->stopPropagation();
  }

  /**
   * Unserialize webform elements from webform submission CDF.
   *
   * @param \Drupal\acquia_contenthub\Event\UnserializeAdditionalMetadataEvent $event
   *   Unserialize event for additional metadata.
   */
  public function onUnserializeWebformElements(UnserializeAdditionalMetadataEvent $event) {
    $cdf = $event->getCdf();
    // Bail early if this isn't a webform submission entity.
    if ($cdf->getAttribute('entity_type')->getValue()['und'] !== 'webform_submission') {
      return;
    }
    /** @var \Drupal\webform\WebformSubmissionInterface $entity */
    $entity = $event->getEntity();
    $metadata = $cdf->getMetadata();
    // Get webform elements data.
    $webform_elements = json_decode(base64_decode($metadata['additional_data']['webform_elements']), TRUE);
    if (is_array($webform_elements)) {
      $entity->setData($webform_elements);
      $event->setEntity($entity);
    }
    $event->stopPropagation();
  }

}
