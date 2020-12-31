<?php

namespace Drupal\Tests\acquia_contenthub_s3\Kernel;

use Drupal\acquia_contenthub_s3\S3FileMap;

/**
 * Class S3FileMapTest.
 *
 * @group acquia_contenthub_s3
 * @coversDefaultClass \Drupal\acquia_contenthub_s3\S3FileMap
 *
 * @requires module depcalc
 * @requires module s3fs
 *
 * @package Drupal\Tests\acquia_contenthub_s3\Kernel
 */
class S3FileMapTest extends S3FileKernelTestBase {

  use S3FileTestTrait;
  use S3FileMapTestTrait;

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

    $this->s3FileMap = new S3FileMap($this->container->get('database'));
  }

  /**
   * @covers ::isNew
   */
  public function testIsNew() {
    $uuid = 'd2ded609-c7eb-4ca0-97cc-5bb0ddbbbf5a';
    $this->assertTrue($this->s3FileMap->isNew($uuid), 'Table does not contain the data.');

    $this->s3FileMap->record(
      $uuid,
      'bucket',
      'root_folder',
      '314d45d0-7b4b-4a46-860b-28f0258bb618'
    );
    $this->assertFalse($this->s3FileMap->isNew($uuid), 'Table contains the data.');
  }

  /**
   * @covers ::record
   */
  public function testRecordDataValidity() {
    $valid = [
      'd2ded609-c7eb-4ca0-97cc-5bb0ddbbbf5a',
      'custom-bucket',
      'custom-root-folder',
      'd2ded609-c7eb-4ca0-97cc-5bb0ddbbbf55',
    ];

    $records = $this->fetchAllData();
    $this->assertCount(0, $records, 'Table is empty.');

    $this->s3FileMap->record(...$valid);
    $records = $this->fetchAllData();
    $this->assertCount(1, $records, 'File was successfully recorded.');

    $invalid_uuid = [
      'd2ded609-c7eb-4ca0-97cc-5bb0ddbbbf5',
      'custom-bucket',
      'custom-root-folder',
      'd2ded609-c7eb-4ca0-97cc-5bb0ddbbbf55',
    ];
    $this->expectException(\Exception::class);
    $this->s3FileMap->record(...$invalid_uuid);
    $invalid_uuid = [
      'd2ded609-c7eb-4ca0-97cc-5bb0ddbbbf55',
      'custom-bucket',
      'custom-root-folder',
      'invalid',
    ];
    $this->expectException(\InvalidArgumentException::class);
    $this->s3FileMap->record(...$invalid_uuid);
  }

  /**
   * @covers ::record
   */
  public function testRecordUpdate() {
    $data = [
      'd2ded609-c7eb-4ca0-97cc-5bb0ddbbbf5a',
      'custom-bucket',
      'custom-root-folder',
      'd2ded609-c7eb-4ca0-97cc-5bb0ddbbbf55',
    ];
    $this->s3FileMap->record(...$data);

    $updated = [
      'd2ded609-c7eb-4ca0-97cc-5bb0ddbbbf5a',
      'other-bucket',
      'other-root',
      '88e438dd-e7a3-4c08-b7a1-6158c404d30b',
    ];
    $this->s3FileMap->record(...$updated);
    $records = $this->fetchAllData();
    $this->assertCount(1, $records, 'No new entry was added.');
    $this->assertEqual($updated, array_values(current($records)), 'Data was successfully updated.');
  }

  /**
   * @covers ::remove
   */
  public function testRemove() {
    $data = [
      'd2ded609-c7eb-4ca0-97cc-5bb0ddbbbf5a',
      'custom-bucket',
      'custom-root-folder',
      'd2ded609-c7eb-4ca0-97cc-5bb0ddbbbf55',
    ];
    $this->s3FileMap->record(...$data);

    $data2 = [
      'b553c81e-cc33-4f07-bce7-e4478332fe26',
      'custom-bucket',
      'custom-root-folder',
      'b553c81e-cc33-4f07-bce7-e4478332fe27',
    ];
    $this->s3FileMap->record(...$data2);

    $this->s3FileMap->remove('d2ded609-c7eb-4ca0-97cc-5bb0ddbbbf5a');
    $records = $this->fetchAllData();
    $this->assertCount(1, $records, 'Data was successfully deleted.');
    $this->assertEqual($data2, array_values(current($records)), 'Non targeted data preserved');
  }

  /**
   * @covers ::getFileByUri
   */
  public function testGetFileByUri() {
    $file = $this->createFileEntity('thelocation/test.jpg', 's3');
    $this->s3FileMap->record(
      $file->uuid(),
      'bucket',
      'root_folder',
      '23623525-fb2f-4035-b5ca-a6d64e212ed9'
    );

    $object = $this->s3FileMap->getFileByUri($file->getFileUri());
    $this->assertInstanceOf('stdClass', $object, 'Returned data is of type stdClass.');

    $this->assertEqual($object->uuid, $file->uuid(), 'File uuid match.');
    $this->assertEqual($object->bucket, 'bucket', 'Bucket match.');
    $this->assertEqual($object->root_folder, 'root_folder', 'Root folder match.');
    $this->assertEqual($object->origin_uuid, '23623525-fb2f-4035-b5ca-a6d64e212ed9', 'Origin uuid match.');

    $object = $this->s3FileMap->getFileByUri('s3://non-existent-file.jpg');
    $this->assertNull($object, 'Non existent file, return value is NULL.');
  }

  /**
   * @covers ::getFileByUuid
   */
  public function testGetFileByUuid() {
    $uuid = 'd2ded609-c7eb-4ca0-97cc-5bb0ddbbbf5a';
    $bucket = 'custom-bucket';
    $root = 'custom-root-folder';
    $origin = 'd2ded609-c7eb-4ca0-97cc-5bb0ddbbbf55';
    $this->s3FileMap->record($uuid, $bucket, $root, $origin);

    $object = $this->s3FileMap->getFileByUuid($uuid);
    $this->assertInstanceOf('stdClass', $object, 'Returned data is of type stdClass.');

    $this->assertEqual($object->file_uuid, $uuid, 'File uuid match.');
    $this->assertEqual($object->bucket, $bucket, 'Bucket match.');
    $this->assertEqual($object->root_folder, $root, 'Root folder match.');
    $this->assertEqual($object->origin_uuid, $origin, 'Origin uuid match.');

    $object = $this->s3FileMap->getFileByUuid('c0512f3c-f305-4dce-a114-6d47bcb051a4');
    $this->assertNull($object, 'Non existent file, return value is NULL.');
  }

}
