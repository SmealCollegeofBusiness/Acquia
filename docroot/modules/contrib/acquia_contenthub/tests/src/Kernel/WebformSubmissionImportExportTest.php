<?php

namespace Drupal\Tests\acquia_contenthub\Kernel;

/**
 * Tests for Webform submission syndication.
 *
 * @group acquia_contenthub
 *
 * @package Drupal\Tests\acquia_contenthub\Kernel
 */
class WebformSubmissionImportExportTest extends ImportExportTestBase {

  /**
   * Fixture files.
   *
   * @var array
   */
  protected $fixtures = [
    [
      'cdf' => 'webform_submission/webform-submission.json',
      'expectations' => 'expectations/webform_submission/webform_submission.php',
    ],
  ];

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'system',
    'user',
    'field',
    'depcalc',
    'acquia_contenthub',
    'path_alias',
    'webform',
  ];

  /**
   * {@inheritdoc}
   *
   * @throws \Exception
   */
  public function setUp() {
    parent::setUp();

    $this->installSchema('webform', ['webform']);
    $this->installConfig('webform');
    $this->installEntitySchema('webform_submission');
    $this->installEntitySchema('user');
    $this->installSchema('user', ['users_data']);
    $this->installEntitySchema('path_alias');

    // Necessary to avoid errors when collecting path alias dependencies.
    $this->setUpCurrentUser();

    // Adding changed date to list of properties
    // to be stripped out of export tests.
    // @todo Syndicate webform submission change dates correctly.
    static::$normalizeList[] = 'changed';
  }

  /**
   * The webform submission import export test.
   *
   * @dataProvider webformSubmissionImportExportDataProvider
   */
  public function testWebformSubmissionImportExport($delta, $validate_data, $export_type, $export_uuid) {
    parent::contentEntityImportExport($delta, $validate_data, $export_type, $export_uuid);

    $fixtures = json_decode($this->getFixtureString($delta), TRUE);
    $submission_fixture_data = json_decode(
      base64_decode(
        $fixtures['entities'][0]['metadata']['additional_data']['webform_elements']
      ), TRUE);

    /** @var \Drupal\Core\Entity\EntityRepository $repository */
    $repository = \Drupal::service('entity.repository');
    /** @var \Drupal\Webform\Entity\WebformSubmission $submission */
    $submission = $repository->loadEntityByUuid('webform_submission', $export_uuid);

    $this->assertEqual($submission_fixture_data, $submission->getData(), "Submission data exported successfully.");
  }

  /**
   * Data provider for webformSubmissionImportExportDataProvider.
   *
   * @return array
   *   Array of import and export data.
   */
  public function webformSubmissionImportExportDataProvider() {
    return [
      [
        0,
        [
          [
            'type' => 'webform_submission',
            'uuid' => 'edd0127d-3cf0-49a5-9661-012449128145',
          ],
        ],
        'webform_submission',
        'edd0127d-3cf0-49a5-9661-012449128145',
      ],
    ];
  }

}
