<?php

namespace Drupal\acquia_contenthub;

use Drupal\Core\Entity\EntityInterface;

/**
 * Audit Pub/Sub track entities.
 */
class AuditTrackEntities {

  /**
   * Checks published entities and compares them with Content Hub.
   *
   * @param array $entities
   *   An array of records from the tracking table.
   * @param bool $reprocess
   *   TRUE to reprocess entities, FALSE to just print.
   * @param string $audit_command
   *   Commands trigger from which site pub or sub.
   * @param mixed $context
   *   The batch context object.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function batchProcess(array $entities, bool $reprocess, string $audit_command, &$context) {
    $uuids = array_column($entities, 'entity_uuid');

    if (!isset($context['sandbox'])) {
      $context['results']['not_published'] = 0;
      $context['results']['outdated'] = 0;
    }

    /** @var \Drupal\acquia_contenthub\Client\ClientFactory $factory */
    $factory = \Drupal::service('acquia_contenthub.client.factory');
    $client = $factory->getClient();
    if (!$client) {
      throw new \Exception(dt('The Content Hub client is not connected so no operations could be performed.'));
    }

    /** @var \Acquia\ContentHubClient\CDF\CDFObject[] $cdfs */
    $cdfs = $client->getEntities($uuids)->getEntities();

    foreach ($entities as $entity) {
      $out_of_sync = FALSE;
      $uuid = $entity['entity_uuid'];

      /** @var \Acquia\ContentHubClient\CDF\CDFObject $ch_entity */
      $ch_entity = $cdfs[$uuid] ?? FALSE;
      if (!$ch_entity) {
        // Entity does not exist in Content Hub.
        self::prepareOutput($entity, 'Entity not published.');
        $out_of_sync = TRUE;
        $context['results']['not_published']++;
      }
      elseif ($entity['hash'] !== $ch_entity->getAttribute('hash')->getValue()['und']) {
        // Entity exists in Content Hub but the hash flag does not match.
        self::prepareOutput($entity, 'Outdated entity.');
        $out_of_sync = TRUE;
        $context['results']['outdated']++;
      }

      if ($out_of_sync) {
        $drupal_entity = \Drupal::entityTypeManager()->getStorage($entity['entity_type'])->load($entity['entity_id']);
        if (!$drupal_entity) {
          // The drupal entity could not be loaded.
          self::prepareOutput($entity, 'This entity exists in the tracking table but could not be loaded in Drupal.');
          continue;
        }
        if ($reprocess) {
          self::enqueueTrackedEntities($drupal_entity, $audit_command);
        }
      }
    }
  }

  /**
   * Output message.
   *
   * @param array $entity
   *   Tracked entity.
   * @param string $msg
   *   Message.
   */
  public static function prepareOutput(array $entity, string $msg) {
    $message = dt("@msg: Entity Type = @type, UUID = @uuid, ID = @id, Modified = @modified", [
      '@msg' => $msg,
      '@type' => $entity['entity_type'],
      '@uuid' => $entity['entity_uuid'],
      '@id' => $entity['entity_id'],
      '@modified' => $entity['modified'],
    ]);
    \Drupal::messenger()->addMessage(dt($message));
  }

  /**
   * Adds entity to publisher export queue.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to enqueue to ContentHub.
   * @param string $audit_command
   *   Commands trigger from pub or sub.
   *
   * @throws \Exception
   */
  public static function enqueueTrackedEntities(EntityInterface $entity, string $audit_command) {
    /** @var \Drupal\acquia_contenthub\PubSubModuleStatusChecker $checker */
    $checker = \Drupal::service('pub.sub_status.checker');
    if ($checker->isPublisher() && $audit_command === 'publisher_audit') {
      /** @var \Drupal\acquia_contenthub_publisher\ContentHubEntityEnqueuer $entity_enqueuer */
      $entity_enqueuer = \Drupal::service('acquia_contenthub_publisher.entity_enqueuer');
      $entity_enqueuer->enqueueEntity($entity, 'update');
    }
    if ($checker->isSubscriber() && $audit_command === 'subscriber_audit') {
      $item = new \stdClass();
      /** @var \Drupal\acquia_contenthub_subscriber\SubscriberTracker $tracker */
      $tracker = \Drupal::service('acquia_contenthub_subscriber.tracker');
      $queue = \Drupal::queue('acquia_contenthub_subscriber_import');
      $item->uuid = $entity->uuid();
      $queue_id = $queue->createItem($item);
      $tracker->queue($entity->uuid());
      $tracker->setQueueItemByUuids([$entity->uuid()], $queue_id);
    }
  }

  /**
   * Batch finish callback.
   *
   * This will inspect the results of the batch and will display a message to
   * indicate how the batch process ended.
   *
   * @param bool $success
   *   The result of batch process.
   * @param array $results
   *   The result of $context.
   * @param array $operations
   *   The operations that were run.
   */
  public function batchFinished(bool $success, array $results, array $operations) {
    if ($success) {
      $msg = dt('Total number of audited entities not found in Content Hub: @total' . PHP_EOL, [
        '@total' => $results['not_published'],
      ]);
      $msg .= dt('Total number of audited entities found outdated in Content Hub: @total', [
        '@total' => $results['outdated'],
      ]);
    }
    else {
      $msg = dt('Finished with a PHP fatal error.');
    }
    \Drupal::messenger()->addStatus($msg);
  }

}
