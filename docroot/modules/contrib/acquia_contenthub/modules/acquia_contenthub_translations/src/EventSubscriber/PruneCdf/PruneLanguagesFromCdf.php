<?php

namespace Drupal\acquia_contenthub_translations\EventSubscriber\PruneCdf;

use Acquia\ContentHubClient\CDF\CDFObjectInterface;
use Acquia\ContentHubClient\CDFDocument;
use Drupal\acquia_contenthub\AcquiaContentHubEvents;
use Drupal\acquia_contenthub\Event\PruneCdfEntitiesEvent;
use Drupal\acquia_contenthub\Libs\Traits\EntityCdfWrapperTrait;
use Drupal\acquia_contenthub_subscriber\CdfImporterInterface;
use Drupal\acquia_contenthub_translations\EntityHandler\Context;
use Drupal\acquia_contenthub_translations\EntityHandler\NonTranslatableEntityHandlerContext;
use Drupal\acquia_contenthub_translations\EntityTranslationManagerInterface;
use Drupal\acquia_contenthub_translations\Exceptions\TranslationDataException;
use Drupal\acquia_contenthub_translations\Helpers\SubscriberLanguagesTrait;
use Drupal\acquia_contenthub_translations\UndesiredLanguageRegistry\UndesiredLanguageRegistryInterface;
use Drupal\Core\Config\Config;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Prunes unwanted languages from incoming cdf on subscriber site.
 */
class PruneLanguagesFromCdf implements EventSubscriberInterface {

  use EntityCdfWrapperTrait;
  use SubscriberLanguagesTrait;

  /**
   * Undesired language registrar.
   *
   * @var \Drupal\acquia_contenthub_translations\UndesiredLanguageRegistry\UndesiredLanguageRegistryInterface
   */
  protected $registrar;

  /**
   * Content hub translations config.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $config;

  /**
   * Language Manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * Acquia content hub translations logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * Entity translation manager.
   *
   * @var \Drupal\acquia_contenthub_translations\EntityTranslationManagerInterface
   */
  protected $entityTranslationManager;

  /**
   * Non-translatable entity handler.
   *
   * @var \Drupal\acquia_contenthub_translations\EntityHandler\NonTranslatableEntityHandlerContext
   */
  protected $ntEntityHandler;

  /**
   * The values to insert into tracking table.
   *
   * @var array
   */
  private $entitiesToTrack = [];

  /**
   * Entities that don't have the translatable attributes.
   *
   * Issued for re-export.
   *
   * @var array
   */
  private $needReExport = [];

  /**
   * The acquia_contenthub_subscriber.cdf_importer service.
   *
   * @var \Drupal\acquia_contenthub_subscriber\CdfImporterInterface
   */
  protected $cdfImporter;

  /**
   * {@inheritDoc}
   */
  public static function getSubscribedEvents() {
    $events[AcquiaContentHubEvents::PRUNE_CDF][] = [
      'onPruneCdf',
      100,
    ];
    return $events;
  }

  /**
   * PruneLanguagesFromCdf constructor.
   *
   * @param \Drupal\acquia_contenthub_translations\UndesiredLanguageRegistry\UndesiredLanguageRegistryInterface $registrar
   *   Undesired language registrar.
   * @param \Drupal\Core\Config\Config $config
   *   Translations config object.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   Language Manager.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   Acquia content hub translations logger channel.
   * @param \Drupal\acquia_contenthub_translations\EntityTranslationManagerInterface $entity_translation_manager
   *   Entity translation manager.
   * @param \Drupal\acquia_contenthub_translations\EntityHandler\NonTranslatableEntityHandlerContext $handler
   *   The handler registry.
   * @param \Drupal\acquia_contenthub_subscriber\CdfImporterInterface $cdfImporter
   *   The acquia_contenthub_subscriber.cdf_importer service.
   */
  public function __construct(
    UndesiredLanguageRegistryInterface $registrar,
    Config $config,
    LanguageManagerInterface $language_manager,
    LoggerChannelInterface $logger,
    EntityTranslationManagerInterface $entity_translation_manager,
    NonTranslatableEntityHandlerContext $handler,
    CdfImporterInterface $cdfImporter
  ) {
    $this->registrar = $registrar;
    $this->config = $config;
    $this->languageManager = $language_manager;
    $this->logger = $logger;
    $this->entityTranslationManager = $entity_translation_manager;
    $this->ntEntityHandler = $handler;
    $this->cdfImporter = $cdfImporter;
  }

  /**
   * Prunes unwanted languages from the CDF.
   *
   * @param \Drupal\acquia_contenthub\Event\PruneCdfEntitiesEvent $event
   *   Prune cdf event.
   *
   * @throws \Exception
   */
  public function onPruneCdf(PruneCdfEntitiesEvent $event): void {
    $selective_language_enabled = $this->config->get('selective_language_import');
    if (!$selective_language_enabled) {
      return;
    }

    $cdf_document = $event->getCdf();

    $enabled_languages = $this->getOriginalEnabledLanguages($this->languageManager, $this->registrar);

    // Incoming languages from publisher.
    $incoming_languages = $this->getCdfConfigurableLanguages($cdf_document);

    $this->filterCdfDocument($cdf_document, $enabled_languages, $incoming_languages);

    $event->setCdf($cdf_document);
    $this->logger->info('Cdf document has been pruned w.r.t languages enabled on subscriber.');
  }

  /**
   * Filters unwanted languages from entities in CDF document.
   *
   * @param \Acquia\ContentHubClient\CDFDocument $cdf_document
   *   Cdf document.
   * @param array $enabled_languages
   *   Enabled languages on subscriber site.
   * @param array $incoming_languages
   *   Incoming configurable languages from publisher.
   *
   * @throws \Exception
   */
  public function filterCdfDocument(CDFDocument $cdf_document, array $enabled_languages, array $incoming_languages): void {
    $non_translatables = [];
    $undesired_languages = [];
    // Initial state: all languages other than already enabled are removable,
    // if a translation claims otherwise the language will be removed from the
    // removable list.
    $removable_languages = array_diff_key($incoming_languages, array_flip($enabled_languages));
    foreach ($cdf_document->getEntities() as $cdf) {
      if (!$this->isValidCdf($cdf)) {
        $this->enqueueForReExport($cdf);
        continue;
      }

      $cdf_metadata = $cdf->getMetadata();
      $default_language = $cdf_metadata['default_language'];
      $languages = $cdf_metadata['languages'] ?? [$default_language];
      $entity_type = $cdf->getAttribute('entity_type')->getValue()[LanguageInterface::LANGCODE_NOT_SPECIFIED];
      if ($this->isEntityNonTranslatable($cdf_metadata, $languages, $entity_type)) {
        $non_translatables[$cdf->getUuid()] = $cdf;
        continue;
      }

      // Languages common between this cdf and enabled on subscriber.
      $common_languages = array_values(array_intersect($languages, $enabled_languages));
      $tracking_data = [
        'entity_uuid' => $cdf->getUuid(),
        'entity_type' => $entity_type,
      ];
      $tracked_entity = $this->entityTranslationManager->getTrackedEntity($cdf->getUuid());
      // If none of the languages are available on subscriber,
      // then only import the default language of this entity.
      if (empty($common_languages)) {
        // This is necessary if it's a translatable entity so that next time
        // an accepted translation gets added
        // we don't end up changing the default language.
        if (!empty($cdf->getMetadata()['languages']) && !$tracked_entity) {
          $this->entitiesToTrack[] = $tracking_data + [
            'original_default_language' => $default_language,
            'default_language' => $default_language,
          ];
        }
        if (!in_array($default_language, $undesired_languages)) {
          $this->logger->info(
            '"@language" will be marked as undesired. This language will also be imported.',
            ['@language' => $default_language]);
          $this->logger->info('Incoming languages of @uuid having entity type "@type": @incoming_languages', [
            '@uuid' => $cdf->getUuid(),
            '@incoming_languages' => implode(',', array_keys($incoming_languages)),
            '@type' => $cdf->getAttributes()['entity_type']->getValue()[LanguageInterface::LANGCODE_NOT_SPECIFIED] ?? '',
          ]);
        }
        $this->updateUndesiredList($undesired_languages, $default_language);
        unset($removable_languages[$default_language]);
        $cdf_metadata['languages'] = [$default_language];
        // Remove other languages from language metadata
        // and dependency list of this entity.
        $unwanted_languages = array_diff($languages, $cdf_metadata['languages']);
        $this->removeConfigurableLanguagesFromCdf($cdf_metadata, $unwanted_languages, $incoming_languages, $removable_languages);
        $cdf->setMetadata($cdf_metadata);
        continue;
      }
      // If there are languages in the cdf which exist on subscriber.
      // If this entity was already tracked it means it was already imported
      // so default language can't be changed in this case for integrity.
      if (!$tracked_entity) {
        $this->changeDefaultLanguage($default_language, $common_languages, $cdf_metadata, $tracking_data);
      }
      // Ensure that the default language if it's not in common languages
      // is always updated whether it is accepted now or not.
      $cdf_metadata['languages'] = $tracked_entity && !in_array($default_language, $common_languages, TRUE)
        ? array_unique(array_merge($common_languages, [$tracked_entity->defaultLanguage()]))
        : $common_languages;
      // Remove other languages from language metadata
      // and dependency list of this entity.
      $unwanted_languages = array_diff($languages, $cdf_metadata['languages']);
      $this->removeConfigurableLanguagesFromCdf($cdf_metadata, $unwanted_languages, $incoming_languages, $removable_languages);
      $cdf->setMetadata($cdf_metadata);
    }

    // Halt before further processing if needed.
    $this->requestRepublishForInvalidCdfs();

    [$removable_languages, $undesired_languages] = $this->processNonTranslatables(
      $non_translatables, $cdf_document,
      [
        'removable' => $removable_languages,
        'undesired' => $undesired_languages,
        'enabled' => $enabled_languages,
        'incoming' => $incoming_languages,
      ]
    );

    $this->updateUndesiredLanguages($undesired_languages);
    $this->removeUnwantedLanguagesFromCdf($cdf_document, $removable_languages, $undesired_languages);

    if (!empty($this->entitiesToTrack)) {
      try {
        $this->entityTranslationManager->trackMultiple($this->entitiesToTrack);
      }
      catch (\Exception $e) {
        $this->logger->error(
          'An error occurred while tracking entities. Error: @error',
          ['@error' => $e->getMessage()]
        );
        throw $e;
      }
      finally {
        // Do not store data unnecessarily.
        $this->entitiesToTrack = [];
      }
    }
  }

  /**
   * Checks whether the CDF complies to the syndication requirements.
   *
   * @param \Acquia\ContentHubClient\CDF\CDFObjectInterface $cdf
   *   The CDF object to validate.
   *
   * @return bool
   *   True if the CDF passed the validation.
   */
  protected function isValidCdf(CDFObjectInterface $cdf): bool {
    return $cdf->getType() !== 'drupal8_content_entity' ||
      isset($cdf->getMetadata()['translatable']);
  }

  /**
   * Enqueues a CDF for reexport.
   *
   * @param \Acquia\ContentHubClient\CDF\CDFObjectInterface $cdf
   *   The CDF object to enqueue.
   */
  protected function enqueueForReExport(CDFObjectInterface $cdf): void {
    $entity_type = $this->getEntityType($cdf);
    $this->logger->warning(
      'Entity of type {entity_type} ({entity_uuid)} marked for re-export.',
      [
        'entity_type' => $entity_type,
        'entity_uuid' => $cdf->getUuid(),
      ]
    );
    $this->needReExport[$cdf->getOrigin()][] = [
      'uuid' => $cdf->getUuid(),
      'type' => $entity_type,
      'dependencies' => array_keys($cdf->getDependencies()),
    ];
  }

  /**
   * Sends a republish request to the publisher(s).
   *
   * Requests a re-export of entities collected during the filtering process.
   *
   * @throws \Drupal\acquia_contenthub_translations\Exceptions\TranslationDataException
   */
  protected function requestRepublishForInvalidCdfs(): void {
    if (empty($this->needReExport)) {
      return;
    }

    $this->logger->info(
      'Requested publisher(s) (origins: {origins}) to re-export entities with missing "translatable" attributes.',
      ['origins' => implode(', ', array_keys($this->needReExport))]
    );
    $this->cdfImporter->requestToRepublishEntities($this->needReExport);

    throw new TranslationDataException(
      'CDF validation failed due to missing "translatable" attribute.'
    );
  }

  /**
   * Processes non-translatable entities.
   *
   * Returns a modified list of removable and undesired language list.
   *
   * If there's no removable language nor undesired language to register,
   * the incoming entity is a non-translatable one.
   *
   * @param array $entities
   *   The list of non-translatable entities.
   * @param \Acquia\ContentHubClient\CDFDocument $document
   *   The CDF document.
   * @param array $language_collection
   *   The collection of languages used for processing input:
   *   removable, undesired, incoming, enabled.
   */
  protected function processNonTranslatables(array $entities, CDFDocument $document, array $language_collection): array {
    $removable_languages = $language_collection['removable'];
    $undesired_languages = $language_collection['undesired'];
    if (empty($entities)) {
      return [$removable_languages, $undesired_languages];
    }

    $enabled_languages = $language_collection['enabled'];
    $original_removable_list = $removable_languages;

    $context = new Context($document, $removable_languages);
    $this->ntEntityHandler->handle($entities, $enabled_languages, $context);
    $removable_languages = $context->getRemovableLanguages();
    // Removable list was modified. If a language is removed from the list it
    // must be considered as undesired.
    $diff = array_diff_key($original_removable_list, $removable_languages);
    if (!empty($diff)) {
      $undesired_languages = array_unique(array_merge($diff, $undesired_languages));
      $this->logger->info('Languages marked as undesired from non-translatable: (@languages).', [
        '@languages' => implode(',', $diff),
      ]);
    }
    array_push($this->entitiesToTrack, ...$context->getTrackableEntities());

    return [$removable_languages, $undesired_languages];
  }

  /**
   * Update undesired language list and removable language list.
   *
   * @param array $undesired_languages
   *   Undesired languages.
   * @param string $default_language
   *   Default language of cdf.
   */
  protected function updateUndesiredList(array &$undesired_languages, string $default_language): void {
    $undesired_languages[] = $default_language;
  }

  /**
   * Changes default language of cdf.
   *
   * Based on subscriber default and common languages.
   *
   * @param string $default_language
   *   Default language of CDF.
   * @param array $common_languages
   *   Common languages in CDF and subscriber's originally enabled languages.
   * @param array $cdf_metadata
   *   Cdf metadata.
   * @param array $tracking_data
   *   Cdf tracking data.
   */
  protected function changeDefaultLanguage(string $default_language, array $common_languages, array &$cdf_metadata, array $tracking_data): void {
    if (in_array($default_language, $common_languages, TRUE)) {
      return;
    }
    $subscriber_default_language = $this->getSubscriberDefaultLanguage();
    $new_default_language = $common_languages[0];
    if (in_array($subscriber_default_language, $common_languages, TRUE)) {
      $new_default_language = $subscriber_default_language;
    }
    $cdf_metadata['default_language'] = $new_default_language;

    $this->entitiesToTrack[] = $tracking_data + [
      'original_default_language' => $default_language,
      'default_language' => $new_default_language,
    ];
  }

  /**
   * Removes unwanted configurable languages from current CDF's dependencies.
   *
   * If a language was in the cdf as dependency
   * but was previously imported on subscriber(as undesired language)
   * so added to stack directly(unchanged hash) without fetching
   * in this case we don't need to remove it from dependencies
   * as it won't make any difference, and it can't be removed from cdf
   *  as it was not available in the cdf in first place.
   *
   * @param array $cdf_metadata
   *   Cdf metadata.
   * @param array $unwanted_languages
   *   Unwanted languages for this CDF.
   * @param array $incoming_languages
   *   Configurable languages coming from publisher.
   * @param array $removable_languages
   *   Removable languages from CDF.
   */
  protected function removeConfigurableLanguagesFromCdf(array &$cdf_metadata, array $unwanted_languages, array $incoming_languages, array $removable_languages): void {
    $unwanted_languages = array_unique(array_merge($unwanted_languages, array_keys($removable_languages)));
    foreach ($unwanted_languages as $unwanted_language) {
      // Get cdf of this unwanted language.
      $unwanted_language_uuid = $incoming_languages[$unwanted_language] ?? '';
      if (empty($unwanted_language_uuid)) {
        continue;
      }
      unset($cdf_metadata['dependencies']['entity'][$unwanted_language_uuid]);
    }
  }

  /**
   * Returns default language of subscriber site.
   *
   * @return string
   *   Subscriber default language.
   */
  protected function getSubscriberDefaultLanguage(): string {
    return $this->languageManager->getDefaultLanguage()->getId();
  }

  /**
   * Get CDF languages.
   *
   * @param \Acquia\ContentHubClient\CDFDocument $cdf_document
   *   CDF Document.
   *
   * @return array
   *   Array of language code => uuid for configurable language.
   */
  protected function getCdfConfigurableLanguages(CDFDocument $cdf_document): array {
    $languages = [];
    foreach ($cdf_document->getEntities() as $cdf) {
      $entity_type = $cdf->getAttribute('entity_type')->getValue()[LanguageInterface::LANGCODE_NOT_SPECIFIED];

      if ($entity_type !== 'configurable_language') {
        continue;
      }

      if (isset($cdf->getMetadata()['langcode'])) {
        $languages[$cdf->getMetadata()['langcode']] = $cdf->getUuid();
      }
    }

    return $languages;
  }

  /**
   * Helper method to update undesired language list.
   *
   * @param array $undesired_languages
   *   Array of languages to add into undesired language list.
   */
  protected function updateUndesiredLanguages(array $undesired_languages): void {
    if (empty($undesired_languages)) {
      return;
    }

    $undesired_languages = array_unique($undesired_languages);
    $this->registrar->markLanguagesUndesired(...$undesired_languages);
    $this->logger->info(
      'Languages marked as undesired: (@languages). These languages will also be imported.',
      ['@languages' => implode(',', $undesired_languages)]
    );
  }

  /**
   * Removes finally removable languages from CDF document.
   *
   * If the incoming language is not marked as removable but not in the
   * undesired language list, it means it can be safely removed.
   *
   * @param \Acquia\ContentHubClient\CDFDocument $cdf_document
   *   Cdf document.
   * @param array $removable_languages
   *   Removable languages.
   * @param array $undesired_languages
   *   Undesired languages.
   */
  protected function removeUnwantedLanguagesFromCdf(CDFDocument $cdf_document, array $removable_languages, array $undesired_languages): void {
    foreach ($removable_languages as $lang => $uuid) {
      // If this language was marked undesired in any iteration,
      // make sure it's not removed from CDF.
      if (in_array($lang, $undesired_languages, TRUE)) {
        continue;
      }
      $cdf_document->removeCdfEntity($uuid);
    }
  }

  /**
   * Checks whether a particular cdf should be non-translatable or not.
   *
   * @param array $cdf_metadata
   *   Cdf metadata.
   * @param array $languages
   *   Languages available for this CDF.
   * @param string $entity_type
   *   Entity type for this CDF.
   *
   * @return bool
   *   True if non-translatable, false otherwise.
   */
  protected function isEntityNonTranslatable(array $cdf_metadata, array $languages, string $entity_type): bool {
    return (isset($cdf_metadata['translatable']) && !$cdf_metadata['translatable'])
      ||
      // This is a hard requirement for entities
      // to be treated as non-translatables
      // that they only have 1 language and are added to nt override registry.
      (count($languages) === 1 && $this->ntEntityHandler->isEntityOverridden($entity_type));
  }

}
