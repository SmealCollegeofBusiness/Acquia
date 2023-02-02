<?php

namespace Drupal\Tests\acquia_contenthub\Unit;

use Acquia\ContentHubClient\CDF\CDFObject;
use Acquia\ContentHubClient\CDF\CDFObjectInterface;
use Acquia\ContentHubClient\CDFAttribute;
use Drupal\acquia_contenthub\Libs\Traits\EntityCdfWrapperTrait;
use Drupal\Tests\UnitTestCase;

/**
 * Tests helper functions of the trait.
 *
 * @group acquia_contenthub
 */
class EntityCdfWrapperTraitTest extends UnitTestCase {

  use EntityCdfWrapperTrait;

  /**
   * @covers \Drupal\acquia_contenthub\Libs\Traits\EntityCdfWrapperTrait::getEntityType
   */
  public function testGetEntityTypeForEntityCdf(): void {
    $cdf = $this->newCdfObject();
    $cdf->addAttribute('entity_type', CDFAttribute::TYPE_STRING, 'node');

    $actual = $this->getEntityType($cdf);
    $this->assertEquals('node', $actual,
      'Returns the entity_type attribute value of the entity.'
    );

    $cdf = $this->newCdfObject();
    $actual = $this->getEntityType($cdf);
    $this->assertEquals('', $actual,
      'Returns an empty string, because there is no entity type set.'
    );
  }

  /**
   * @covers \Drupal\acquia_contenthub\Libs\Traits\EntityCdfWrapperTrait::getAttributeValueByLanguage
   */
  public function testGetAttributeValueByLanguage(): void {
    $cdf = $this->newCdfObject();
    $cdf->addAttribute('attr', CDFAttribute::TYPE_BOOLEAN, TRUE, 'und');
    $cdf->getAttribute('attr')->setValue(FALSE, 'en');

    $actual = $this->getAttributeValueByLanguage($cdf, 'attr');
    $this->assertEquals(TRUE, $actual);

    $actual = $this->getAttributeValueByLanguage($cdf, 'attr', 'en');
    $this->assertEquals(FALSE, $actual);

    $expected = [
      'key1' => 'val1',
      'key2' => 'val2',
    ];
    $cdf->addAttribute('attr2', CDFAttribute::TYPE_ARRAY_STRING, $expected);
    $actual = $this->getAttributeValueByLanguage($cdf, 'attr2');
    $this->assertEquals($expected, $actual);

    $actual = $this->getAttributeValueByLanguage($cdf, 'non-existing');
    $this->assertEquals(NULL, $actual);
  }

  /**
   * @covers \Drupal\acquia_contenthub\Libs\Traits\EntityCdfWrapperTrait::getAttributeValue
   */
  public function testGetAttributeValue(): void {
    $cdf = $this->newCdfObject();
    $cdf->addAttribute('attr', CDFAttribute::TYPE_INTEGER, 42);
    $cdf->getAttribute('attr')->setValue(42, 'en');
    $cdf->getAttribute('attr')->setValue(42, 'hu');

    $actual = $this->getAttributeValue($cdf, 'attr');
    $this->assertEquals([
      'und' => 42,
      'en' => 42,
      'hu' => 42,
    ], $actual);

    $actual = $this->getAttributeValue($cdf, 'non-existing');
    $this->assertEquals([], $actual);
  }

  /**
   * Returns a new CDFObject for testing purposes.
   *
   * @return \Acquia\ContentHubClient\CDF\CDFObjectInterface
   *   The CDF object.
   */
  protected function newCdfObject(): CDFObjectInterface {
    return new CDFObject(
      'drupal8_content_entity',
      'some_uuid',
      time(),
      time(),
      'some_origin',
      [],
    );
  }

}
