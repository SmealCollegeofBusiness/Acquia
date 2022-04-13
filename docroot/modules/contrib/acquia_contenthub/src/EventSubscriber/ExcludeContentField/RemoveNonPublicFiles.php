<?php

namespace Drupal\acquia_contenthub\EventSubscriber\ExcludeContentField;

use Drupal\acquia_contenthub\Event\ExcludeEntityFieldEvent;
use Drupal\acquia_contenthub\Libs\Traits\RemoveNonPublicFilesTrait;
use Drupal\acquia_contenthub\Plugin\FileSchemeHandler\FileSchemeHandlerManagerInterface;

/**
 * Subscribes to field exclusion event for non-public files.
 */
class RemoveNonPublicFiles extends ExcludeContentFieldBase {

  use RemoveNonPublicFilesTrait;

  /**
   * The file scheme handler manager.
   *
   * @var \Drupal\acquia_contenthub\Plugin\FileSchemeHandler\FileSchemeHandlerManagerInterface
   */
  protected $manager;

  /**
   * {@inheritdoc}
   */
  public static $priority = 1001;

  /**
   * RemoveNonPublicFiles constructor.
   *
   * @param \Drupal\acquia_contenthub\Plugin\FileSchemeHandler\FileSchemeHandlerManagerInterface $manager
   *   The file scheme handler manager service.
   */
  public function __construct(FileSchemeHandlerManagerInterface $manager) {
    $this->manager = $manager;
  }

  /**
   * {@inheritDoc}
   */
  public function shouldExclude(ExcludeEntityFieldEvent $event): bool {
    $field = $event->getField();
    return !$this->includeField($field, $this->manager);
  }

}
