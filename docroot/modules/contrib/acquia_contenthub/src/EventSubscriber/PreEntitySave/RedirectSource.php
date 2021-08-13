<?php

namespace Drupal\acquia_contenthub\EventSubscriber\PreEntitySave;

use Drupal\acquia_contenthub\AcquiaContentHubEvents;
use Drupal\acquia_contenthub\Event\PreEntitySaveEvent;
use Drupal\redirect\Entity\Redirect;
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
   * Adds the query parameter.
   *
   * @param \Drupal\acquia_contenthub\Event\PreEntitySaveEvent $event
   *   The pre entity save event.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function onPreEntitySave(PreEntitySaveEvent $event) {
    $entity = $event->getEntity();

    // If it is not a redirect then exit.
    if ($entity->getEntityTypeId() !== 'redirect') {
      return;
    }

    $data = json_decode(base64_decode($event->getCdf()->getMetadata()['data']), TRUE);
    $redirect_source = $data['redirect_source'];
    $this->setQuery($redirect_source, $entity);
  }

  /**
   * Set query parameter for redirect.
   *
   * @param array $redirect_source
   *   The redirect source.
   * @param \Drupal\redirect\Entity\Redirect $redirect
   *   The redirect entity.
   */
  protected function setQuery(array $redirect_source, Redirect $redirect) {
    $langcode = $redirect->getEntityType()->getKey('langcode');
    $language = $redirect->get($langcode)->value;
    $query = $redirect_source['value'][$language]['query'] ?? [];
    $path = $redirect->getSource()['path'];
    if (!empty($query)) {
      $redirect->setSource($path, $query);
    }
  }

}
