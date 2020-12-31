<?php

namespace Drupal\acquia_contenthub\EventSubscriber\Cdf;

use Acquia\ContentHubClient\CDF\CDFObject;
use Drupal\acquia_contenthub\AcquiaContentHubEvents;
use Drupal\acquia_contenthub\Client\ClientFactory;
use Drupal\acquia_contenthub\Event\CreateCdfEntityEvent;
use Drupal\acquia_contenthub\Event\ParseCdfEntityEvent;
use Drupal\acquia_contenthub\Event\SerializeCdfEntityFieldEvent;
use Drupal\acquia_contenthub\Event\UnserializeCdfEntityFieldEvent;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\depcalc\DependencyCalculator;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * The Content entity CDF creator.
 *
 * @see \Drupal\acquia_contenthub\Event\CreateCdfEntityEvent
 */
class ContentEntityHandler implements EventSubscriberInterface {

  /**
   * The dependency calculator.
   *
   * @var \Drupal\depcalc\DependencyCalculator
   */
  protected $calculator;

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $dispatcher;

  /**
   * The client factory.
   *
   * @var \Drupal\acquia_contenthub\Client\ClientFactory
   */
  protected $clientFactory;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManager
   */
  protected $languageManager;

  /**
   * ContentEntity constructor.
   *
   * @param \Drupal\depcalc\DependencyCalculator $calculator
   *   The dependency calculator.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $dispatcher
   *   The event dispatcher.
   * @param \Drupal\acquia_contenthub\Client\ClientFactory $factory
   *   The client factory.
   */
  public function __construct(DependencyCalculator $calculator, ConfigFactoryInterface $config_factory, EventDispatcherInterface $dispatcher, ClientFactory $factory) {
    $this->calculator = $calculator;
    $this->configFactory = $config_factory;
    $this->dispatcher = $dispatcher;
    $this->clientFactory = $factory;
    $this->languageManager = \Drupal::languageManager();
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[AcquiaContentHubEvents::CREATE_CDF_OBJECT][] = ['onCreateCdf', 100];
    $events[AcquiaContentHubEvents::PARSE_CDF][] = ['onParseCdf', 100];
    return $events;
  }

  /**
   * Creates a new CDF representation of Content Entities.
   *
   * @param \Drupal\acquia_contenthub\Event\CreateCdfEntityEvent $event
   *   Event.
   *
   * @throws \Exception
   */
  public function onCreateCdf(CreateCdfEntityEvent $event) {
    $entity = $event->getEntity();
    if (!$entity instanceof ContentEntityInterface) {
      // Bail early if this isn't a content entity.
      return;
    }

    $settings = $this->clientFactory->getSettings();

    $cdf = new CDFObject('drupal8_content_entity', $entity->uuid(), date('c'), date('c'), $settings->getUuid());
    $metadata = [
      'default_language' => $entity->language()->getId(),
    ];
    if ($dependencies = $event->getDependencies()) {
      $metadata['dependencies'] = $dependencies;
    }
    $cdf->setMetadata($metadata);

    $fields = [];
    foreach ($entity as $field_name => $field) {
      $fieldEvent = new SerializeCdfEntityFieldEvent($entity, $field_name, $field, $cdf);
      $this->dispatcher->dispatch(AcquiaContentHubEvents::SERIALIZE_CONTENT_ENTITY_FIELD, $fieldEvent);
      if ($fieldEvent->isExcluded()) {
        continue;
      }
      $fields[$field_name] = $fieldEvent->getFieldData();
    }
    $metadata = $cdf->getMetadata();
    $metadata['data'] = base64_encode(json_encode($fields));
    $cdf->setMetadata($metadata);
    $event->addCdf($cdf);
  }

  /**
   * Parses the CDF representation of Content Entities.
   *
   * @param \Drupal\acquia_contenthub\Event\ParseCdfEntityEvent $event
   *   Event.
   *
   * @throws \Exception
   */
  public function onParseCdf(ParseCdfEntityEvent $event) {
    $cdf = $event->getCdf();
    if ($cdf->getType() !== 'drupal8_content_entity') {
      // Bail early if this isn't a content entity.
      return;
    }

    $default_language = $cdf->getMetadata()['default_language'];

    // If entity doesn't have any language available, throw an error.
    if (empty($default_language)) {
      throw new \Exception(sprintf("No language available for entity with UUID %s.", $cdf->getUuid()));
    }

    $langcodes = $cdf->getMetadata()['languages'];
    $entity_values = [];
    $entity_type_id = $cdf->getAttribute('entity_type')->getValue()['und'];
    $entity_type = $this->getEntityTypeManager()->getDefinition($entity_type_id);
    $bundle_id = $cdf->getAttribute('bundle')->getValue()['und'];
    foreach (json_decode(base64_decode($cdf->getMetadata()['data']), TRUE) as $field_name => $field) {
      if ($field_name == 'uuid' && $event->hasEntity() && $cdf->getUuid() !== $event->getEntity()->uuid()) {
        // Make sure we do not override the uuid of an existing local entity.
        continue;
      }
      $unserialize_event = new UnserializeCdfEntityFieldEvent($entity_type, $bundle_id, $field_name, $field, $cdf->getMetadata()['field'][$field_name], $event->getStack());
      $this->dispatcher->dispatch(AcquiaContentHubEvents::UNSERIALIZE_CONTENT_ENTITY_FIELD, $unserialize_event);
      $value = $unserialize_event->getValue();
      $entity_values = NestedArray::mergeDeep($entity_values, $value);
    }

    if (!$event->isMutable()) {
      return;
    }

    if (!$event->hasEntity()) {

      // If formatted language is different from default language, change it.
      $formatted_default_language = $this->removeChannelId($default_language);
      if ($formatted_default_language !== $default_language) {
        $entity_values[$formatted_default_language] = $entity_values[$default_language];
        $entity_values[$formatted_default_language]['langcode'] = $formatted_default_language;
        unset($entity_values[$default_language]);
      }

      /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
      $entity = $this->getEntityTypeManager()->getStorage($entity_type_id)->create($entity_values[$formatted_default_language]);
      unset($entity_values[$formatted_default_language]);
    }
    else {
      $entity = $event->getEntity();
    }

    foreach ($entity_values as $langcode => $values) {
      if (!in_array($langcode, $langcodes)) {
        continue;
      }
      $langcode = $this->removeChannelId($langcode);
      $values['langcode'] = $langcode;
      if (isset($values['content_translation_source'])) {
        $values['content_translation_source'] = $this->removeChannelId($values['content_translation_source']);
      }

      if (!$entity->hasTranslation($langcode)) {
        $entity->addTranslation($langcode, $values);
      }
      else {
        /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
        $entity = $entity->getTranslation($langcode);
        foreach ($entity as $field_name => $field) {
          if (isset($values[$field_name])) {
            $entity->set($field_name, $values[$field_name]);
          }
        }
      }
    }
    $event->setEntity($entity);
  }

  /**
   * Get the entity type manager.
   *
   * @return \Drupal\Core\Entity\EntityTypeManagerInterface
   *   Entity type manager service.
   */
  protected function getEntityTypeManager() {
    return \Drupal::entityTypeManager();
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
  protected function removeChannelId($langcode) {
    $pattern = '/(\w+)_(\d+)/i';
    $replacement = '${1}';
    return preg_replace($pattern, $replacement, $langcode);
  }

}
