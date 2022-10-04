<?php

namespace Drupal\acquia_contenthub_translations\EntityHandler;

use Acquia\ContentHubClient\CDF\CDFObject;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Handles entities which default language can be changed, e.g. files.
 */
class LanguageFlexible implements NonTranslatableEntityHandlerInterface {

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The logger channel.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Constructs a new object.
   *
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger channel.
   */
  public function __construct(LanguageManagerInterface $language_manager, LoggerInterface $logger) {
    $this->languageManager = $language_manager;
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public function handleEntity(CDFObject $cdf, Context $context): void {
    $metadata = $cdf->getMetadata();
    $lang = $metadata['default_language'];
    $local_lang = $this->languageManager->getDefaultLanguage()->getId();
    $entity_type = $cdf->getAttribute('entity_type')->getValue()[LanguageInterface::LANGCODE_NOT_SPECIFIED];

    $context->addTrackableEntity([
      'entity_uuid' => $cdf->getUuid(),
      'entity_type' => $entity_type,
      'original_default_language' => $lang,
      'default_language' => $local_lang,
    ]);

    $metadata['default_language'] = $local_lang;
    $metadata['languages'] = [$local_lang];
    unset($metadata['dependencies']['entity'][$context->getRemovableLanguages()[$lang]]);

    $cdf->setMetadata($metadata);
    $this->logger->info(sprintf('Changed default language of %s | %s from %s to %s',
        $entity_type, $cdf->getUuid(), $lang, $local_lang)
    );
  }

}
