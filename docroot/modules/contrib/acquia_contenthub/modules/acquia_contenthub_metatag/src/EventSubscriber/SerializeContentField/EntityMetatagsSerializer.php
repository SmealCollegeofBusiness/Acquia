<?php

namespace Drupal\acquia_contenthub_metatag\EventSubscriber\SerializeContentField;

use Drupal\acquia_contenthub\AcquiaContentHubEvents;
use Drupal\acquia_contenthub\Event\SerializeCdfEntityFieldEvent;
use Drupal\acquia_contenthub\EventSubscriber\SerializeContentField\ContentFieldMetadataTrait;
use Drupal\acquia_contenthub\EventSubscriber\SerializeContentField\FallbackFieldSerializer;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Subscribes to entity field serialization to handle metatags values.
 */
class EntityMetatagsSerializer extends FallbackFieldSerializer implements EventSubscriberInterface {

  use ContentFieldMetadataTrait;

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[AcquiaContentHubEvents::SERIALIZE_CONTENT_ENTITY_FIELD][] =
      ['onSerializeContentField', 110];
    return $events;
  }

  /**
   * Extract entity url to update metatag values.
   *
   * @param \Drupal\acquia_contenthub\Event\SerializeCdfEntityFieldEvent $event
   *   The content entity field serialization event.
   *
   * @throws \Exception
   */
  public function onSerializeContentField(SerializeCdfEntityFieldEvent $event) {
    // Bail early if it isn't a metatag field.
    if ($event->getField()->getFieldDefinition()->getType() != 'metatag') {
      return;
    }

    $config = \Drupal::service('config.factory')->getEditable('acquia_contenthub_metatag.settings');
    if ($config->get('ach_metatag_node_url_do_not_transform')) {
      return;
    }

    parent::onSerializeContentField($event);
    $entity = $event->getEntity();

    $langcode = $entity->language()->getId();
    $publisher_node_url = $entity->toUrl()->setAbsolute()->toString();

    $metatag_field_data = $event->getFieldData();
    $metatag_values = unserialize($metatag_field_data['value'][$langcode]['value']);

    $metatags = \Drupal::service('metatag.manager')->tagsFromEntityWithDefaults($entity);
    $metatag_values['canonical_url'] = str_replace('[node:url]', $publisher_node_url, $metatags['canonical_url']);

    $metatag_field_data['value'][$langcode]['value'] = serialize($metatag_values);
    $event->setFieldData($metatag_field_data);
    $event->stopPropagation();
  }

}
