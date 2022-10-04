<?php

namespace Drupal\acquia_contenthub_translations\EntityHandler;

use Acquia\ContentHubClient\CDF\CDFObject;
use Drupal\Core\Language\LanguageInterface;

/**
 * Main entrypoint for non-translatable entity handlers.
 */
class NonTranslatableEntityHandlerContext {

  /**
   * Non-translatable entity handlers.
   *
   * @var \Drupal\acquia_contenthub_translations\EntityHandler\NonTranslatableEntityHandlerInterface[]
   */
  protected $handlers = [];

  /**
   * The handler registry.
   *
   * @var \Drupal\acquia_contenthub_translations\EntityHandler\HandlerRegistry
   */
  protected $registry;

  /**
   * Constructs a new object.
   *
   * @param \Drupal\acquia_contenthub_translations\EntityHandler\HandlerRegistry $registry
   *   The handler registry.
   */
  public function __construct(HandlerRegistry $registry) {
    $this->registry = $registry;
  }

  /**
   * Registers new handlers.
   *
   * Called in the compiler pass.
   *
   * @param \Drupal\acquia_contenthub_translations\EntityHandler\NonTranslatableEntityHandlerInterface $handler
   *   The handler to register.
   * @param string $id
   *   The handler's identifier.
   *
   * @see \Drupal\acquia_contenthub_translations\EntityHandler\NonTranslatableEntityHandlerCompilerPass
   */
  public function addHandler(NonTranslatableEntityHandlerInterface $handler, string $id): void {
    $this->handlers[$id] = $handler;
  }

  /**
   * Handles the non-translatable entities by choosing the correct handler.
   *
   * The process ends in a final cleanup.
   *
   * @param \Acquia\ContentHubClient\CDF\CDFObject[] $non_translatables
   *   Non-translatable entities.
   * @param array $enabled_languages
   *   The enabled languages on the site.
   * @param \Drupal\acquia_contenthub_translations\EntityHandler\Context $context
   *   The context provided for the set of entities.
   */
  public function handle(array $non_translatables, array $enabled_languages, Context $context): void {
    $removable_langs = $context->getRemovableLanguages();
    $remove_dep = $context->getRemovableCdfs();
    array_push($remove_dep, ...array_values($removable_langs));
    $doc = $context->getCdfDocument();

    foreach ($non_translatables as $cdf) {
      $lang = $cdf->getMetadata()['default_language'];
      if (in_array($lang, $enabled_languages) && !in_array($lang, array_keys($removable_langs))) {
        continue;
      }

      $entity_type = $this->getEntityType($cdf);
      $handler = $this->handlers[$this->getHandlerType($entity_type)];
      $handler->handleEntity($cdf, $context);
    }

    array_push($remove_dep, ...$context->getRemovableCdfs());
    if (empty($remove_dep)) {
      return;
    }

    // Final cleanup from dependencies.
    foreach ($doc->getEntities() as $cdf) {
      $metadata = $cdf->getMetadata();
      foreach ($remove_dep as $dep) {
        unset($metadata['dependencies']['entity'][$dep]);
      }
      $cdf->setMetadata($metadata);
    }
  }

  /**
   * Returns the appropriate handler types.
   *
   * @param string $entity_type
   *   The entity type to handle.
   *
   * @return string
   *   The handler used to return the appropriate handler.
   */
  protected function getHandlerType(string $entity_type): string {
    return $this->registry->getHandlerIdFor($entity_type);
  }

  /**
   * Returns the CDF's entity type.
   *
   * @param \Acquia\ContentHubClient\CDF\CDFObject $cdf
   *   The CDF in hand.
   *
   * @return string
   *   The entity type of the object.
   */
  protected function getEntityType(CDFObject $cdf): string {
    return $cdf->getAttribute('entity_type')->getValue()[LanguageInterface::LANGCODE_NOT_SPECIFIED];
  }

}
