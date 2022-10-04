<?php

namespace Drupal\acquia_contenthub_translations\EventSubscriber\EntityDataTamper;

use Drupal\acquia_contenthub\AcquiaContentHubEvents;
use Drupal\acquia_contenthub\Event\EntityDataTamperEvent;
use Drupal\acquia_contenthub_translations\EntityTranslationManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Normalizes field values of CDF if the original language has changed.
 */
class NormalizeFieldValues implements EventSubscriberInterface {

  /**
   * The translation manager service.
   *
   * @var \Drupal\acquia_contenthub_translations\EntityTranslationManagerInterface
   */
  protected $translationManager;

  /**
   * Constructs a new object.
   *
   * @param \Drupal\acquia_contenthub_translations\EntityTranslationManagerInterface $manager
   *   The translation manager service.
   */
  public function __construct(EntityTranslationManagerInterface $manager) {
    $this->translationManager = $manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[AcquiaContentHubEvents::ENTITY_DATA_TAMPER][] = 'onDataTamper';
    return $events;
  }

  /**
   * Tamper with CDF data before its imported.
   *
   * @param \Drupal\acquia_contenthub\Event\EntityDataTamperEvent $event
   *   The data tamper event.
   */
  public function onDataTamper(EntityDataTamperEvent $event): void {
    foreach ($event->getCdf()->getEntities() as $uuid => $object) {
      $metadata = $object->getMetadata();
      if (!isset($metadata['translatable']) || $metadata['translatable']) {
        continue;
      }

      $tracked = $this->translationManager->getTrackedEntity($uuid);
      if (!$tracked) {
        continue;
      }

      $lang = $tracked->defaultLanguage();
      $orig = $tracked->originalDefaultLanguage();
      if ($lang === $orig) {
        continue;
      }

      $field_values = json_decode(base64_decode($metadata['data']), TRUE);
      $normalized_values = [];
      foreach ($field_values as $name => $value) {
        $field_value = $value['value'][$orig];
        $normalized_values[$name]['value'][$lang] = $field_value;
      }
      $metadata['data'] = base64_encode(json_encode($normalized_values));
      $object->setMetadata($metadata);
    }
  }

}
