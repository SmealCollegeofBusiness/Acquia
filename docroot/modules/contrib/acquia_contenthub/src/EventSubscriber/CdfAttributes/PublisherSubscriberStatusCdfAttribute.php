<?php

namespace Drupal\acquia_contenthub\EventSubscriber\CdfAttributes;

use Acquia\ContentHubClient\CDFAttribute;
use Drupal\acquia_contenthub\AcquiaContentHubEvents;
use Drupal\acquia_contenthub\Event\BuildClientCdfEvent;
use Drupal\acquia_contenthub\PubSubModuleStatusChecker;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Extracts user specific data for identifying duplicate and anonymous users.
 */
class PublisherSubscriberStatusCdfAttribute implements EventSubscriberInterface {

  /**
   * Status Checker.
   *
   * @var \Drupal\acquia_contenthub\PubSubModuleStatusChecker
   */
  private $checker;

  /**
   * Constructor.
   *
   * @param \Drupal\acquia_contenthub\PubSubModuleStatusChecker $checker
   *   Status Checker.
   */
  public function __construct(PubSubModuleStatusChecker $checker) {
    $this->checker = $checker;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[AcquiaContentHubEvents::BUILD_CLIENT_CDF][] =
      ['onBuildClientCdf', 100];
    return $events;
  }

  /**
   * Method called on the BUILD_CLIENT_CDF event.
   *
   * Adds publisher/subscriber status to the cdf.
   *
   * @param \Drupal\acquia_contenthub\Event\BuildClientCdfEvent $event
   *   The BuildClientCdfEvent object.
   *
   * @throws \Exception
   */
  public function onBuildClientCdf(BuildClientCdfEvent $event) {
    $cdf = $event->getCdf();

    $cdf->addAttribute('publisher', CDFAttribute::TYPE_BOOLEAN, $this->checker->isPublisher());
    $cdf->addAttribute('subscriber', CDFAttribute::TYPE_BOOLEAN, $this->checker->isSubscriber());
  }

}
