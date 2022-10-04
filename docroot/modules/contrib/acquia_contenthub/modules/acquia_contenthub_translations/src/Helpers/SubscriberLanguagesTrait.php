<?php

namespace Drupal\acquia_contenthub_translations\Helpers;

use Drupal\acquia_contenthub_translations\UndesiredLanguageRegistry\UndesiredLanguageRegistryInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;

/**
 * Provides subscriber's enabled languages.
 */
trait SubscriberLanguagesTrait {

  /**
   * Returns subscriber's configured languages.
   *
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   Language manager.
   *
   * @return array
   *   Langcodes of enabled languages. ['en', 'fr'] etc.
   */
  protected function getSubscriberEnabledLanguages(LanguageManagerInterface $language_manager): array {
    return array_keys($language_manager->getLanguages(LanguageInterface::STATE_ALL));
  }

  /**
   * Returns original languages enabled on subscriber site.
   *
   * Excluding undesired languages.
   *
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   Language manager.
   * @param \Drupal\acquia_contenthub_translations\UndesiredLanguageRegistry\UndesiredLanguageRegistryInterface $registrar
   *   Undesired language registrar.
   *
   * @return array
   *   Array of originally enabled languages.
   */
  protected function getOriginalEnabledLanguages(LanguageManagerInterface $language_manager, UndesiredLanguageRegistryInterface $registrar): array {
    return array_values(array_diff($this->getSubscriberEnabledLanguages($language_manager), $registrar->getUndesiredLanguages()));
  }

}
