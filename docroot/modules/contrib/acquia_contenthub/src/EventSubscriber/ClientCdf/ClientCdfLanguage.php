<?php

namespace Drupal\acquia_contenthub\EventSubscriber\ClientCdf;

use Drupal\acquia_contenthub\AcquiaContentHubEvents;
use Drupal\acquia_contenthub\Event\BuildClientCdfEvent;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Language\LanguageDefault;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Adds base url to all CDF documents.
 */
class ClientCdfLanguage implements EventSubscriberInterface {

  /**
   * The Entity Type Manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * The Module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandler
   */
  protected $moduleHandler;

  /**
   * The default language.
   *
   * @var \Drupal\Core\Language\LanguageDefault
   */
  protected $languageDefault;

  /**
   * ClientManagerFactory constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManager $entityTypeManager
   *   The Entity Type Manager.
   * @param \Drupal\Core\Extension\ModuleHandler $moduleHandler
   *   The module handler.
   * @param \Drupal\Core\Language\LanguageDefault $language_default
   *   Default language.
   */
  public function __construct(EntityTypeManager $entityTypeManager, ModuleHandler $moduleHandler, LanguageDefault $language_default) {
    $this->entityTypeManager = $entityTypeManager;
    $this->moduleHandler = $moduleHandler;
    $this->languageDefault = $language_default;
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
   * Adds language information to ClientCDF documents.
   *
   * @param \Drupal\acquia_contenthub\Event\BuildClientCdfEvent $event
   *   The event being dispatched.
   *
   * @throws \Exception
   */
  public function onBuildClientCdf(BuildClientCdfEvent $event) {
    $cdf = $event->getCdf();
    $metadata = $cdf->getMetadata();

    // Set a default language if translation is not enabled.
    if (!$this->moduleHandler->moduleExists('language')) {
      $default_language = [
        'direction' => $this->languageDefault->get()->getDirection(),
        'id' => $this->languageDefault->get()->getId(),
        'label' => $this->languageDefault->get()->getName(),
        'langcode' => $this->languageDefault->get()->getId(),
        'locked' => $this->languageDefault->get()->isLocked(),
        'status' => 1,
        'weight' => $this->languageDefault->get()->getWeight(),
      ];

      $metadata['languages'][$default_language['langcode']] = $default_language;
    }
    else {
      $metadata['languages'] = $this->getLanguages();
    }
    $cdf->setMetadata($metadata);
  }

  /**
   * Get languages from the configurable language entity type manager.
   *
   * @return array
   *   The array of languages.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getLanguages() {
    $languages = [];
    $lang_entities = $this->entityTypeManager->getStorage('configurable_language')->loadMultiple();
    foreach ($lang_entities as $langcode => $language) {
      $languages[$langcode] = $language->toArray();
      // Cleanup Extra lines for efficient storage in Plexus.
      unset($languages[$langcode]['_core'], $languages[$langcode]['dependencies']);
    }
    return $languages;
  }

}
