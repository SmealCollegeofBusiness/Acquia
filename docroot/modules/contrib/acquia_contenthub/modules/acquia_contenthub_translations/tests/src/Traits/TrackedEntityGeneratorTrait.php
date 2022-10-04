<?php

namespace Drupal\Tests\acquia_contenthub_translations\Traits;

use Drupal\acquia_contenthub_translations\Data\TrackedEntity;
use Drupal\acquia_contenthub_translations\EntityTranslationManagerInterface;
use Drupal\Component\Utility\Random;
use Drupal\Tests\acquia_contenthub\Traits\CommonRandomGenerator;

/**
 * Contains TrackedEntity generator method.
 */
trait TrackedEntityGeneratorTrait {

  use CommonRandomGenerator;

  /**
   * Returns a new TrackedEntity object.
   *
   * @param \Drupal\acquia_contenthub_translations\EntityTranslationManagerInterface $manager
   *   The EntityTranslationManagerInterface service.
   * @param callable|null $modify_values
   *   Anonymous function to alter values.
   *
   * @return array
   *   First element is the value, second is the object.
   *
   * @throws \Drupal\acquia_contenthub_translations\Exceptions\InvalidAttributeException
   */
  public function generateTrackedEntity(EntityTranslationManagerInterface $manager, ?callable $modify_values = NULL): array {
    $random = new Random();
    $languages = [];
    $count = random_int(1, 10);
    for ($i = 0; $i < $count; $i++) {
      $languages = array_merge($this->generateRandomLangOperationPair());
    }
    $values = [
      'uuid' => $this->generateUuid(),
      'type' => $random->string(),
      'original_default_language' => $random->string(2),
      'default_language' => $random->string(2),
      'languages' => $languages,
      'changed' => time(),
      'created' => time(),
    ];
    if (!is_null($modify_values)) {
      $modify_values($values);
    }

    return [$values, new TrackedEntity($manager, $values)];
  }

  /**
   * Generates a random langcode - operation_flag pair.
   *
   * @return array
   *   The random pair.
   */
  public function generateRandomLangOperationPair(): array {
    $random = new Random();
    return [
      $random->string(2) => array_rand([
        EntityTranslationManagerInterface::NO_ACTION,
        EntityTranslationManagerInterface::NO_DELETION,
        EntityTranslationManagerInterface::NO_UPDATE,
        EntityTranslationManagerInterface::NO_UPDATE | EntityTranslationManagerInterface::NO_UPDATE,
      ]),
    ];
  }

}
