<?php

/**
 * @file
 * Batch API functions to enqueue entities from legacy tracking table.
 */

/**
 * Reads exported entities from old tracking table and enqueues them for export.
 *
 * @param mixed $context
 *   The context object.
 */
function acquia_contenthub_publisher_enqueue_exported_entities(&$context) {
  if (!is_array($context) && !class_implements($context)['ArrayAccess']) {
    throw new \Exception("Queue context must be an array or an object extending ArrayObject");
  }
  $database = \Drupal::database();
  $logger = \Drupal::logger('acquia_contenthub_publisher');
  $query = $database->select('acquia_contenthub_entities_tracking', 't')
    ->fields('t', ['entity_id', 'entity_uuid', 'entity_type']);
  $query->condition('status_export', ['EXPORTED', 'INITIATED', 'QUEUED'], 'IN');
  if (!isset($context['sandbox']['progress'])) {
    $max = $query->countQuery()->execute()->fetchField();
    $context['sandbox']['progress'] = 0;
    $context['finished'] = 0;
    $context['sandbox']['max'] = $max;
    $context['results']['enqueued'] = 0;
    $context['results']['max'] = $max;
  }
  $limit = 50;
  $entity_type_manager = $entity = \Drupal::entityTypeManager();
  // Obtain the list of entities to enqueue.
  $results = $query->range($context['sandbox']['progress'], $limit)->execute();
  foreach ($results as $result) {
    // Enqueue entity if it is not paragraphs.
    if ($result->entity_type !== 'paragraph') {
      $entity = $entity_type_manager->getStorage($result->entity_type)
        ->load($result->entity_id);
      if ($entity) {
        _acquia_contenthub_publisher_enqueue_entity($entity, 'update');
        $logger->info("Enqueue-ing entity for export: {$result->entity_type} - {$result->entity_id}");
        $context['results']['enqueued']++;
      }
      else {
        $message = sprintf("Upgrade : Entity (type = {$result->entity_type}, id = {$result->entity_id}, uuid = {$result->entity_uuid}) could not be loaded. It cannot be enqueued for export.");
        $logger->warning($message);
      }
    }
    // Updating progress.
    $context['sandbox']['progress']++;
  }
  // Did we finish yet?
  $context['finished'] = $context['sandbox']['max'] == 0 ? 1 : $context['sandbox']['progress'] / $context['sandbox']['max'];
  $message = dt('Processed @progress out of @total entities', [
    '@progress' => $context['sandbox']['progress'],
    '@total' => $context['sandbox']['max'],
  ]);
  $logger->warning($message);
}

/**
 * Final function after enqueuing exported entities.
 *
 * Takes care of deleting the legacy tracking table if empty.
 *
 * @param bool $success
 *   The success parameter.
 * @param mixed $results
 *   The results array.
 * @param mixed $operations
 *   The operations array.
 */
function acquia_contenthub_publisher_enqueue_exported_entities_finished($success, $results, $operations) {
  if ($success) {
    $message = t('Enqueued @num entities out of @total for export to Content Hub. Entities not enqueued will be exported as dependencies.', [
      '@num' => $results['enqueued'],
      '@total' => $results['max'],
    ]);
  }
  else {
    $message = t('Finished with an error. Some entities could not be exported. Please review all entities were enqueued for export.');
  }
  \Drupal::messenger()->addStatus($message);
  $database = \Drupal::database();
  // Delete all enqueued items.
  $query = $database->delete('acquia_contenthub_entities_tracking');
  $query->condition('status_export', ['EXPORTED', 'INITIATED', 'QUEUED'], 'IN');
  $query->execute();
  // Delete legacy tracking table if empty.
  $query = $database->select('acquia_contenthub_entities_tracking', 't')
    ->fields('t', ['entity_id']);
  $count = $query->countQuery()->execute()->fetchField();
  if ($count === 0) {
    // If table is empty, drop it.
    \Drupal::database()->schema()->dropTable('acquia_contenthub_entities_tracking');
  }
}
