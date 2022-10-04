<?php

namespace Drupal\acquia_contenthub_translations\EntityHandler;

use Acquia\ContentHubClient\CDF\CDFObject;
use Drupal\Core\Language\LanguageInterface;
use Psr\Log\LoggerInterface;

/**
 * Handles entities without associated handlers.
 */
class Unspecified implements NonTranslatableEntityHandlerInterface {

  /**
   * The acquia_contenthub_translation logger channel.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Handler registry.
   *
   * @var \Drupal\acquia_contenthub_translations\EntityHandler\HandlerRegistry
   */
  protected $registry;

  /**
   * Constructs a new object.
   *
   * @param \Psr\Log\LoggerInterface $logger
   *   The acquia_contenthub_translations logger channel.
   * @param \Drupal\acquia_contenthub_translations\EntityHandler\HandlerRegistry $registry
   *   Handler registry service.
   */
  public function __construct(LoggerInterface $logger, HandlerRegistry $registry) {
    $this->logger = $logger;
    $this->registry = $registry;
  }

  /**
   * {@inheritdoc}
   */
  public function handleEntity(CDFObject $cdf, Context $context): void {
    $entity_type = $cdf->getAttribute('entity_type')->getValue()[LanguageInterface::LANGCODE_NOT_SPECIFIED];
    if ($this->registry->isUnspecified($entity_type)) {
      return;
    }
    // @todo link public documentation.
    $this->logger->warning(sprintf(
      'The entity type "%s", does not have a specific translation handler, adding it to unspecified list. Please evaluate manually!',
      $entity_type
    ));
    $this->registry->addToUnspecified($entity_type);
  }

}
