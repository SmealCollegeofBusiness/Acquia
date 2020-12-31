<?php

namespace Drupal\Tests\acquia_contenthub_s3\Kernel;

use Acquia\ContentHubClient\CDF\CDFObject;
use Drupal\Core\Language\LanguageInterface;
use Drupal\file\Entity\File;
use Drupal\file\FileInterface;
use PHPUnit\Framework\Assert;
use function GuzzleHttp\Psr7\mimetype_from_extension;

/**
 * Provides common methods for s3 related tests.
 *
 * @package Drupal\Tests\acquia_contenthub_s3
 */
trait S3FileTestTrait {

  /**
   * Returns the contents of an arbitrary file fixture.
   *
   * @return array
   *   The cdf array.
   */
  protected function getFileFixture(string $file_name): array {
    $location = drupal_get_path('module', 'acquia_contenthub_s3') . "/tests/fixtures/file/$file_name";
    $data = json_decode(file_get_contents($location), TRUE);
    return is_array($data) ? $data : [];
  }

  /**
   * Saves and returns a file entity based on the file name and scheme.
   *
   * Optionally override every values by using the $values parameter.
   *
   * @param string $file_name
   *   The file name. Used to generate uri.
   * @param string $scheme
   *   The applicable scheme.
   * @param array $values
   *   Values to override file entity fields.
   *
   * @return \Drupal\file\FileInterface
   *   The newly created file entity.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function createFileEntity(string $file_name, string $scheme, array $values = []): FileInterface {
    if ($scheme === 's3') {
      $config = \Drupal::configFactory()->getEditable('s3fs.settings');
      if (!$config->get('bucket')) {
        $config->set('bucket', 'test-bucket')
          ->save();
      }
    }

    $path = explode('/', $file_name);
    $data = [
      'uuid' => \Drupal::service('uuid')->generate(),
      'langcode' => 'en',
      'uid' => 1,
      'filename' => end($path),
      'uri' => sprintf("$scheme://%s", implode('/', $path)),
      'filemime' => mimetype_from_extension($file_name),
      'filesize' => rand(1000, 5000),
      'status' => 1,
      'created' => time(),
    ];
    $data = array_merge($data, $values);
    $file = File::create($data);
    $file->save();

    return $file;
  }

  /**
   * Asserts CDF attribute values.
   *
   * @param \Acquia\ContentHubClient\CDF\CDFObject $cdf
   *   The cdf under test.
   * @param string $attribute
   *   The attribute to evaluate.
   * @param mixed $expected
   *   The expected value of the attribute.
   */
  protected function assertCdfAttribute(CDFObject $cdf, string $attribute, $expected): void {
    $attr = $cdf->getAttribute($attribute);
    if (is_null($attr)) {
      Assert::fail("Attribute ($attribute) doesn't exist!");
    }

    Assert::assertEquals(
      $cdf->getAttribute($attribute)->getValue()[LanguageInterface::LANGCODE_NOT_SPECIFIED],
      $expected,
      'CDF attribute value and expected value do not match.'
    );
  }

  /**
   * Configures s3fs.settings.
   *
   * @param string $bucket
   *   The s3 bucket name.
   * @param string $root_folder
   *   The 'subfolder' inside the bucket.
   *
   * @throws \Exception
   */
  protected function setS3fsConfig(string $bucket, string $root_folder): void {
    \Drupal::getContainer()->get('config.factory')
      ->getEditable('s3fs.settings')
      ->set('bucket', $bucket)
      ->set('root_folder', $root_folder)
      ->save();
  }

}
