<?php

namespace Drupal\Tests\acquia_contenthub_translations\Traits;

use Drupal\node\NodeInterface;

/**
 * Contains helper functions for translation related tests.
 */
trait TranslationCreatorTrait {

  /**
   * Adds translations to a node and track it as well.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node to extend.
   * @param string ...$langcode
   *   The array of langcodes.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function addTranslation(NodeInterface $node, string ...$langcode) {
    foreach ($langcode as $lang) {
      $node->addTranslation($lang, [
        'title' => 'Title - ' . $lang,
        'body' => 'Body - ' . $lang,
      ]);
    }
    $node->save();
  }

}
