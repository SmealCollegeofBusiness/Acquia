<?php

namespace Drupal\acquia_contenthub_translations\UndesiredLanguageRegistry;

use Drupal\Core\Config\Config;
use Drupal\Core\Logger\LoggerChannelInterface;

/**
 * Registrar service which adds undesired languages.
 *
 * To translation configuration.
 */
class UndesiredLanguageRegistrar implements UndesiredLanguageRegistryInterface {

  /**
   * Content Hub translation settings config.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $config;

  /**
   * Content Hub translations logger.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * UndesiredLanguageRegistrar constructor.
   *
   * @param \Drupal\Core\Config\Config $config
   *   Content Hub translation settings config.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   Content Hub translations logger.
   */
  public function __construct(Config $config, LoggerChannelInterface $logger) {
    $this->config = $config;
    $this->logger = $logger;
  }

  /**
   * {@inheritDoc}
   */
  public function markLanguagesUndesired(string ...$languages): void {
    $existing_languages = $this->getUndesiredLanguages();
    $new_languages = array_unique(array_merge($existing_languages, $languages));
    $this->config->set('undesired_languages', $new_languages)->save();
  }

  /**
   * {@inheritDoc}
   */
  public function getUndesiredLanguages(): array {
    return $this->config->get('undesired_languages') ?? [];
  }

  /**
   * {@inheritDoc}
   */
  public function isLanguageUndesired(string $language): bool {
    return in_array($language, $this->getUndesiredLanguages(), TRUE);
  }

  /**
   * {@inheritDoc}
   *
   * @see acquia_contenthub_translations_configurable_language_delete
   */
  public function removeLanguageFromUndesired(string ...$languages): void {
    $current_languages = $this->getUndesiredLanguages();
    $removable_languages = array_intersect($current_languages, $languages);
    if (empty($removable_languages)) {
      return;
    }
    $new_languages = array_diff($current_languages, $removable_languages);
    $this->config->set('undesired_languages', array_values($new_languages))->save();
    $this->logger->info('Language(s) (%lang) have been removed from undesired languages.', ['%lang' => implode(', ', $removable_languages)]);
  }

}
