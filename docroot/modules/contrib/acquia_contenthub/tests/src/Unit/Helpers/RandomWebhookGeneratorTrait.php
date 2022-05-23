<?php

namespace Drupal\Tests\acquia_contenthub\Unit\Helpers;

use Acquia\ContentHubClient\Webhook;
use Drupal\Component\Utility\Random;
use Drupal\Component\Uuid\Php;

/**
 * Generates random webhook objects.
 */
trait RandomWebhookGeneratorTrait {

  /**
   * Generates a customizable webhook object.
   *
   * @param array $definition_override
   *   (Optional) The override definition.
   *
   * @return \Acquia\ContentHubClient\Webhook
   *   The random webhook object.
   */
  public function getRandomWebhook(array $definition_override = []): Webhook {
    $random_generator = new Random();
    $definition = [
      'uuid' => $this->generateRandomUuids(1)[0],
      'client_uuid' => $this->generateRandomUuids(1)[0],
      'client_name' => $random_generator->word(rand(0, 15)) . '_client',
      'url' => 'https://' . $random_generator->word(rand(0, 15)) . '.com/acquia-contenthub/webhook',
      'version' => '2',
      'disable_retries' => rand(0, 1) == 0 ? 'false' : 'true',
      'filters' => $this->generateRandomUuids(rand(0, 15)),
      'status' => 'ENABLED',
      'is_migrated' => FALSE,
      'suppressed_until' => array_rand([time() => '', 0 => '']),
    ];
    $definition = array_replace($definition, $definition_override);
    return new Webhook($definition);
  }

  /**
   * Generates random uuids.
   *
   * @param int $amount
   *   The number of uuids to generate.
   *
   * @return array
   *   The resulting set of uuids.
   */
  public function generateRandomUuids(int $amount): array {
    $generator = new Php();
    $uuids = [];
    for ($i = 0; $i < $amount; $i++) {
      $uuids[] = $generator->generate();
    }
    return $uuids;
  }

}
