<?php

namespace Drupal\Tests\acquia_contenthub\Kernel\EventSubscriber\CdfAttributes;

use Acquia\ContentHubClient\CDF\CDFObject;
use Drupal\acquia_contenthub\Event\CdfAttributesEvent;
use Drupal\acquia_contenthub\EventSubscriber\CdfAttributes\EntityLanguagesAttribute;
use Drupal\depcalc\DependentEntityWrapper;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\node\Entity\Node;

/**
 * Tests that Languages attribute is added to entity CDF.
 *
 * @group acquia_contenthub
 * @coversDefaultClass \Drupal\acquia_contenthub\EventSubscriber\CdfAttributes\EntityLanguagesAttribute
 *
 * @requires module depcalc
 *
 * @package Drupal\Tests\acquia_contenthub\Kernel\EventSubscriber\CdfAttributes
 */
class EntityLanguagesAttributeTest extends EntityKernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'acquia_contenthub',
    'depcalc',
    'system',
    'node',
    'language',
  ];

  /**
   * ES default node.
   *
   * @var \Drupal\node\NodeInterface
   */
  private $esDefaultNode;

  /**
   * EN default node.
   *
   * @var \Drupal\node\NodeInterface
   */
  private $enDefaultNode;

  /**
   * Event Subscriber for entity languages.
   *
   * @var \Drupal\acquia_contenthub\EventSubscriber\CdfAttributes\EntityLanguagesAttribute
   */
  protected $entityLanguagesAttribute;

  /**
   * {@inheritDoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->installEntitySchema('node');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['language']);
    $language = ConfigurableLanguage::createFromLangcode('es');
    $language->save();
    $this->enDefaultNode = Node::create([
      'type' => 'article',
      'langcode' => 'en',
      'uid' => 1,
      'title' => 'En default node',
    ]);
    $this->enDefaultNode->save();
    $node_es = $this->enDefaultNode->addTranslation('es');
    $node_es->setTitle('Spanish is secondary language here');
    $node_es->save();
    $this->esDefaultNode = Node::create([
      'type' => 'article',
      'langcode' => 'es',
      'uid' => 1,
      'title' => 'Hola Es default node',
    ]);
    $this->esDefaultNode->save();
    $node_en = $this->esDefaultNode->addTranslation('en');
    $node_en->setTitle('English is secondary language here');
    $node_en->save();
    $this->entityLanguagesAttribute = new EntityLanguagesAttribute();
  }

  /**
   * Tests language array in entity cdf metadata.
   *
   * @throws \Exception
   */
  public function testLanguageMetadata() {
    $en_default_cdf = new CDFObject('drupal8_content_entity', $this->enDefaultNode->uuid(), date('c'), date('c'), 'default-uuid');
    $en_event = new CdfAttributesEvent($en_default_cdf, $this->enDefaultNode, new DependentEntityWrapper($this->enDefaultNode));
    $es_default_cdf = new CDFObject('drupal8_content_entity', $this->esDefaultNode->uuid(), date('c'), date('c'), 'default-uuid');
    $es_event = new CdfAttributesEvent($es_default_cdf, $this->esDefaultNode, new DependentEntityWrapper($this->esDefaultNode));
    $this->entityLanguagesAttribute->onPopulateAttributes($en_event);
    $this->entityLanguagesAttribute->onPopulateAttributes($es_event);
    $new_en_cdf_metadata = $en_event->getCdf()->getMetadata();
    $new_es_cdf_metadata = $es_event->getCdf()->getMetadata();
    $languages_en = $new_en_cdf_metadata['languages'];
    $languages_es = $new_es_cdf_metadata['languages'];
    $this->assertEquals($this->enDefaultNode->language()->getId(), $languages_en[0], 'Assert that default language is always the first in this list.');
    $this->assertEquals($this->esDefaultNode->language()->getId(), $languages_es[0], 'Assert that default language is always the first in this list.');
    $this->assertEqualsCanonicalizing($languages_en, $languages_es, 'Assert that language arrays are similar, just the order is different.');
  }

}
