<?php

namespace Drupal\Tests\acquia_contenthub_translations\Kernel\OperationHandler;

use Drupal\acquia_contenthub_translations\Data\EntityTranslations;
use Drupal\acquia_contenthub_translations\Data\EntityTranslationsTracker;
use Drupal\acquia_contenthub_translations\EventSubscriber\ParseCdf\TrackTranslations;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\node\NodeInterface;
use Drupal\Tests\acquia_contenthub\Unit\Helpers\LoggerMock;
use Drupal\Tests\acquia_contenthub_translations\Traits\EntityTranslationDbAssertions;
use Drupal\Tests\acquia_contenthub_translations\Traits\TranslationCreatorTrait;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;

/**
 * @coversDefaultClass \Drupal\acquia_contenthub_translations\OperationHandler\TranslationUpdateHandler
 *
 * @group acuqia_contenthub_translations
 *
 * @requires module depcalc
 *
 * @package Drupal\Tests\acquia_contenthub_translations\Kernel
 */
class TranslationUpdateHandlerTest extends KernelTestBase {

  use NodeCreationTrait;
  use ContentTypeCreationTrait;
  use EntityTranslationDbAssertions;
  use TranslationCreatorTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'acquia_contenthub',
    'acquia_contenthub_subscriber',
    'acquia_contenthub_translations',
    'content_translation',
    'depcalc',
    'field',
    'filter',
    'language',
    'node',
    'text',
    'user',
    'system',
  ];

  /**
   * Logger mock.
   *
   * @var \Drupal\Tests\acquia_contenthub\Unit\Helpers\LoggerMock
   */
  private $logger;

  /**
   * Content hub translations config.
   *
   * @var \Drupal\Core\Config\Config
   */
  private $translateConfig;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('configurable_language');
    $this->installConfig([
      'field',
      'filter',
      'node',
      'language',
    ]);
    $this->installSchema('node', ['node_access']);
    $this->installSchema('acquia_contenthub_translations', [
      EntityTranslations::TABLE,
      EntityTranslationsTracker::TABLE,
    ]);

    $this->logger = new LoggerMock();
    $logger_factory = $this->prophesize(LoggerChannelFactoryInterface::class);
    $logger_factory->get('acquia_contenthub_translations')->willReturn($this->logger);
    $this->container->set('logger.factory', $logger_factory->reveal());
    ConfigurableLanguage::createFromLangcode('hu')->save();
    $this->createContentType([
      'type' => 'test',
      'name' => 'Test',
    ])->save();

    $this->translateConfig = $this->container->get('acquia_contenthub_translations.config');
    $this->translateConfig->set('override_translation', FALSE)->save();
    $this->container->set('acquia_contenthub_translations.config', $this->translateConfig);
  }

  /**
   * @covers ::updateTrackedEntity
   */
  public function testUpdateTrackedEntity(): void {
    ConfigurableLanguage::createFromLangcode('es')->save();
    ConfigurableLanguage::createFromLangcode('sw')->save();
    // Additional languages.
    $additional_languages = ['hu', 'es', 'sw'];
    $node = $this->initNode(...$additional_languages);

    $languages = array_keys($node->getTranslationLanguages());

    foreach ($languages as $lang) {
      $translation = $node->getTranslation($lang);
      $translation->set('title', 'Updated content - ' . $lang);
      $translation->set('body', 'Updated body - ' . $lang);
    }

    // This will kick off the update handler.
    $node->save();
    // Default case, every translation will be updated.
    foreach ($languages as $lang) {
      $translation = $node->getTranslation($lang);
      $this->assertEquals('Updated content - ' . $lang, $translation->getTitle());
      $this->assertEquals('Updated body - ' . $lang, $translation->get('body')->getValue()[0]['value']);
    }

    // Local translations update.
    TrackTranslations::$isSyndicating = FALSE;
    foreach ($languages as $lang) {
      $translation = $node->getTranslation($lang);
      $translation->set('title', 'Updated local content - ' . $lang);
      $translation->set('body', 'Updated local body - ' . $lang);
    }
    $node->save();

    foreach ($languages as $lang) {
      $translation = $node->getTranslation($lang);
      $this->assertEquals('Updated local content - ' . $lang, $translation->getTitle());
      $this->assertEquals('Updated local body - ' . $lang, $translation->get('body')->getValue()[0]['value']);
    }

    // First update translations through hook entity update.
    foreach ($languages as $lang) {
      $translation = $node->getTranslation($lang);
      $translation->set('title', 'Updated content via local through hook update - ' . $lang);
      $translation->set('body', 'Updated body via local through hook update - ' . $lang);
      $translation->save();
    }
    TrackTranslations::$isSyndicating = TRUE;
    foreach ($languages as $lang) {
      $translation = $node->getTranslation($lang);
      $translation->set('title', 'Updated content via syndication - ' . $lang);
      $translation->set('body', 'Updated body via syndication - ' . $lang);
    }
    $node->save();
    $debug_messages = $this->logger->getDebugMessages();
    foreach ($languages as $lang) {
      $translation = $node->getTranslation($lang);
      if ($translation->isDefaultTranslation()) {
        $this->assertEquals('Updated content via syndication - ' . $lang, $translation->getTitle());
        $this->assertEquals('Updated body via syndication - ' . $lang, $translation->get('body')->getValue()[0]['value']);
        $this->assertNotContains(sprintf(
          'Translation %s of %s | %s has been reverted to original',
          $lang, $node->bundle(), $node->uuid(),
        ), $debug_messages);
        continue;
      }
      $this->assertEquals('Updated content via local through hook update - ' . $lang, $translation->getTitle());
      $this->assertEquals('Updated body via local through hook update - ' . $lang, $translation->get('body')->getValue()[0]['value']);
      $this->assertContains(sprintf(
        'Translation %s of %s | %s has been reverted to original',
        $lang, $node->bundle(), $node->uuid(),
      ), $debug_messages);
    }

    // Test with allow translation overriding flag TRUE.
    $this->translateConfig->set('override_translation', TRUE)->save();
    $this->container->set('acquia_contenthub_translations.config', $this->translateConfig);
    foreach ($languages as $lang) {
      $translation = $node->getTranslation($lang);
      $translation->set('title', 'Updated content via syndication config flag true - ' . $lang);
      $translation->set('body', 'Updated body via syndication config flag true - ' . $lang);
    }
    $node->save();
    foreach ($languages as $lang) {
      $translation = $node->getTranslation($lang);
      $this->assertEquals('Updated content via syndication config flag true - ' . $lang, $translation->getTitle());
      $this->assertEquals('Updated body via syndication config flag true - ' . $lang, $translation->get('body')->getValue()[0]['value']);
    }

  }

  /**
   * Initializes a test node.
   *
   * @return \Drupal\node\NodeInterface
   *   The test node.
   *
   * @throws \Exception
   */
  protected function initNode(string ...$languages): NodeInterface {
    TrackTranslations::$isSyndicating = TRUE;
    $node = $this->createNode([
      'type' => 'test',
      'title' => 'Test content',
      'body' => 'Test body',
    ]);
    $this->addTranslation($node, ...$languages);

    return $node;
  }

}
