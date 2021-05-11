<?php

namespace Drupal\acquia_contenthub_moderation\EventSubscriber\PreEntitySave;

use Drupal\acquia_contenthub\AcquiaContentHubEvents;
use Drupal\acquia_contenthub\Event\PreEntitySaveEvent;
use Drupal\content_moderation\ModerationInformationInterface;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Sets the entity with a forward revision and change of moderation state.
 *
 * @package Drupal\acquia_contenthub\EventSubscriber\PreEntitySave
 */
class CreateModeratedForwardRevision implements EventSubscriberInterface {

  /**
   * The field name that stores content moderation states.
   *
   * @var string
   */
  protected $fieldName = 'moderation_state';

  /**
   * The Entity Type Manager Service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Configuration for Content Hub Moderation.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * The Content Moderation Information Interface.
   *
   * @var \Drupal\content_moderation\ModerationInformationInterface
   */
  protected $moderationInfo;

  /**
   * CreateModeratedForwardRevision constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The Entity Type Manager Service.
   * @param \Drupal\Core\Config\ConfigFactory $config_factory
   *   The Config Factory.
   * @param \Drupal\content_moderation\ModerationInformationInterface $moderation_info
   *   The Content Moderation Information Service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ConfigFactory $config_factory, ModerationInformationInterface $moderation_info) {
    $this->entityTypeManager = $entity_type_manager;
    $this->config = $config_factory->get('acquia_contenthub_moderation.settings');
    $this->moderationInfo = $moderation_info;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    // Priority is low so that this is one of the last things to be changed.
    $events[AcquiaContentHubEvents::PRE_ENTITY_SAVE][] = ['onPreEntitySave', 5];
    return $events;
  }

  /**
   * Creates a forward revision and state change for entities being imported.
   *
   * @param \Drupal\acquia_contenthub\Event\PreEntitySaveEvent $event
   *   The pre entity save event.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function onPreEntitySave(PreEntitySaveEvent $event) {
    $entity = $event->getEntity();
    if (!($entity instanceof ContentEntityInterface)) {
      // Only Content Entities can be moderated.
      return;
    }

    $entity_type = $entity->getEntityType();
    $bundle = $entity->bundle();
    if (!$bundle) {
      return;
    }

    // If this bundle is not moderated, nothing needs to be done.
    if (!$this->moderationInfo->shouldModerateEntitiesOfBundle($entity_type, $bundle)) {
      return;
    }

    // Is there a workflow moderation state defined for imported entities.
    $workflow = $this->moderationInfo->getWorkflowForEntity($entity);
    if (!$workflow) {
      return;
    }
    $import_moderation_state = $this->config->get("workflows.{$workflow->id()}.moderation_state");

    // Check whether the entity is configured to create a new revision.
    if ($entity instanceof RevisionableInterface && $entity->isNewRevision() && !empty($import_moderation_state)) {
      // Use configured moderation state.
      $entity->set($this->fieldName, $import_moderation_state);
      // Make it a forward revision if import moderation state is
      // not a "published" state.
      $published_state = $workflow->getTypePlugin()->getState($import_moderation_state)->isPublishedState();
      if (!$published_state) {
        $entity->isDefaultRevision(FALSE);
      }
    }
  }

}
