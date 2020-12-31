<?php

namespace Drupal\Tests\acquia_contenthub_s3\Kernel;

use Acquia\ContentHubClient\CDF\CDFObject;
use Acquia\ContentHubClient\CDFAttribute;
use Drupal\acquia_contenthub_s3\S3FileMap;
use Drupal\acquia_contenthub_s3\S3FileMapper;
use Prophecy\Argument;

/**
 * Class S3FileMapperTest.
 *
 * @group acquia_contenthub_s3
 * @coversDefaultClass \Drupal\acquia_contenthub_s3\S3FileMapper
 *
 * @requires module depcalc
 * @requires module s3fs
 *
 * @package Drupal\Tests\acquia_contenthub_s3\Kernel
 */
class S3FileMapperTest extends S3FileKernelTestBase {

  use S3FileTestTrait;
  use S3FileMapTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'acquia_contenthub_s3_test',
    'filter',
    'system',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->installSchema('file', 'file_usage');
  }

  /**
   * @covers ::mapS3File
   */
  public function testMapS3File() {
    $file = $this->createFileEntity('test.png', 's3');

    $cdf = $this->prophesize(CDFObject::class);
    $cdf->getAttribute(Argument::is('ach_s3_bucket'))
      ->willReturn($this->createCdfAttribute('test-bucket'));
    $cdf->getAttribute(Argument::is('ach_s3_source'))
      ->willReturn($this->createCdfAttribute('test-root'));
    $cdf->getOrigin()
      ->willReturn(\Drupal::service('uuid')->generate());

    $mapper = $this->constructFileMapper();
    $mapper->mapS3File($cdf->reveal(), $file);
    $s3_file = $this->container->get('acquia_contenthub_s3.file_map')
      ->getFileByUuid($file->uuid());
    $this->assertEqual($s3_file->bucket, 'test-bucket');
    $this->assertEqual($s3_file->root_folder, 'test-root');
  }

  /**
   * @covers ::mapS3File
   * @dataProvider mapS3FileDataProvider
   *
   * @param string|null $bucket
   *   The bucket attribute.
   * @param string|null $source
   *   The source attribute.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testMapS3FileNoBucket(?string $bucket, ?string $source) {
    $file = $this->createFileEntity('test.png', 's3');

    $cdf = $this->prophesize(CDFObject::class);
    $cdf->getAttribute(Argument::is('ach_s3_bucket'))
      ->willReturn($this->createCdfAttribute($bucket));
    $cdf->getAttribute(Argument::is('ach_s3_source'))
      ->willReturn($this->createCdfAttribute($source));

    $map = $this->prophesize(S3FileMap::class);
    $map->record(Argument::any())
      ->shouldNotBeCalled();
    $this->container->set('acquia_contenthub_s3.file_map', $map->reveal());

    $mapper = $this->constructFileMapper();
    $mapper->mapS3File($cdf->reveal(), $file);
    $s3_file = $this->container->get('database')
      ->select(S3FileMap::TABLE_NAME, 'acs3')
      ->fields('acs3')
      ->condition('file_uuid', $file->uuid(), '=')
      ->execute()
      ->fetchObject();

    $this->assertFalse($s3_file);
  }

  /**
   * Returns an array of input cases.
   *
   * @return array
   *   Data for test cases.
   */
  public function mapS3FileDataProvider() {
    return [
      [NULL, NULL],
      ['ach_s3_bucket', NULL],
      [NULL, 'ach_s3_source'],
    ];
  }

  /**
   * Returns a CDFAttribute object using the provided input string.
   *
   * In case the input is NULL, the return value will be NULL as well.
   *
   * @param string|null $name
   *   The name and value of the attribute.
   *
   * @return \Acquia\ContentHubClient\CDFAttribute|null
   *   The CDFAttribute or NULL.
   *
   * @throws \Exception
   */
  protected function createCdfAttribute(?string $name): ?CDFAttribute {
    if (is_null($name)) {
      return NULL;
    }

    return new CDFAttribute($name, CDFAttribute::TYPE_STRING, $name);
  }

  /**
   * Construct a fresh and crispy S3FileMapper object.
   *
   * @return \Drupal\acquia_contenthub_s3\S3FileMapper
   *   The file mapper object.
   *
   * @throws \Exception
   */
  protected function constructFileMapper() {
    return new S3FileMapper(
      $this->container->get('config.factory'),
      $this->container->get('file_system'),
      $this->container->get('acquia_contenthub_s3.file_map'),
      $this->container->get('stream_wrapper_manager')
    );
  }

}
