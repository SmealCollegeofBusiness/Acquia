<?php

namespace Drupal\Tests\acquia_contenthub_s3\Kernel;

use Acquia\ContentHubClient\Settings;
use Drupal\acquia_contenthub\ContentHubCommonActions;
use Drupal\acquia_contenthub_s3\S3FileOriginLocator;
use Drupal\acquia_contenthub_s3_test\EventSubscriber\GetSettings\OverwriteContentHubAdminSettings;
use Drupal\file\FileInterface;
use Drupal\image\Entity\ImageStyle;
use Drupal\image\ImageStyleInterface;
use Prophecy\Argument;

/**
 * Class S3FileOriginLocatorTest.
 *
 * @group acquia_contenthub_s3
 * @coversDefaultClass \Drupal\acquia_contenthub_s3\S3FileOriginLocator
 *
 * @requires module depcalc
 * @requires module s3fs
 *
 * @package Drupal\Tests\acquia_contenthub_s3\Kernel
 */
class S3FileOriginLocatorTest extends S3FileKernelTestBase {

  use S3FileTestTrait;
  use S3FileMapTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'acquia_contenthub_s3_test',
    'filter',
    'image',
    'system',
  ];

  /**
   * The s3 file map service.
   *
   * @var \Drupal\acquia_contenthub_s3\S3FileMap|object|null
   */
  protected $s3FileMap;

  /**
   * The s3 origin locator service.
   *
   * @var \Drupal\acquia_contenthub_s3\S3FileOriginLocator
   */
  protected $locator;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->installEntitySchema('image_style');
    $this->installSchema('file', 'file_usage');

    $this->s3FileMap = $this->container->get('acquia_contenthub_s3.file_map');
  }

  /**
   * @covers ::getS3FileSource
   * @dataProvider sourceDataProvider
   */
  public function testGetS3FileSourcePublisherHasRoot(string $bucket, string $root_folder, bool $with_image_style): void {
    $this->setS3fsConfig($bucket, $root_folder);
    $this->runTestWith('test-bucket', 'test-root', $with_image_style);
  }

  /**
   * @covers ::getS3FileSource
   * @dataProvider sourceDataProvider
   */
  public function testGetS3FileSourcePublisherHasNoRoot(string $bucket, string $root_folder, bool $with_image_style): void {
    $this->setS3fsConfig($bucket, $root_folder);
    $this->runTestWith('test-bucket', '', $with_image_style);
  }

  /**
   * Provides test cases for s3 file source.
   *
   * @return array
   *   Test cases.
   */
  public function sourceDataProvider(): array {
    return [
      ['test-bucket', 'test-root', FALSE],
      ['test-bucket', 'test-root', TRUE],
      ['test-bucket', 'not-same-root', FALSE],
      ['test-bucket', 'not-same-root', TRUE],
      ['not-same-bucket', 'test-root', FALSE],
      ['not-same-bucket', 'test-root', TRUE],
      ['not-same-bucket', '', FALSE],
      ['not-same-bucket', '', TRUE],
    ];
  }

  /**
   * @covers ::getS3FileSource
   */
  public function testNoRecordLocally(): void {
    // The mocking process below represents a file on Content Hub.
    $this->setS3fsConfig('pub-bucket', 'pub-root');
    OverwriteContentHubAdminSettings::overwrite(new Settings(
      'test-client',
      'a76696bf-be45-4d42-b5c3-08cebee93798',
      'api-key',
      'secret-key',
      'http://example.com'
    ));

    $file = $this->createFileEntity('test.png', 's3');
    // Same uri, pretend they are in different buckets.
    $this->createFileEntity('test.png', 's3');
    $this->createFileEntity('test.png', 's3');

    $cdf_doc = $this->container->get('acquia_contenthub_common_actions')->getLocalCdfDocument($file);
    $cdf = $cdf_doc->getCdfEntity($file->uuid());
    // Clear s3 file map table. getLocalCdfDocument call will populate the file
    // map table.
    $this->truncateS3FileMap();

    $uuid = $file->uuid();
    $common = $this->prophesize(ContentHubCommonActions::class);
    $common->getRemoteEntity(Argument::type('string'))
      ->will(function ($args) use ($uuid, $cdf) {
        return current($args) === $uuid ? $cdf : NULL;
      });
    $this->container->set('acquia_contenthub_common_actions', $common->reveal());

    // This is the subscriber side.
    $this->setS3fsConfig('sub-bucket', 'sub-root');
    // Make sure the table is empty.
    $this->assertCount(0, $this->fetchAllData());
    // Make the remote call.
    $source = $this->constructS3Locator()->getS3FileSource($file->getFileUri());
    $expected = [
      'bucket' => 'pub-bucket',
      'root_folder' => 'pub-root',
    ];
    $this->assertEqual($source, $expected);
  }

  /**
   * Run tests with the specified paramteres.
   *
   * @param string $origin_bucket
   *   The publisher's bucket name.
   * @param string $origin_root_folder
   *   The publisher's root_folder name.
   * @param bool $with_image_style
   *   Whether to include image style path alteration.
   *
   * @throws \Exception
   */
  protected function runTestWith(string $origin_bucket, string $origin_root_folder, bool $with_image_style): void {
    $file = $this->initFileFixture($origin_bucket, $origin_root_folder);
    $uri = $file->getFileUri();
    if ($with_image_style) {
      $image_style = $this->createImageStyle();
      $uri = $image_style->buildUri($file->getFileUri());
    }

    $source = $this->constructS3Locator()->getS3FileSource($uri);
    $expected = [
      'bucket' => $origin_bucket,
      'root_folder' => $origin_root_folder,
    ];
    $this->assertEqual($source, $expected);
  }

  /**
   * Returns a freshly constructed S3FileOriginLocator object.
   *
   * @return \Drupal\acquia_contenthub_s3\S3FileOriginLocator
   *   The locator service.
   *
   * @throws \Exception
   */
  protected function constructS3Locator(): S3FileOriginLocator {
    return new S3FileOriginLocator(
      $this->container->get('acquia_contenthub_s3.file_map'),
      $this->container->get('acquia_contenthub_s3.file_storage'),
      $this->container->get('acquia_contenthub_common_actions'),
      $this->container->get('config.factory')->get('acquia_contenthub.admin_settings')
    );
  }

  /**
   * Initializes a file and inserts it into s3 file map table.
   *
   * @param string $bucket
   *   The s3 source of the file.
   * @param string $root_folder
   *   The s3 source of the file.
   *
   * @return \Drupal\file\FileInterface
   *   The file entity.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function initFileFixture(string $bucket, string $root_folder): FileInterface {
    // Initialize test case.
    $file = $this->createFileEntity('test.png', 's3');
    $origin = '96c3cc3f-17cf-48f3-8354-33a71300c676';
    $this->s3FileMap->record($file->uuid(), $bucket, $root_folder, $origin);

    return $file;
  }

  /**
   * Creates and returns a new image style.
   *
   * @return \Drupal\image\ImageStyleInterface
   *   The image style object saved into database.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function createImageStyle(): ImageStyleInterface {
    /** @var \Drupal\image\ImageStyleInterface $style */
    $style = ImageStyle::create([
      'name' => 'test',
    ]);
    $style->save();

    return $style;
  }

}
