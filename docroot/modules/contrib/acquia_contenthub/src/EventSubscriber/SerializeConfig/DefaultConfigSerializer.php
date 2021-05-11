<?php

namespace Drupal\acquia_contenthub\EventSubscriber\SerializeConfig;

use Drupal\acquia_contenthub\AcquiaContentHubEvents;
use Drupal\acquia_contenthub\Event\ConfigDataEvent;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\language\ConfigurableLanguageManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Serializes default configuration entities.
 */
class DefaultConfigSerializer implements EventSubscriberInterface {

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The language manager service.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface|\Drupal\language\ConfigurableLanguageManagerInterface
   */
  protected $languageManager;

  /**
   * DefaultConfigSerializer constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   */
  public function __construct(ConfigFactoryInterface $config_factory, LanguageManagerInterface $language_manager) {
    $this->configFactory = $config_factory;
    $this->languageManager = $language_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = [];
    $events[AcquiaContentHubEvents::SERIALIZE_CONFIG_ENTITY][] =
      ['onSerializeConfigEntity'];
    return $events;
  }

  /**
   * The default serializer for config entities basic data.
   *
   * @param \Drupal\acquia_contenthub\Event\ConfigDataEvent $event
   *   The config data event.
   */
  public function onSerializeConfigEntity(ConfigDataEvent $event) {
    $entity = $event->getEntity();
    $entity_type = $entity->getEntityType();
    $config_name = $entity_type->getConfigPrefix() . '.' . $entity->get($entity_type->getKey('id'));
    $config = $this->configFactory->get($config_name);

    $data = $event->getData();
    // Use NestedArray::mergeDeepArray() and preserve the integer keys to
    // prevent overriding or duplicating any config data that was already
    // serialized by other handlers. We duplicate this approach for language
    // overrides.
    $data[$entity->language()->getId()] =
      !empty($data[$entity->language()->getId()]) ?
        NestedArray::mergeDeepArray([
          $data[$entity->language()->getId()],
          $config->getRawData(),
        ], TRUE) : $config->getRawData();
    if ($this->languageManager instanceof ConfigurableLanguageManagerInterface) {
      foreach ($this->languageManager->getLanguages() as $langcode => $language) {
        if ($langcode === $entity->language()->getId()) {
          continue;
        }

        /** @var \Drupal\language\Config\LanguageConfigOverride $language_config_override */
        $language_config_override = $this->languageManager->getLanguageConfigOverride($langcode, $config_name);
        $overridden_config = $language_config_override->get();
        if ($overridden_config) {
          $data[$langcode] = !empty($data[$langcode]) ?
            NestedArray::mergeDeepArray([
              $data[$langcode],
              $overridden_config,
            ], TRUE) : $overridden_config;
        }
      }
    }
    $event->setData($data);
  }

}
