<?php

namespace Drupal\acquia_contenthub\EventSubscriber\CdfAttributes;

use Acquia\ContentHubClient\CDFAttribute;
use Drupal\acquia_contenthub\AcquiaContentHubEvents;
use Drupal\acquia_contenthub\Client\ProjectVersionClient;
use Drupal\acquia_contenthub\Event\BuildClientCdfEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Adds core version attribute to client CDF.
 */
class CoreVersionAttribute implements EventSubscriberInterface {

  /**
   * Project Version Client to fetch D.O latest releases.
   *
   * @var \Drupal\acquia_contenthub\Client\ProjectVersionClient
   */
  protected $pvClient;

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[AcquiaContentHubEvents::BUILD_CLIENT_CDF][] = ['onBuildClientCdf'];
    return $events;
  }

  /**
   * CoreVersionAttribute constructor.
   *
   * @param \Drupal\acquia_contenthub\Client\ProjectVersionClient $pv_client
   *   Project version client.
   */
  public function __construct(ProjectVersionClient $pv_client) {
    $this->pvClient = $pv_client;
  }

  /**
   * Method called on the BUILD_CLIENT_CDF event.
   *
   * Adds core version attribute to the cdf.
   *
   * @param \Drupal\acquia_contenthub\Event\BuildClientCdfEvent $event
   *   The event being dispatched.
   *
   * @throws \Exception
   */
  public function onBuildClientCdf(BuildClientCdfEvent $event) {
    $cdf = $event->getCdf();
    $current_version = \Drupal::VERSION;
    $versions['current'] = $current_version;
    $recommended_versions = $this->pvClient->getDrupalReleases($current_version[0]);
    if (!empty($recommended_versions)) {
      $versions = array_merge($versions, $recommended_versions);
    }
    $cdf->addAttribute('drupal_version', CDFAttribute::TYPE_ARRAY_STRING);
    $attribute = $cdf->getAttribute('drupal_version');
    $attribute->setValue($versions);
  }

}
