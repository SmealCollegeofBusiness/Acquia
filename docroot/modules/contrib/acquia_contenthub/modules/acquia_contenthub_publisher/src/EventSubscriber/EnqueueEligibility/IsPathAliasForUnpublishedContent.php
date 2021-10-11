<?php

namespace Drupal\acquia_contenthub_publisher\EventSubscriber\EnqueueEligibility;

use Drupal\acquia_contenthub_publisher\ContentHubPublisherEvents;
use Drupal\acquia_contenthub_publisher\EntityModeratedRevision;
use Drupal\acquia_contenthub_publisher\Event\ContentHubEntityEligibilityEvent;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\path_alias\PathAliasInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Routing\Matcher\UrlMatcherInterface;

/**
 * Subscribes to entity eligibility to prevent enqueueing path alias.
 */
class IsPathAliasForUnpublishedContent implements EventSubscriberInterface {

  /**
   * The url matcher.
   *
   * @var \Symfony\Component\Routing\Matcher\UrlMatcherInterface
   */
  protected $matcher;

  /**
   * The Entity Moderated Revision Service.
   *
   * @var \Drupal\acquia_contenthub_publisher\EntityModeratedRevision
   */
  protected $entityModeratedRevision;

  /**
   * The acquia_contenthub_publisher logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $achLoggerChannel;

  /**
   * IsPathAliasForUnpublishedContent constructor.
   *
   * @param \Symfony\Component\Routing\Matcher\UrlMatcherInterface $matcher
   *   URL Matcher service.
   * @param \Drupal\acquia_contenthub_publisher\EntityModeratedRevision $entity_moderated_revision
   *   Entity Moderated Revision Service.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger_channel
   *   The acquia_contenthub_publisher logger channel.
   */
  public function __construct(UrlMatcherInterface $matcher, EntityModeratedRevision $entity_moderated_revision, LoggerChannelInterface $logger_channel) {
    $this->matcher = $matcher;
    $this->entityModeratedRevision = $entity_moderated_revision;
    $this->achLoggerChannel = $logger_channel;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[ContentHubPublisherEvents::ENQUEUE_CANDIDATE_ENTITY][] =
      ['onEnqueueCandidateEntity', 50];
    return $events;
  }

  /**
   * Prevent path aliases from enqueueing unpublished content.
   *
   * @param \Drupal\acquia_contenthub_publisher\Event\ContentHubEntityEligibilityEvent $event
   *   The event to determine entity eligibility.
   *
   * @throws \Exception
   */
  public function onEnqueueCandidateEntity(ContentHubEntityEligibilityEvent $event) {
    // Never export path alias dependencies that are not "published" .
    $entity = $event->getEntity();
    if (!($entity instanceof PathAliasInterface)) {
      return;
    }
    try {
      $params = $this->matcher->match($entity->getPath());
      foreach ($params['_raw_variables']->keys() as $parameter) {
        if (!empty($params[$parameter])) {
          $entity_dependency = $params[$parameter];
          if ($entity_dependency instanceof EntityInterface && !$this->entityModeratedRevision->isPublishedRevision($entity_dependency)) {
            $event->setEligibility(FALSE);
            $event->stopPropagation();
          }
        }
      }
    }
    catch (\Exception $e) {
      $this->achLoggerChannel->error(
        'Following error occurred while trying to get the matching entity for path alias with uuid: @uuid. Error: @error',
        [
          '@uuid' => $entity->uuid(),
          '@error' => $e->getMessage(),
        ]
      );
      // Set the eligibility false as this path alias is a problematic entity.
      $event->setEligibility(FALSE);
      $event->stopPropagation();
    }
  }

}
