<?php

namespace Drupal\acquia_contenthub\EventSubscriber\ParseCdf;

use Acquia\ContentHubClient\CDF\CDFObject;
use Drupal\acquia_contenthub\AcquiaContentHubEvents;
use Drupal\acquia_contenthub\Event\ParseCdfEntityEvent;
use Drupal\acquia_contenthub\Event\UnserializeAdditionalMetadataEvent;
use Drupal\acquia_contenthub\Event\UnserializeCdfEntityFieldEvent;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Subscribes to parse cdf event.
 *
 * @see \Drupal\acquia_contenthub\AcquiaContentHubEvents::PARSE_CDF
 */
class ContentEntityParseCdfHandler implements EventSubscriberInterface {

  /**
   * Event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $dispatcher;

  /**
   * Constructs a ContentEntityParseCdfHandler object.
   *
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $dispatcher
   *   Event dispatcher.
   */
  public function __construct(EventDispatcherInterface $dispatcher) {
    $this->dispatcher = $dispatcher;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[AcquiaContentHubEvents::PARSE_CDF][] = ['onParseCdf', 100];
    return $events;
  }

  /**
   * Parses the CDF representation of Content Entities.
   *
   * @param \Drupal\acquia_contenthub\Event\ParseCdfEntityEvent $event
   *   The event.
   *
   * @throws \Exception
   */
  public function onParseCdf(ParseCdfEntityEvent $event): void {
    $cdf = $event->getCdf();
    if ($cdf->getType() !== 'drupal8_content_entity') {
      return;
    }

    $default_language = $cdf->getMetadata()['default_language'];
    if (empty($default_language)) {
      throw new \Exception(sprintf('No language available for entity with UUID %s.', $cdf->getUuid()));
    }
    $entity_type_manager = \Drupal::service('entity_type.manager');

    $entity_type_id = $cdf->getAttribute('entity_type')->getValue()['und'];
    $entity_type = $entity_type_manager->getDefinition($entity_type_id);
    $langcode_key = $entity_type->hasKey('langcode') ? $entity_type->getKey('langcode') : 'langcode';
    $entity_values = $this->unserializeFields($cdf, $event, $entity_type);

    if (!$event->isMutable()) {
      return;
    }

    if (!$event->hasEntity()) {
      $entity = $this->createEntity($langcode_key, $default_language, $entity_type_id, $entity_values);
    }
    else {
      $entity = $event->getEntity();
    }

    $langcodes = $cdf->getMetadata()['languages'];
    $this->setTranslations($entity_values, $langcodes, $langcode_key, $entity);

    $entity = $this->dispatchUnserializeAdditionalMetadataEvent($entity, $cdf);
    $event->setEntity($entity);
  }

  /**
   * Decodes base64 encoded metadata.
   *
   * @param string $data
   *   The base64 encoded, json data.
   *
   * @return array
   *   Decoded data.
   */
  public function decodeMetadataContent(string $data): array {
    return json_decode(base64_decode($data), TRUE);
  }

  /**
   * Sets the translation.
   *
   * @param array $entity_values
   *   The values of the given entity fields.
   * @param array $langcodes
   *   The available langcodes.
   * @param string $default_langcode
   *   The default language.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity in question that the translations are being created for.
   *
   * @throws \Exception
   */
  protected function setTranslations(array $entity_values, array $langcodes, string $default_langcode, ContentEntityInterface $entity): void {
    foreach ($entity_values as $langcode => $values) {
      if (!in_array($langcode, $langcodes)) {
        continue;
      }
      $langcode = $this->removeChannelId($langcode);
      $values[$default_langcode] = $langcode;
      if (isset($values['content_translation_source'])) {
        $values['content_translation_source'] = $this->removeChannelId($values['content_translation_source']);
      }

      if (!$entity->hasTranslation($langcode)) {
        try {
          $entity->addTranslation($langcode, $values);
        }
        catch (\InvalidArgumentException $ex) {
          // Still fail but provide information to locate the failing entity.
          throw new \Exception(sprintf("Cannot add translation '%s' for Entity (%s, %s): %s.",
            $langcode,
            $entity->getEntityTypeId(),
            $entity->uuid(),
            $ex->getMessage()
          ));
        }
        return;
      }

      /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
      $entity = $entity->getTranslation($langcode);
      $field_names = array_keys($entity->getFields());
      foreach ($field_names as $name) {
        if (isset($values[$name])) {
          $entity->set($name, $values[$name]);
        }
      }
    }
  }

  /**
   * Creates a new entity.
   *
   * @param string $langcode
   *   Langcode key.
   * @param string $default_language
   *   Default language of entity.
   * @param string $entity_type_id
   *   Entity type id.
   * @param array $entity_values
   *   Entity field values from cdf.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   Created content entity.
   */
  protected function createEntity(string $langcode, string $default_language, string $entity_type_id, array $entity_values): ContentEntityInterface {
    // Entities like redirect don't have default_language field
    // which makes the langcode field missing in the actual values
    // for entity creation leading to incorrect default language.
    if (!array_key_exists($langcode, $entity_values[$default_language])) {
      $entity_values[$default_language][$langcode] = $default_language;
    }
    // If formatted language is different from default language, change it.
    $formatted_default_lang = $this->removeChannelId($default_language);
    if ($formatted_default_lang !== $default_language) {
      $entity_values[$formatted_default_lang] = $entity_values[$default_language];
      $entity_values[$formatted_default_lang][$langcode] = $formatted_default_lang;
      unset($entity_values[$default_language]);
    }

    /** @var \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager */
    $entity_type_manager = \Drupal::service('entity_type.manager');
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $entity_type_manager->getStorage($entity_type_id)
      ->create($entity_values[$formatted_default_lang]);
    unset($entity_values[$formatted_default_lang]);
    return $entity;
  }

  /**
   * Unserializes fields from cdf metadata.
   *
   * @param \Acquia\ContentHubClient\CDF\CDFObject $cdf
   *   Cdf object.
   * @param \Drupal\acquia_contenthub\Event\ParseCdfEntityEvent $event
   *   Parse cdf event.
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   Entity type object.
   *
   * @return array
   *   Unserialized entity field values array.
   */
  protected function unserializeFields(CDFObject $cdf, ParseCdfEntityEvent $event, EntityTypeInterface $entity_type): array {
    $entity_values = [];
    $bundle = $cdf->getAttribute('bundle')->getValue()['und'];
    $fields = $this->decodeMetadataContent($cdf->getMetadata()['data']);
    foreach ($fields as $field_name => $field) {
      if ($field_name === 'uuid' && $event->hasEntity() && $cdf->getUuid() !== $event->getEntity()->uuid()) {
        // Make sure we do not override the uuid of an existing local entity.
        continue;
      }
      $unserialize_event = new UnserializeCdfEntityFieldEvent(
        $entity_type, $bundle, $field_name,
        $field, $cdf->getMetadata()['field'][$field_name],
        $event->getStack()
      );
      $this->dispatcher->dispatch(AcquiaContentHubEvents::UNSERIALIZE_CONTENT_ENTITY_FIELD, $unserialize_event);
      $value = $unserialize_event->getValue();
      $entity_values = NestedArray::mergeDeep($entity_values, $value);
    }
    return $entity_values;
  }

  /**
   * Disptaches event for final alteration.
   *
   * Enables of extending the entity of additional data from the CDF.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity in question.
   * @param \Acquia\ContentHubClient\CDF\CDFObject $cdf
   *   The entity CDF.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   The extended content entity.
   */
  protected function dispatchUnserializeAdditionalMetadataEvent(ContentEntityInterface $entity, CDFObject $cdf): ContentEntityInterface {
    $event = new UnserializeAdditionalMetadataEvent($entity, $cdf);
    $this->dispatcher->dispatch(AcquiaContentHubEvents::UNSERIALIZE_ADDITIONAL_METADATA, $event);
    return $event->getEntity();
  }

  /**
   * Removes channel ID from a langcode.
   *
   * @param string $langcode
   *   The langcode to be formatted.
   *
   * @return null|string|string[]
   *   The new langcode.
   */
  protected function removeChannelId(string $langcode) {
    $pattern = '/(\w+)_(\d+)/i';
    $replacement = '${1}';
    return preg_replace($pattern, $replacement, $langcode);
  }

}
