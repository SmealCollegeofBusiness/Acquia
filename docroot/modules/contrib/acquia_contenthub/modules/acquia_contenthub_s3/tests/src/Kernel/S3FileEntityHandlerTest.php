<?php

namespace Drupal\Tests\acquia_contenthub_s3\Kernel;

use Acquia\ContentHubClient\CDF\CDFObject;
use Drupal\acquia_contenthub\AcquiaContentHubEvents;
use Drupal\acquia_contenthub\Event\ParseCdfEntityEvent;
use Drupal\depcalc\DependencyStack;
use Drupal\user\Entity\User;

/**
 * Class S3FileEntityHandlerTest.
 *
 * @group acquia_contenthub_s3
 * @coversDefaultClass \Drupal\acquia_contenthub_s3\EventSubscriber\Cdf\S3FileEntityHandler
 *
 * @requires module depcalc
 * @requires module s3fs
 *
 * @package Drupal\Tests\acquia_contenthub_s3\Kernel
 */
class S3FileEntityHandlerTest extends S3FileKernelTestBase {

  use S3FileTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
  ];

  /**
   * The s3 file map object to test.
   *
   * @var \Drupal\acquia_contenthub_s3\S3FileMap
   */
  protected $s3FileMap;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->installSchema('system', 'sequences');
    $this->s3FileMap = $this->container->get('acquia_contenthub_s3.file_map');
  }

  /**
   * @covers ::onParseCdf
   */
  public function testOnParseCdf() {
    $this->container->get('config.factory')->getEditable('s3fs.settings')
      ->set('region', 'us-east-1')->save();

    $cdf = CDFObject::fromArray($this->getFileFixture('file-1.json'));
    $file = $this->createFileEntity('example.png', 's3', [
      'uuid' => 'ff201120-3e98-475e-9460-4fef82172c29',
      'filesize' => 5387,
      'status' => 1,
      'created' => 1581338438,
      'changed' => 1581338445,
    ]);
    // User uuid in encoded into the cdf.
    User::create([
      'uuid' => '828714b2-858f-413f-a1e4-8b74a3151b0e',
      'langcode' => 'en',
      'name' => 'User-1',
    ])->save();

    $event = new ParseCdfEntityEvent($cdf, new DependencyStack(), $file);
    $this->container->get('event_dispatcher')
      ->dispatch(AcquiaContentHubEvents::PARSE_CDF, $event);
    $cdf = $event->getCdf();
    $object = $this->s3FileMap->getFileByUuid($file->uuid());

    // Test if s3 file recording was successful.
    $this->assertEqual($object->file_uuid, $file->uuid(), 'File uuid match.');
    $this->assertCdfAttribute($cdf, 'ach_s3_bucket', $object->bucket);
    $this->assertCdfAttribute($cdf, 'ach_s3_source', $object->root_folder);
    $this->assertEqual($object->origin_uuid, $cdf->getOrigin(), 'Origin uuid match.');
  }

}
