<?php

namespace Drupal\acquia_contenthub_translations\EntityHandler;

use Acquia\ContentHubClient\CDF\CDFObject;
use Drupal\Core\Language\LanguageInterface;

/**
 * Handles removable entities.
 *
 * An entity is removable if a translation gets created as a new entity like in
 * case of redirect or path_alias.
 */
class Removable implements NonTranslatableEntityHandlerInterface {

  /**
   * {@inheritdoc}
   */
  public function handleEntity(CDFObject $cdf, Context $context): void {
    $lang = $cdf->getMetadata()['default_language'];
    if ($lang !== LanguageInterface::LANGCODE_NOT_SPECIFIED) {
      $context->getCdfDocument()->removeCdfEntity($cdf->getUuid());
      $context->addToRemovables($cdf->getUuid());
    }
  }

}
