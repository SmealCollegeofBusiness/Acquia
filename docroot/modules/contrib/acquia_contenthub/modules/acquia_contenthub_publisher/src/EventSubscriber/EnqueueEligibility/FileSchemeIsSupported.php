<?php

namespace Drupal\acquia_contenthub_publisher\EventSubscriber\EnqueueEligibility;

use Drupal\acquia_contenthub\Plugin\FileSchemeHandler\FileSchemeHandlerManagerInterface;
use Drupal\acquia_contenthub_publisher\ContentHubPublisherEvents;
use Drupal\acquia_contenthub_publisher\Event\ContentHubEntityEligibilityEvent;
use Drupal\file\FileInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Prevents enqueueing files whose scheme is not supported.
 */
class FileSchemeIsSupported implements EventSubscriberInterface {

  /**
   * The File Scheme Handler Manager Interface.
   *
   * @var \Drupal\acquia_contenthub\Plugin\FileSchemeHandler\FileSchemeHandlerManagerInterface
   */
  protected $fileSchemeHandler;

  /**
   * ImportedEntity constructor.
   *
   * @param \Drupal\acquia_contenthub\Plugin\FileSchemeHandler\FileSchemeHandlerManagerInterface $file_scheme_handler
   *   The File Scheme Handler Manager Interface.
   */
  public function __construct(FileSchemeHandlerManagerInterface $file_scheme_handler) {
    $this->fileSchemeHandler = $file_scheme_handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[ContentHubPublisherEvents::ENQUEUE_CANDIDATE_ENTITY][] =
      ['onEnqueueCandidateEntity', 60];
    return $events;
  }

  /**
   * Prevent files with unsupported schemes to be enqueued.
   *
   * @param \Drupal\acquia_contenthub_publisher\Event\ContentHubEntityEligibilityEvent $event
   *   The event to determine entity eligibility.
   *
   * @throws \Exception
   */
  public function onEnqueueCandidateEntity(ContentHubEntityEligibilityEvent $event) {
    // If this is a file with an unsupported scheme then do not export it.
    $entity = $event->getEntity();
    if ($entity instanceof FileInterface && !$this->fileSchemeHandler->isSupportedFileScheme($entity)) {
      $event->setEligibility(FALSE);
      $event->stopPropagation();
    }
  }

}
