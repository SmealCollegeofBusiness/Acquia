<?php

namespace Drupal\Tests\acquia_contenthub_translations\Kernel;

use Drupal\acquia_contenthub_subscriber\SubscriberTracker;
use Drupal\acquia_contenthub_translations\Data\EntityTranslations;
use Drupal\acquia_contenthub_translations\Data\EntityTranslationsTracker;
use Drupal\acquia_contenthub_translations\EventSubscriber\ParseCdf\TrackTranslations;
use Drupal\acquia_contenthub_translations\Form\ContentHubTranslationsSettingsForm;
use Drupal\depcalc\DependencyStack;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\Tests\acquia_contenthub\Kernel\ImportExportTestBase;

/**
 * Test overriding of translations.
 *
 * @requires module depcalc
 *
 * @group acquia_contenthub_translations
 *
 * @package Drupal\Tests\acquia_contenthub_translations\Kernel
 */
class TranslationOverrideTest extends ImportExportTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'user',
    'node',
    'file',
    'field',
    'depcalc',
    'acquia_contenthub_test',
    'acquia_contenthub',
    'acquia_contenthub_subscriber',
    'acquia_contenthub_translations',
    'language',
  ];

  /**
   * {@inheritDoc}
   */
  protected function setup(): void {
    parent::setup();

    $this->installConfig(['language']);
    ConfigurableLanguage::createFromLangcode('be')->save();

    $this->installSchema('acquia_contenthub_subscriber', [SubscriberTracker::IMPORT_TRACKING_TABLE]);
    $this->installSchema('acquia_contenthub_translations',
      [EntityTranslations::TABLE, EntityTranslationsTracker::TABLE]
    );
    $this->installEntitySchema('node');
    $this->installSchema('node', ['node_access']);
    $this->installEntitySchema('file');
    $this->installSchema('file', ['file_usage']);
    $this->installEntitySchema('user');
    $this->installSchema('user', ['users_data']);
    $this->config(ContentHubTranslationsSettingsForm::CONFIG)->set('selective_language_import', TRUE)->save();
  }

  /**
   * Fixtures to import.
   *
   * @var array
   */
  protected $fixtures = [
    0 => [
      'cdf' => 'node/node-translations.json',
      'expectations' => 'expectations/node/node_translations.php',
    ],
  ];

  /**
   * Tests overriding of translation.
   */
  public function testOverridingTranslations(): void {
    $node_uuid = 'b0137bab-a80e-4305-84fe-4d99ffd906c5';
    $this->importFixture(0);

    TrackTranslations::$isSyndicating = FALSE;
    /** @var \Drupal\node\NodeInterface $node */
    $node = $this->container->get('entity.repository')->loadEntityByUuid('node', $node_uuid);
    $translation = $node->getTranslation('be');
    $translation->set('body', 'Test body')->save();

    $document = $this->createCdfDocumentFromFixture(0);
    $cdf_object = $document->getCdfEntity($node_uuid);
    $metadata = $cdf_object->getMetadata();
    $data = json_decode(base64_decode($metadata['data']));
    $data->body->value->be[0]->value = 'Updated be language';
    $metadata['data'] = base64_encode(json_encode($data));
    $cdf_object->setMetadata($metadata);

    $stack = new DependencyStack();
    $this->getSerializer()->unserializeEntities($document, $stack);

    /** @var \Drupal\node\NodeInterface $node_updated */
    $node_updated = $this->container->get('entity.repository')->loadEntityByUuid('node', $node_uuid);
    $translation_updated = $node_updated->getTranslation('be');
    $this->assertSame('Test body', $translation_updated->get('body')->getValue()[0]['value']);
  }

  /**
   * Tests overriding translation when translation overriding flag is enabled.
   */
  public function testOverridingTranslationsEnabled(): void {
    $config = $this->config(ContentHubTranslationsSettingsForm::CONFIG);
    $config->set('override_translation', TRUE)->save();

    $node_uuid = 'b0137bab-a80e-4305-84fe-4d99ffd906c5';
    $this->importFixture(0);

    TrackTranslations::$isSyndicating = FALSE;
    /** @var \Drupal\node\NodeInterface $node */
    $node = $this->container->get('entity.repository')->loadEntityByUuid('node', $node_uuid);
    $translation = $node->getTranslation('be');
    $translation->set('title', 'Test title')->save();

    $document = $this->createCdfDocumentFromFixture(0);
    $cdf_object = $document->getCdfEntity($node_uuid);
    $metadata = $cdf_object->getMetadata();
    $data = json_decode(base64_decode($metadata['data']));
    $data->title->value->be = 'Updated the be title';
    $metadata['data'] = base64_encode(json_encode($data));
    $cdf_object->setMetadata($metadata);

    $stack = new DependencyStack();
    $this->getSerializer()->unserializeEntities($document, $stack);

    /** @var \Drupal\node\NodeInterface $node_updated */
    $node_updated = $this->container->get('entity.repository')->loadEntityByUuid('node', $node_uuid);
    $translation_updated = $node_updated->getTranslation('be');
    $this->assertSame('Updated the be title', $translation_updated->get('title')->getValue()[0]['value']);
  }

}
