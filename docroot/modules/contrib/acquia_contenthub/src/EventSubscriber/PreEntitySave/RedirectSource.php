<?php

namespace Drupal\acquia_contenthub\EventSubscriber\PreEntitySave;

use Drupal\acquia_contenthub\AcquiaContentHubEvents;
use Drupal\acquia_contenthub\Event\PreEntitySaveEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Fixes the redirect source field to handle query parameters.
 *
 * @package Drupal\acquia_contenthub\EventSubscriber\PreEntitySave
 */
class RedirectSource implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[AcquiaContentHubEvents::PRE_ENTITY_SAVE][] =
      ['onPreEntitySave', 80];
    return $events;
  }

  /**
   * Adds the query parameter to the Redirect Source Field.
   *
   * @param \Drupal\acquia_contenthub\Event\PreEntitySaveEvent $event
   *   The pre entity save event.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function onPreEntitySave(PreEntitySaveEvent $event) {
    $entity = $event->getEntity();

    // If it is not a redirect then exit.
    if ($entity->getEntityTypeId() !== 'redirect') {
      return;
    }

    $metadata = $event->getCdf()->getMetadata();
    $data = json_decode(base64_decode($metadata['data']), TRUE);
    $redirect_source = $data['redirect_source'];
    $langcode = $entity->getEntityType()->getKey('langcode');
    $language = $entity->get($langcode)->value;
    $query = $redirect_source['value'][$language]['query'] ?? NULL;
    if (!empty($query)) {
      $path = $entity->getSource()['path'];
      $entity->setSource($path, $query);
    }
  }

}
