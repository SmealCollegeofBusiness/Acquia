<?php

namespace Drupal\acquia_contenthub_translations\EventSubscriber\ParseCdf;

use Drupal\acquia_contenthub\AcquiaContentHubEvents;
use Drupal\acquia_contenthub\Event\ParseCdfEntityEvent;
use Drupal\acquia_contenthub_translations\OperationHandler\TranslationDeletionHandler;
use Drupal\Core\Entity\TranslatableInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Handles translation deletions initiated from publishers.
 */
class DeleteUndesiredTranslations implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[AcquiaContentHubEvents::PARSE_CDF][] =
      ['onParseCdf', -100];
    return $events;
  }

  /**
   * The translation deletion handler.
   *
   * @var \Drupal\acquia_contenthub_translations\OperationHandler\TranslationDeletionHandler
   */
  protected $deleteHandler;

  /**
   * Constructs a new object.
   *
   * @param \Drupal\acquia_contenthub_translations\OperationHandler\TranslationDeletionHandler $delete_handler
   *   The deletion handler service.
   */
  public function __construct(TranslationDeletionHandler $delete_handler) {
    $this->deleteHandler = $delete_handler;
  }

  /**
   * Prunes the undesired translations from the site before saving the entity.
   *
   * Handles publisher initiated translation deletion.
   *
   * @param \Drupal\acquia_contenthub\Event\ParseCdfEntityEvent $event
   *   The event.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function onParseCdf(ParseCdfEntityEvent $event): void {
    $entity = $event->getEntity();
    if (!$entity instanceof TranslatableInterface) {
      return;
    }

    $this->deleteHandler->pruneTranslations($entity,
      $event->getCdf()->getMetadata()['languages'] ?? [$event->getCdf()->getMetadata()['default_language']]
    );
  }

}
