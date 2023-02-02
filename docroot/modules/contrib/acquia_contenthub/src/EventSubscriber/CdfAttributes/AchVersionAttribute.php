<?php

namespace Drupal\acquia_contenthub\EventSubscriber\CdfAttributes;

use Acquia\ContentHubClient\CDFAttribute;
use Drupal\acquia_contenthub\AcquiaContentHubEvents;
use Drupal\acquia_contenthub\Client\ProjectVersionClient;
use Drupal\acquia_contenthub\Event\BuildClientCdfEvent;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Serialization\Yaml;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Adds ACH module version attribute to client CDF.
 */
class AchVersionAttribute implements EventSubscriberInterface {

  /**
   * Project Version Client to fetch D.O latest releases.
   *
   * @var \Drupal\acquia_contenthub\Client\ProjectVersionClient
   */
  protected $pvClient;

  /**
   * Module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * AchVersionAttribute constructor.
   *
   * @param \Drupal\acquia_contenthub\Client\ProjectVersionClient $pv_client
   *   Project version client.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   Module Handler Service.
   */
  public function __construct(ProjectVersionClient $pv_client, ModuleHandlerInterface $module_handler) {
    $this->pvClient = $pv_client;
    $this->moduleHandler = $module_handler;
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

    $ach_attributes['current'] = $this->getAchVersion();
    $ch_latest_versions = $this->pvClient->getContentHubReleases();
    if (!empty($ch_latest_versions)) {
      $ach_attributes['latest'] = $ch_latest_versions['latest'];
    }
    $cdf->addAttribute('ch_version', CDFAttribute::TYPE_ARRAY_STRING);
    $attribute = $cdf->getAttribute('ch_version');
    $attribute->setValue($ach_attributes);
  }

  /**
   * Fetch ACH version.
   *
   * @return mixed|string
   *   Acquia Content Hub version.
   *
   * @throws \Exception
   */
  protected function getAchVersion() {
    $module_path = $this->moduleHandler->getModule('acquia_contenthub')->getPath();
    $version_file_path = $module_path . '/acquia_contenthub_versions.yml';

    if (!file_exists($version_file_path)) {
      throw new \Exception('ACH YAML version file doesn\'t exist.');
    }

    $versions = Yaml::decode(file_get_contents($version_file_path));
    return $versions['acquia_contenthub'] ?? '';
  }

}
