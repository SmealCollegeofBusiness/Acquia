<?php

namespace Drupal\acquia_contenthub\EventSubscriber\CdfAttributes;

use Acquia\ContentHubClient\CDFAttribute;
use Drupal\acquia_contenthub\AcquiaContentHubEvents;
use Drupal\acquia_contenthub\Event\BuildClientCdfEvent;
use Drupal\acquia_contenthub\Event\CdfAttributesEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Calculates a hash value of the entity and stores it as an attribute.
 */
class HashCdfAttribute implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[AcquiaContentHubEvents::POPULATE_CDF_ATTRIBUTES][] = ['onPopulateAttributes', 100];
    $events[AcquiaContentHubEvents::BUILD_CLIENT_CDF][] = ['onBuildClientCdf', -100];

    return $events;
  }

  /**
   * On populate attributes.
   *
   * @param \Drupal\acquia_contenthub\Event\CdfAttributesEvent $event
   *   CDF attributes event.
   *
   * @throws \Exception
   */
  public function onPopulateAttributes(CdfAttributesEvent $event) {
    $cdf = $event->getCdf();
    $cdf->addAttribute('hash', CDFAttribute::TYPE_STRING, sha1($cdf->getMetadata()['data']));
  }

  /**
   * On Build ClientCdf.
   *
   * @param \Drupal\acquia_contenthub\Event\BuildClientCdfEvent $event
   *   The BuildClientCdfEvent object.
   *
   * @throws \Exception
   */
  public function onBuildClientCdf(BuildClientCdfEvent $event) {
    $cdf = $event->getCdf();
    $data = $cdf->toArray();
    unset($data['created'], $data['modified']);
    $cdf->addAttribute('hash', CDFAttribute::TYPE_KEYWORD, sha1(json_encode($data)));
    $event->stopPropagation();
  }

}
