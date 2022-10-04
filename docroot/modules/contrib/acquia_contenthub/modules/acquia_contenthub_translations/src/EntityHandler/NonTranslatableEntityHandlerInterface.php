<?php

namespace Drupal\acquia_contenthub_translations\EntityHandler;

use Acquia\ContentHubClient\CDF\CDFObject;

/**
 * Provides a unified interface for the handler logic.
 */
interface NonTranslatableEntityHandlerInterface {

  /**
   * Handles the entity_type through the appropriate handler.
   *
   * Receives a context object which provides information about languages and
   * the document itself.
   *
   * @param \Acquia\ContentHubClient\CDF\CDFObject $cdf
   *   The CDF object to handle.
   * @param \Drupal\acquia_contenthub_translations\EntityHandler\Context $context
   *   The current context.
   */
  public function handleEntity(CDFObject $cdf, Context $context): void;

}
