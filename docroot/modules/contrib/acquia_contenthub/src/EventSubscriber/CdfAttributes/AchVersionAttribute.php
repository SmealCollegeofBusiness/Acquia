<?php

namespace Drupal\acquia_contenthub\EventSubscriber\CdfAttributes;

use Acquia\ContentHubClient\CDFAttribute;
use Drupal\acquia_contenthub\AcquiaContentHubEvents;
use Drupal\acquia_contenthub\Client\ProjectVersionClient;
use Drupal\acquia_contenthub\Event\BuildClientCdfEvent;
use Drupal\Core\Extension\ModuleExtensionList;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Adds ACH module version attribute to client CDF.
 */
class AchVersionAttribute implements EventSubscriberInterface {

  /**
   * The module extension list service.
   *
   * @var \Drupal\Core\Extension\ModuleExtensionList
   */
  protected $moduleList;

  /**
   * Project Version Client to fetch D.O latest releases.
   *
   * @var \Drupal\acquia_contenthub\Client\ProjectVersionClient
   */
  protected $pvClient;

  /**
   * AchVersionAttribute constructor.
   *
   * @param \Drupal\Core\Extension\ModuleExtensionList $moduleExtensionList
   *   Module list.
   * @param \Drupal\acquia_contenthub\Client\ProjectVersionClient $pv_client
   *   Project version client.
   */
  public function __construct(ModuleExtensionList $moduleExtensionList, ProjectVersionClient $pv_client) {
    $this->moduleList = $moduleExtensionList;
    $this->pvClient = $pv_client;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[AcquiaContentHubEvents::BUILD_CLIENT_CDF][] = ['onBuildClientCdf'];
    return $events;
  }

  /**
   * Method called on the BUILD_CLIENT_CDF event.
   *
   * Adds ACH module version attribute to the cdf.
   *
   * @param \Drupal\acquia_contenthub\Event\BuildClientCdfEvent $event
   *   The event being dispatched.
   *
   * @throws \Exception
   */
  public function onBuildClientCdf(BuildClientCdfEvent $event) {
    $cdf = $event->getCdf();

    $extension_list = $this->moduleList->getList();
    // @todo change this once LCH-5023 is complete and read it from custom maintained versions yml.
    $version = $extension_list['acquia_contenthub']->info['version'] ?? '';
    $ach_attributes = [];
    $ach_attributes['current'] = $version;
    $ch_latest_versions = $this->pvClient->getContentHubReleases();
    if (!empty($ch_latest_versions)) {
      $ch_latest_version = $ch_latest_versions['latest'];
      $ch_latest_version = substr($ch_latest_version, strpos($ch_latest_version, '-') + 1);
      $ach_attributes['latest'] = $ch_latest_version;
    }
    $cdf->addAttribute('ch_version', CDFAttribute::TYPE_ARRAY_STRING);
    $attribute = $cdf->getAttribute('ch_version');
    $attribute->setValue($ach_attributes);
  }

}
