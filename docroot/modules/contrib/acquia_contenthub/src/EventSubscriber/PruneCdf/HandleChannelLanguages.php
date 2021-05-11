<?php

namespace Drupal\acquia_contenthub\EventSubscriber\PruneCdf;

use Acquia\ContentHubClient\CDF\CDFObject;
use Acquia\ContentHubClient\CDFDocument;
use Acquia\ContentHubClient\Settings;
use Drupal\acquia_contenthub\AcquiaContentHubEvents;
use Drupal\acquia_contenthub\Event\PruneCdfEntitiesEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Handles channel languages during pruning.
 */
class HandleChannelLanguages implements EventSubscriberInterface {

  /**
   * The client settings.
   *
   * @var \Acquia\ContentHubClient\Settings
   */
  protected $clientSettings;

  /**
   * HandleChannelLanguages constructor.
   *
   * @param \Acquia\ContentHubClient\Settings $client_settings
   *   The client settings.
   */
  public function __construct(Settings $client_settings) {
    $this->clientSettings = $client_settings;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[AcquiaContentHubEvents::PRUNE_CDF][] = 'onPruneCdf';
    return $events;
  }

  /**
   * Handles channel languages.
   *
   * @param \Drupal\acquia_contenthub\Event\PruneCdfEntitiesEvent $event
   *   The prune event.
   */
  public function onPruneCdf(PruneCdfEntitiesEvent $event) {
    $cdf = $event->getCdf();
    $allLanguages = $this->getCdfConfigurableLanguages($cdf);
    $channelLanguages = $this->getChannelLanguages($allLanguages);

    $removedLanguages = [];
    foreach ($allLanguages as $langcode => $language) {
      if (!isset($channelLanguages[$langcode])) {
        $cdf->removeCdfEntity($language->getUuid());
        $removedLanguages[$langcode] = $language;
      }
    }

    if (empty($removedLanguages)) {
      return;
    }

    foreach ($cdf->getEntities() as $cdfEntity) {
      $this->removeDependenciesToNotChannelLanguages($cdfEntity, $removedLanguages);
    }
  }

  /**
   * Removes languages that don't belong to the channel.
   *
   * @param array $languages
   *   Languages of interest.
   *
   * @return array
   *   The languages that don't belong to the channel.
   */
  protected function getChannelLanguages(array $languages) {
    $name = $this->clientSettings->getName();
    $channelLanguages = [];

    /** @var \Acquia\ContentHubClient\CDFDocument $language */
    foreach ($languages as $language) {
      $channelName = $this->getLanguageChannelName($language);
      // If language is global (no channel) or
      // belongs to current channel, add it to the array.
      if (empty($channelName) || $channelName === $name) {
        $channelLanguages[$language->getMetadata()['langcode']] = $language;
      }
    }

    return $this->removeGlobalLanguagesIfChannelLanguageExist($channelLanguages);
  }

  /**
   * Remove global languages if channel exists.
   *
   * @param array $channelLanguages
   *   Channel languages.
   *
   * @return array
   *   Filtered channel languages.
   */
  protected function removeGlobalLanguagesIfChannelLanguageExist(array $channelLanguages) {
    $langcodes = array_keys($channelLanguages);

    foreach ($channelLanguages as $langcode => $language) {
      $channelName = $this->getLanguageChannelName($language);
      // If language is global (no channel)
      // but there are other languages with the same prefix, remove it.
      if (empty($channelName) &&
        !empty(preg_grep("/^${langcode}_/", $langcodes))) {
        unset($channelLanguages[$langcode]);
      }
    }

    return $channelLanguages;
  }

  /**
   * Get Channel Language Name.
   *
   * @param \Acquia\ContentHubClient\CDF\CDFObject $language
   *   Language from CDF Object.
   *
   * @return string
   *   Channel name attribute.
   */
  protected function getLanguageChannelName(CDFObject $language) {
    $channelNameAttribute = $language->getAttribute('channel_name');
    return $channelNameAttribute ? $channelNameAttribute->getValue()['und'] : '';
  }

  /**
   * Get CDF languages.
   *
   * @param \Acquia\ContentHubClient\CDFDocument $cdf
   *   CDF Document.
   *
   * @return \Acquia\ContentHubClient\CDF\CDFObject[]
   *   CDF Object.
   */
  protected function getCdfConfigurableLanguages(CDFDocument $cdf) {

    $languages = [];
    foreach ($cdf->getEntities() as $cdfEntity) {
      $entity_type = $cdfEntity->getAttribute('entity_type')->getValue()['und'];

      if ($entity_type !== 'configurable_language') {
        continue;
      }

      if (isset($cdfEntity->getMetadata()['langcode'])) {
        $languages[$cdfEntity->getMetadata()['langcode']] = $cdfEntity;
      }
    }

    return $languages;
  }

  /**
   * Removes languages that don't belong to the channel.
   *
   * @param \Acquia\ContentHubClient\CDF\CDFObject $cdfEntity
   *   The CDF object.
   * @param array $removedLanguages
   *   The languages that don't belong to the channel.
   */
  protected function removeDependenciesToNotChannelLanguages(CDFObject $cdfEntity, array $removedLanguages) {
    $metadata = $cdfEntity->getMetadata();

    // User will not have channel specific language, do not remove the default.
    $bundle = $cdfEntity->getAttribute('bundle');
    if (isset($bundle)) {
      $value = $bundle->getValue();
      if (isset($value['und']) && $value['und'] === 'user') {
        return;
      }
    }

    // Not a multilingual entity, nothing to do here.
    if (!isset($metadata['languages'])) {
      return;
    }

    /** @var \Acquia\ContentHubClient\CDF\CDFObject $languageCdf */
    foreach ($removedLanguages as $languageCdf) {
      $removedLangcode = $languageCdf->getMetadata()['langcode'];
      if (($langcodeKey = array_search($removedLangcode, $metadata['languages'], TRUE)) !== FALSE) {
        unset($metadata['languages'][$langcodeKey]);
      }

      // If default language doesn't belong to channel, use the first language.
      if (!in_array($metadata['default_language'], $metadata['languages'], TRUE)) {
        $metadata['default_language'] = current($metadata['languages']);
      }

      unset($metadata['dependencies']['entity'][$languageCdf->getUuid()]);
    }
    $cdfEntity->setMetadata($metadata);
  }

}
