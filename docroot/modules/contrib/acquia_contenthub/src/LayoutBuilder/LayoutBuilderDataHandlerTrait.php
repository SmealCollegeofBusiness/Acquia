<?php

namespace Drupal\acquia_contenthub\LayoutBuilder;

use Drupal\layout_builder\Plugin\Block\InlineBlock;
use Drupal\layout_builder\Section;
use Drupal\layout_builder\SectionComponent;

/**
 * Layout Builder trait for handling data.
 */
trait LayoutBuilderDataHandlerTrait {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Prepares Layout Builder sections to be serialized.
   *
   * @param \Drupal\layout_builder\Section[] $sections
   *   Sections for layout.
   *
   * @return array
   *   The prepared Layout Builder sections.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function serializeSections(Section ...$sections) { // @codingStandardsIgnoreLine
    $return = [];
    foreach ($sections as $section) {
      $this->serializeComponents($section->getComponents());
      $return[] = $section->toArray();
    }
    return $return;
  }

  /**
   * Prepares component to be serialized.
   *
   * @param \Drupal\layout_builder\SectionComponent[] $components
   *   The component to add.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function serializeComponents(array $components) {
    foreach ($components as $component) {
      $plugin = $component->getPlugin();
      // @todo Decide if it's worth to handle this as an event.
      if ($plugin instanceof InlineBlock) {
        $revision_id = $plugin->getConfiguration()['block_revision_id'];
        $entity = $this->entityTypeManager->getStorage('block_content')->loadRevision($revision_id);
        $component->set('block_uuid', $entity->uuid());
      }
    }
  }

  /**
   * Prepares Layout Builder sections to be unserialized.
   *
   * @param array $sections
   *   The Layout Builder sections to unserialize.
   *
   * @return array
   *   The prepared sections.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function unserializeSections(array $sections) {
    $values = [];
    foreach ($sections as $sectionArray) {
      $section = $sectionArray instanceof Section ? $sectionArray : Section::fromArray($sectionArray['section']);
      $this->unserializeComponents($section->getComponents());
      $values[] = $section;
    }
    return $values;
  }

  /**
   * Prepares Layout Builder components to be unserialized.
   *
   * @param \Drupal\layout_builder\SectionComponent[] $components
   *   The components to unserialize.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function unserializeComponents(array $components) {
    foreach ($components as $component) {
      $plugin = $component->getPlugin();
      // @todo Decide if it's worth to handle this as an event.
      if ($plugin instanceof InlineBlock) {
        $block_uuid = $component->get('block_uuid');
        $entities = $this->entityTypeManager->getStorage('block_content')->loadByProperties(['uuid' => $block_uuid]);
        $entity = array_shift($entities);
        $componentConfiguration = $this->getComponentConfiguration($component);
        $componentConfiguration['block_revision_id'] = $entity->getRevisionId();
        $component->setConfiguration($componentConfiguration);
      }
    }
  }

  /**
   * Gets configuration for a Layout Builder component.
   *
   * @param \Drupal\layout_builder\SectionComponent $component
   *   The Layout Builder component.
   *
   * @return array
   *   The component configuration.
   *
   * @todo Check pending patch to make SectionComponent::getConfiguration() public: https://www.drupal.org/project/drupal/issues/3046814
   */
  protected function getComponentConfiguration(SectionComponent $component) {
    $method = new \ReflectionMethod('\Drupal\layout_builder\SectionComponent', 'getConfiguration');
    $method->setAccessible(TRUE);

    return $method->invoke($component);
  }

}
