<?php

namespace Drupal\Tests\acquia_contenthub\Kernel\EventSubscriber\CdfAttributes;

use Drupal\acquia_contenthub\Event\CdfAttributesEvent;
use Drupal\acquia_contenthub\EventSubscriber\CdfAttributes\TermParentCdfAttribute;
use Drupal\depcalc\DependentEntityWrapper;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\Tests\taxonomy\Traits\TaxonomyTestTrait;

/**
 * Tests term parent cdf attribute.
 *
 * @group acquia_contenthub
 *
 * @coversDefaultClass \Drupal\acquia_contenthub\EventSubscriber\CdfAttributes\TermParentCdfAttribute
 *
 * @requires module depcalc
 *
 * @package Drupal\Tests\acquia_contenthub\Kernel\EventSubscriber\CdfAttributes
 */
class TermParentCdfAttributeTest extends EntityKernelTestBase {

  use TaxonomyTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'acquia_contenthub',
    'depcalc',
    'path_alias',
    'taxonomy',
  ];

  /**
   * The TermParentCdfAttribute object.
   *
   * @var \Drupal\acquia_contenthub\EventSubscriber\CdfAttributes\TermParentCdfAttribute
   */
  protected $termParentCdfAttribute;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('path_alias');
    $this->installEntitySchema('taxonomy_term');

    $this->termParentCdfAttribute = new TermParentCdfAttribute();
  }

  /**
   * Test cases to check term parent cdf attribute.
   *
   * @covers ::onPopulateAttributes
   *
   * @throws \Exception
   */
  public function testTermParentCdfAttribute(): void {
    /** @var \Drupal\taxonomy\VocabularyInterface $vocabulary */
    $vocabulary = $this->createVocabulary();

    /** @var \Drupal\taxonomy\TermInterface $parent_term */
    $parent_term = $this->createTerm($vocabulary);

    // Create a child term and assign the parents above.
    $child_term = $this->createTerm($vocabulary, [
      'vid' => $vocabulary->id(),
      'parent' => $parent_term->id(),
    ]);

    /** @var \Acquia\ContentHubClient\CDF\CDFObject $cdf */
    $cdf = $this->container->get('acquia_contenthub_common_actions')
      ->getLocalCdfDocument($child_term)
      ->getCdfEntity($child_term->uuid());

    $wrapper = new DependentEntityWrapper($child_term);
    $event = new CdfAttributesEvent($cdf, $child_term, $wrapper);
    $this->termParentCdfAttribute->onPopulateAttributes($event);

    $this->assertArrayHasKey('parent', $event->getCdf()->getAttributes());
  }

}
