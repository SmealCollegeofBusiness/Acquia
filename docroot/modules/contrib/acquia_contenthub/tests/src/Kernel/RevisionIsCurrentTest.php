<?php

namespace Drupal\Tests\acquia_contenthub\Kernel;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\content_moderation\Traits\ContentModerationTestTrait;

/**
 * Tests that only current published revisions are enqueued.
 *
 * @group acquia_contenthub
 *
 * @package Drupal\Tests\acquia_contenthub\Kernel
 */
class RevisionIsCurrentTest extends QueueingTestBase {

  use ContentModerationTestTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'node',
    'language',
    'workflows',
    'user',
    'content_translation',
    'content_moderation',
  ];

  /**
   * The content translation manager.
   *
   * @var \Drupal\content_translation\ContentTranslationManagerInterface
   */
  protected $contentTranslationManager;

  /**
   * {@inheritdoc}
   *
   * @throws \Exception
   */
  protected function setUp(): void {
    parent::setUp();

    $this->contentTranslationManager = $this->container->get('content_translation.manager');

    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installEntitySchema('content_moderation_state');
    $this->installSchema('node', ['node_access']);
    $this->installConfig('content_moderation');

    ConfigurableLanguage::createFromLangcode('es')->save();
    $this->createTranslatableNodeTypeWithWorkflow();
  }

  /**
   * Creates a translatable node type with a revisionable workflow.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function createTranslatableNodeTypeWithWorkflow() {
    NodeType::create([
      'type' => 'bundle_test',
      'new_revision' => TRUE,
    ])->save();

    $workflow = $this->createEditorialWorkflow();
    $workflow->getTypePlugin()->addEntityTypeAndBundle('node', 'bundle_test');
    $workflow->save();
    $this->contentTranslationManager->setEnabled('node', 'bundle_test', TRUE);
  }

  /**
   * Tests that only current published revisions are enqueued.
   *
   * @param array $moderation_states
   *   Array containing the moderation states.
   * @param array $expected_queue_count
   *   Array containing the number, how many queue items we
   *   expect after transition.
   * @param array $langcodes
   *   Langcodes that defines which translation are we working with.
   * @param array $message
   *   Messages displayed on failure.
   *
   * @dataProvider testRevisionIsCurrentDataProvider
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testRevisionIsCurrent(array $moderation_states, array $expected_queue_count, array $langcodes, array $message) {
    // Makes sure queue is empty before this test.
    $this->contentHubQueue->purgeQueues();

    // Creates and translates a draft, make sure it isn't added yet.
    /** @var \Drupal\node\NodeInterface $node */
    $node = Node::create([
      'type' => 'bundle_test',
      'moderation_state' => 'draft',
      'langcode' => 'en',
      'title' => 'Test EN',
    ]);
    $node->save();

    $this->assertEquals(
      0,
      $this->contentHubQueue->getQueueCount(),
      'Node created as draft not queued.'
    );

    $node->addTranslation('es', [
      'moderation_state' => 'draft',
      'title' => 'Test ES',
    ]);
    $node->save();
    $this->assertEquals(
      0,
      $this->contentHubQueue->getQueueCount(),
      'Translation created as draft not queued.'
    );

    foreach ($moderation_states as $key => $moderation_state) {
      // If we wouldn't purge the queues every time then the
      // IsAlreadyEnqueued subscriber would prevent the queue creation
      // and we would got false queue counts for the given scenarios.
      $this->contentHubQueue->purgeQueues();
      $node = $node->getTranslation($langcodes[$key]);
      $node->setNewRevision(TRUE);
      $node->set('moderation_state', $moderation_state);
      $node->save();
      $this->assertEquals(
        $expected_queue_count[$key],
        $this->contentHubQueue->getQueueCount(),
        $message[$key]
      );
    }
  }

  public function testRevisionIsCurrentDataProvider() {
    return [
      [
        ['draft', 'published', 'archived'],
        [0, 1, 1],
        ['en', 'en', 'en'],
        [
          'Node transitioned to draft from draft state.',
          'Node transitioned to published from draft state.',
          'Node transitioned to archived from published state.',
        ],
      ],
      [
        ['published', 'draft', 'published', 'archived', 'archived'],
        [1, 1, 1, 1, 0],
        ['en', 'en', 'en', 'en', 'en'],
        [
          'Node transitioned to published from draft state.',
          'Node transitioned to draft from published state.',
          'Node transitioned to published from draft state.',
          'Node transitioned to archived from published state.',
          'Node transitioned to draft from archived state.',
        ],
      ],
      [
        ['published', 'published', 'archived', 'archived', 'draft', 'draft'],
        [1, 1, 1, 1, 0, 0],
        ['en', 'es', 'en', 'es', 'en', 'es'],
        [
          'Node transitioned to published from draft state. (EN)',
          'Node transitioned to published from draft state. (ES)',
          'Node transitioned to archived from published state. (EN)',
          'Node transitioned to archived from published state. (ES)',
          'Node transitioned to draft from archived state. (EN)',
          'Node transitioned to draft from archived state. (ES)',
        ],
      ],
    ];
  }

}
