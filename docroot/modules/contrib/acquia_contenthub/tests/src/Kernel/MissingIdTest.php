<?php

namespace Drupal\Tests\acquia_contenthub\Kernel;

use Drupal\acquia_contenthub_publisher\Event\ContentHubEntityEligibilityEvent;
use Drupal\acquia_contenthub_publisher\EventSubscriber\EnqueueEligibility\MissingId;
use Drupal\KernelTests\KernelTestBase;
use Drupal\webform\Entity\Webform;
use Drupal\webform\Entity\WebformSubmission;

/**
 * Tests that entities without ids are not eligible for export.
 *
 * @group acquia_contenthub
 *
 * @package Drupal\Tests\acquia_contenthub\Kernel
 */
class MissingIdTest extends KernelTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = [
    'system',
    'user',
    'depcalc',
    'acquia_contenthub',
    'acquia_contenthub_publisher',
    'webform',
  ];

  /**
   * The mocked Event object.
   *
   * @var \Drupal\acquia_contenthub_publisher\Event\ContentHubEntityEligibilityEvent|\PHPUnit\Framework\MockObject\MockObject
   */
  private $event;

  /**
   * {@inheritdoc}
   *
   * @throws \Exception
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installSchema('acquia_contenthub_publisher', ['acquia_contenthub_publisher_export_tracking']);
    $this->installSchema('webform', ['webform']);
    $this->installConfig('webform');
    $this->installEntitySchema('webform_submission');
  }

  /**
   * Tests missing "Entity Id" functionality.
   */
  public function testMissingId() {
    // Create a webform.
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

    // Create a webform submission.
    $webform_submission = WebformSubmission::create([
      'id' => $this->randomMachineName(),
      'webform_id' => $webform->id(),
      'data' => ['name' => $this->randomMachineName()],
    ]);
    $webform_submission->save();

    $this->event = new ContentHubEntityEligibilityEvent($webform_submission, 'insert');
    $this->triggerEvent($this->event);
    $this->assertFalse($this->event->getEligibility());
  }

  /**
   * Triggers onEnqueueCandidateEntity subscriber.
   *
   * @param \Drupal\acquia_contenthub_publisher\Event\ContentHubEntityEligibilityEvent $event
   *   Handle MissingId event.
   *
   * @throws \Exception
   */
  protected function triggerEvent(ContentHubEntityEligibilityEvent $event) {
    $handler = new MissingId();

    $handler->onEnqueueCandidateEntity($event);
  }

}
