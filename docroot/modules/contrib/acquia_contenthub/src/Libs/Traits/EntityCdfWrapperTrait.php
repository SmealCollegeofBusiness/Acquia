<?php

namespace Drupal\acquia_contenthub\Libs\Traits;

use Acquia\ContentHubClient\CDF\CDFObjectInterface;
use Drupal\Core\Language\LanguageInterface;

/**
 * Wraps around a CDF which is of type entity for convenience.
 */
trait EntityCdfWrapperTrait {

  /**
   * Returns the entity type.
   *
   * @param \Acquia\ContentHubClient\CDF\CDFObjectInterface $cdf
   *   The CDF object in hand.
   *
   * @return string
   *   The entity's type.
   */
  public function getEntityType(CDFObjectInterface $cdf): string {
    return (string) $this->getAttributeValueByLanguage($cdf, 'entity_type');
  }

  /**
   * Returns and attribute value based on the langcode provided.
   *
   * @param \Acquia\ContentHubClient\CDF\CDFObjectInterface $cdf
   *   The CDF object in hand.
   * @param string $attribute
   *   The attribute's name.
   * @param string $langcode
   *   (Optional = 'und') The langcode key which the value is stored at.
   *
   * @return mixed|null
   *   NULL if the value or the langcode key doesn't exist.
   */
  public function getAttributeValueByLanguage(CDFObjectInterface $cdf, string $attribute, string $langcode = LanguageInterface::LANGCODE_NOT_SPECIFIED) {
    $vals = $this->getAttributeValue($cdf, $attribute);
    if (empty($vals)) {
      return NULL;
    }
    return $vals[$langcode] ?? NULL;
  }

  /**
   * Returns the values of a given attribute or an empty array.
   *
   * @param \Acquia\ContentHubClient\CDF\CDFObjectInterface $cdf
   *   The CDF object in hand.
   * @param string $attribute
   *   The attribute's name.
   *
   * @return array
   *   The values (keyed by langcode) of the attribute.
   */
  public function getAttributeValue(CDFObjectInterface $cdf, string $attribute): array {
    $attr = $cdf->getAttribute($attribute);
    if (is_null($attr)) {
      return [];
    }
    return $attr->getValue();
  }

}
