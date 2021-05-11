<?php

namespace Drupal\Tests\acquia_contenthub\Kernel\EventSubscriber\SerializeContentField;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\Tests\acquia_contenthub\Kernel\AcquiaContentHubSerializerTestBase;
use Drupal\Tests\acquia_contenthub\Kernel\Stubs\DrupalVersion;

/**
 * Tests Path Field Serialization.
 *
 * @group acquia_contenthub
 *
 * @package Drupal\Tests\acquia_contenthub\Kernel
 *
 * @covers \Drupal\acquia_contenthub\EventSubscriber\SerializeContentField\PathFieldSerializer
 */
class PathFieldSerializerTest extends AcquiaContentHubSerializerTestBase {

  use DrupalVersion;

  /**
   * Path field name.
   */
  protected const FIELD_NAME = 'path';

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = [
    'acquia_contenthub_test',
    'language',
    'content_translation',
    'path',
  ];

  /**
   * {@inheritdoc}
   *
   * @throws \Exception
   */
  public function setUp(): void {
    if (version_compare(\Drupal::VERSION, '9.0', '>=')) {
      static::$modules[] = 'path_alias';
    }
    parent::setUp();
    self::$modules = array_merge(parent::$modules, self::$modules);

    if (version_compare(\Drupal::VERSION, '8.8.0', '>=')) {
      $this->installEntitySchema('path_alias');
    }

    // Enable two additional languages.
    ConfigurableLanguage::createFromLangcode('de')->save();
    ConfigurableLanguage::createFromLangcode('hu')->save();

  }

  /**
   * Tests the serialization of the path field.
   *
   * @param array $languages
   *   Data for create node with translations.
   * @param array $expected
   *   Excepted data for assertion.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   *
   * @dataProvider settingsDataProvider
   */
  public function testPathFieldSerialization(array $languages, array $expected) {

    $this->entity = $this->createNode();
    $field = $this->entity->get(self::FIELD_NAME);
    $this->addTranslationAndAlias($this->entity, $languages);

    $event = $this->dispatchSerializeEvent(self::FIELD_NAME, $field);

    // Check expected output after path field serialization.
    $this->assertEquals($expected, $event->getFieldData());
  }

  /**
   * Provides sample data for client's settings and expected data for assertion.
   *
   * @return array
   *   Settings.
   */
  public function settingsDataProvider() {
    return [
      [
        [
          'hu' => 'hu',
          'de' => 'de',
        ],
        [
          'value' => [
            'en' => [
              'langcode' => 'en',
            ],
            'de' => [
              'alias' => '/path_de',
              'source' => '',
              'pid' => '',
            ],
            'hu' => [
              'alias' => '/path_hu',
              'source' => '',
              'pid' => '',
            ],
          ],
        ],
      ],
    ];
  }

  /**
   * Add translation and path aliases for an entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $node
   *   Base entity.
   * @param array $languages
   *   Translation languages.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function addTranslationAndAlias(ContentEntityInterface $node, array $languages) {
    foreach ($languages as $language) {
      $translation = $node->addTranslation($language);
      $translation->title = 'test.' . $language;
      $translation->path = '/path_' . $language;
      $translation->save();
      if (version_compare(\Drupal::VERSION, '8.8.0', '>=')) {
        $path_alias_storage = \Drupal::entityTypeManager()->getStorage('path_alias');
        $path_alias_storage->create([
          'path' => '/node' . $translation->id(),
          'alias' => '/' . $language,
        ]);
        continue;
      };

      \Drupal::service('path.alias_storage')->save('/node/' . $translation->id(), '/' . $language, $language);
    }
  }

}
