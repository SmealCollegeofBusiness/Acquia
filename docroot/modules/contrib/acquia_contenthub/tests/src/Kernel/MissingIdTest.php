<?php

namespace Drupal\Tests\acquia_contenthub\Kernel;

use Drupal\webform\Entity\Webform;
use Drupal\webform\Entity\WebformSubmission;

/**
 * Tests that entities without ids are not eligible for export.
 *
 * @group acquia_contenthub
 *
 * @requires module webform
 *
 * @package Drupal\Tests\acquia_contenthub\Kernel
 */
class MissingIdTest extends QueueingTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'webform',
  ];

  /**
   * {@inheritdoc}
   *
   * @throws \Exception
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installSchema('webform', ['webform']);
    $this->installConfig('webform');
    $this->installEntitySchema('webform_submission');
  }

  /**
   * Tests missing "Entity Id" functionality.
   */
  public function testMissingId() {
    $webform = Webform::create([
      'id' => $this->randomMachineName(),
    ]);
    $elements = [
      'name' => [
        '#type' => 'textfield',
        '#title' => 'name',
      ],
    ];
    $webform->setElements($elements);
    // Disable saving of results.
    $webform->setSetting('results_disabled', TRUE);
    $webform->save();

    $pre_webform_submission_queue_count = $this->contentHubQueue->getQueueCount();

    // Create a webform submission.
    $webform_submission = WebformSubmission::create([
      'id' => $this->randomMachineName(),
      'webform_id' => $webform->id(),
      'data' => ['name' => $this->randomMachineName()],
    ]);
    $webform_submission->save();

    $this->assertEquals(
      $pre_webform_submission_queue_count,
      $this->contentHubQueue->getQueueCount(),
      'Webform submission not queued.'
    );
  }

}
