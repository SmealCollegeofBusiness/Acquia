<?php

namespace Drupal\Tests\acquia_contenthub\Kernel;

use Acquia\ContentHubClient\CDF\CDFObject;
use Drupal\acquia_contenthub\AcquiaContentHubEvents;
use Drupal\acquia_contenthub\Event\CreateCdfEntityEvent;
use Drupal\Core\Language\LanguageInterface;
use Drupal\file\Entity\File;
use Drupal\file\FileInterface;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Assert;
use function GuzzleHttp\Psr7\mimetype_from_extension;

/**
 * Tests the Private File Scheme Handler.
 *
 * @group acquia_contenthub
 * @coversDefaultClass \Drupal\acquia_contenthub\Plugin\FileSchemeHandler\PrivateFileSchemeHandler
 *
 * @requires module depcalc
 *
 * @package Drupal\Tests\acquia_contenthub\Kernel
 */
class PrivateFileSchemeHandlerTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'acquia_contenthub',
    'depcalc',
    'file',
    'user',
    'filter',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->installEntitySchema('file');
    $this->installEntitySchema('user');

    $config_factory = $this->container->get('config.factory');
    $config_factory->getEditable('acquia_contenthub.admin_settings')
      ->set('origin', 'aa1c7fd9-cffe-411e-baef-d0a2c67bddd4')
      ->save();
  }

  /**
   * @covers ::addAttributes
   */
  public function testAddAttributes() {
    $file = $this->createFileEntity('test.jpg', 'private');
    $event = new CreateCdfEntityEvent($file, []);
    $this->container->get('event_dispatcher')->dispatch(AcquiaContentHubEvents::CREATE_CDF_OBJECT, $event);

    $cdf = $event->getCdf($file->uuid());
    $this->assertCdfAttribute($cdf, 'file_scheme', 'private');
    $this->assertCdfAttribute($cdf, 'file_uri', $file->getFileUri());
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

}
