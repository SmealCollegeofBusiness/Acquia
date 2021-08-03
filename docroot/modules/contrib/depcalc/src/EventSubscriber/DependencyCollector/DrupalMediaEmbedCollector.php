<?php

namespace Drupal\depcalc\EventSubscriber\DependencyCollector;

use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\depcalc\DependencyCalculatorEvents;
use Drupal\depcalc\DependentEntityWrapper;
use Drupal\depcalc\Event\CalculateEntityDependenciesEvent;
use Drupal\depcalc\FieldExtractor;
use Drupal\field\Entity\FieldStorageConfig;

/**
 * Class DrupalMediaEmbedCollector.
 */
class DrupalMediaEmbedCollector extends BaseDependencyCollector {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[DependencyCalculatorEvents::CALCULATE_DEPENDENCIES][] = ['onCalculateDependencies'];
    return $events;
  }

  /**
   * Calculates media entities embedded into the text areas of other entities.
   *
   * @param \Drupal\depcalc\Event\CalculateEntityDependenciesEvent $event
   *   The CalculateEntityDependenciesEvent event.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function onCalculateDependencies(CalculateEntityDependenciesEvent $event) {

    $entity = $event->getEntity();

    if (!$entity instanceof ContentEntityInterface) {
      return;
    }
    $fields = FieldExtractor::getFieldsFromEntity($entity,
      function(ContentEntityInterface $entity, $field_name, FieldItemListInterface $field) {
        $storage_definition = $field->getFieldDefinition()->getFieldStorageDefinition();
        return (
          $storage_definition instanceof FieldStorageConfig &&
          $storage_definition->getTypeProvider() === 'text'
        );
      }
    );
    foreach ($fields as $field) {
      foreach ($field->getValue() as $value) {
        if (empty($value['format'])) {
          continue;
        }
        /** @var \Drupal\filter\Entity\FilterFormat $filter_format */
        $filter_format = \Drupal::entityTypeManager()->getStorage('filter_format')->load($value['format']);
        $filters = $filter_format->filters();
        $filters->sort();
        /** @var \Drupal\filter\Plugin\FilterInterface $filter */
        foreach ($filters as $filter) {
          // If this text area can have entities embedded, we want to
          // manually extract the entities contained therein.
          $text = $value['value'];
          if ($filter->getPluginId() !== 'media_embed' ||
              stristr($text, '<drupal-media') === FALSE
          ) {
            continue;
          }

          $dom = Html::load($text);
          $xpath = new \DOMXPath($dom);

          foreach ($xpath->query('//drupal-media[@data-entity-type="media" and normalize-space(@data-entity-uuid)!=""]') as $node) {
            /** @var \DOMElement $node */
            $entity_type = $node->getAttribute('data-entity-type');
            if ($uuid = $node->getAttribute('data-entity-uuid')) {
              $embed = \Drupal::entityTypeManager()->getStorage($entity_type)->loadByProperties(['uuid' => $uuid]);
              if (!is_array($embed)) {
                continue;
              }
              $embed = current($embed);
              $embed_wrapper = new DependentEntityWrapper($embed);
              $local_dependencies = [];
              $this->mergeDependencies($embed_wrapper, $event->getStack(), $this->getCalculator()
                ->calculateDependencies($embed_wrapper, $event->getStack(), $local_dependencies));
              $event->addDependency($embed_wrapper);
            }

          }

        }
      }
    }
  }

}
