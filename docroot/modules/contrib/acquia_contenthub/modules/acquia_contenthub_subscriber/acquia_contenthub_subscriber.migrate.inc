<?php

/**
 * @file
 * Post-update functions for Content Hub Subscriber.
 */

use Drupal\Core\Entity\RevisionableInterface;

/**
 * @addtogroup updates-8.6.x-contenthub-subscriher-track-imported-entities
 * @{
 */

/**
 * Sends imported entities from legacy tracking table to interest list.
 *
 * @param mixed $context
 *   The context array.
 */
function acquia_contenthub_subscriber_track_imported_linked_entities(&$context) {
  $database = \Drupal::database();
  if (!$database->schema()->tableExists('acquia_contenthub_entities_tracking')) {
    return;
  }
  $import_status_auto_update = [
    'AUTO_UPDATE_ENABLED',
    'PENDING_SYNC',
    'QUEUED',
  ];
  $query = $database->select('acquia_contenthub_entities_tracking', 't')
    ->fields('t', ['entity_id', 'entity_uuid', 'entity_type']);
  $query->condition('status_import', $import_status_auto_update, 'IN');

  if (!isset($context['sandbox']['progress'])) {
    $max = $query->countQuery()->execute()->fetchField();
    $context['sandbox']['progress'] = 0;
    $context['finished'] = 0;
    $context['sandbox']['max'] = $max;
  }

  $limit = 50;

  // Obtain the list of entities to enqueue.
  $results = $query->range($context['sandbox']['progress'], $limit)->execute();
  $uuids = [];
  $subscriber_tracker = \Drupal::service('acquia_contenthub_subscriber.tracker');
  $entity_repository = \Drupal::service('entity.repository');
  foreach ($results as $result) {
    $entity = $entity_repository->loadEntityByUuid($result->entity_type, $result->entity_uuid);
    $subscriber_tracker->track($entity, '');
    $uuids[] = $result->entity_uuid;
    // Updating progress.
    $context['sandbox']['progress']++;
  }

  /** @var \Drupal\acquia_contenthub\Client\ClientFactory $client_factory */
  $client_factory = \Drupal::service('acquia_contenthub.client.factory');
  $webhook = \Drupal::configFactory()->get('acquia_contenthub.admin_settings')->get('webhook');
  $webhook_uuid = $webhook['uuid'] ?? NULL;
  if ($webhook_uuid && $uuids) {
    // Adding imported entities to interest list.
    $settings = $client_factory->getSettings();
    $client_factory
      ->getClient($settings)
      ->addEntitiesToInterestList($webhook_uuid, $uuids);
    $logger = \Drupal::logger('acquia_contenthub_subscriber');
    $logger->debug(dt('Added %count out of %total entities to the interest list.', [
      '%count' => $context['sandbox']['progress'],
      '%total' => $context['sandbox']['max'],
    ]));
  }

  // Did we finish yet?
  if ($context['sandbox']['progress'] != $context['sandbox']['max']) {
    $context['finished'] = $context['sandbox']['progress'] / $context['sandbox']['max'];
  }
  else {
    $context['finished'] = 1;

    // Delete all dependent entities. They will be imported as dependencies.
    $database->delete('acquia_contenthub_entities_tracking')
      ->condition('status_import', 'IS_DEPENDENT', '=')
      ->execute();

    $database->delete('acquia_contenthub_entities_tracking')
      ->condition('status_import', $import_status_auto_update, 'IN')
      ->execute();
  }
}

/**
 * Stores imported entities with local changes to a state variable.
 *
 * @param mixed $context
 *   The context array.
 */
function acquia_contenthub_subscriber_track_imported_unlinked_entities(&$context) {
  $database = \Drupal::database();
  if (!$database->schema()->tableExists('acquia_contenthub_entities_tracking')) {
    return;
  }
  $logger = \Drupal::logger('acquia_contenthub_subscriber');
  $entity_type_manager = \Drupal::entityTypeManager();
  $import_status_no_auto_update = [
    'AUTO_UPDATE_DISABLED',
    'HAS_LOCAL_CHANGE',
  ];
  $query = $database->select('acquia_contenthub_entities_tracking', 't')
    ->fields('t', ['entity_id', 'entity_uuid', 'entity_type']);
  $query->condition('status_import', $import_status_no_auto_update, 'IN');

  if (!isset($context['sandbox']['progress'])) {
    $max = $query->countQuery()->execute()->fetchField();
    $context['sandbox']['progress'] = 0;
    $context['finished'] = 0;
    $context['sandbox']['max'] = $max;
  }

  $limit = 50;

  // Obtain the list of entities to enqueue.
  $results = $query->range($context['sandbox']['progress'], $limit)->execute();
  $unlinked = [];
  foreach ($results as $result) {
    $uuid = $result->entity_uuid;
    if ($entity = $entity_type_manager->getStorage($result->entity_type)->load($result->entity_id)) {
      $revision_id = NULL;
      if ($entity instanceof RevisionableInterface) {
        $revision_id = $entity->getRevisionId() ?: NULL;
      }
      // Collect entity id, type and revision_id from all imported entities
      // with local changes or disconnected from Content Hub.
      $unlinked[$uuid] = [
        'id' => $result->entity_id,
        'uuid' => $result->entity_uuid,
        'type' => $result->entity_type,
        'revision_id' => $revision_id,
      ];
      $logger->debug(sprintf('Entity disconnected from Content Hub: (id = %s, UUID = %s, type = %s, revision_id = %s)', $result->entity_id, $result->entity_uuid, $result->entity_type, $revision_id));
    }
    // Updating progress.
    $context['sandbox']['progress']++;
  }

  if (!empty($unlinked)) {
    $state = \Drupal::state();
    $disconnected_entities = $state->get('acquia_contenthub_update_82001_disconnected_entities', []);
    $disconnected_entities = array_merge($disconnected_entities, $unlinked);
    $state->set('acquia_contenthub_update_82001_disconnected_entities', $disconnected_entities);

  }

  // Did we finish yet?
  if ($context['sandbox']['progress'] != $context['sandbox']['max']) {
    $context['finished'] = $context['sandbox']['max'] > 0 ? $context['sandbox']['progress'] / $context['sandbox']['max'] : 1;
  }
  else {
    $context['finished'] = 1;

    // Delete the unlinked entities from the tracking table.
    $database->delete('acquia_contenthub_entities_tracking')
      ->condition('status_import', $import_status_no_auto_update, 'IN')
      ->execute();

    // Delete legacy tracking table if empty.
    $query = $database->select('acquia_contenthub_entities_tracking', 't')
      ->fields('t', ['entity_id', 'entity_uuid', 'entity_type']);
    $count = $query->countQuery()->execute()->fetchField();
    if ($count == 0) {
      // If table is empty, drop it.
      $database->schema()->dropTable('acquia_contenthub_entities_tracking');
    }
  }
}

/**
 * @} End of "addtogroup updates-8.6.x-contenthub-subscriher-track-imported-entities.
 */
