<?php

namespace Drupal\Tests\acquia_contenthub_metatag\Kernel\EventSubscriber\SerializeContentField;

use Drupal\Tests\acquia_contenthub\Kernel\AcquiaContentHubSerializerTestBase;

/**
 * Tests Metatag Field Serialization.
 *
 * @group acquia_contenthub
 *
 * @package Drupal\Tests\acquia_contenthub_metatag\Kernel
 *
 * @covers \Drupal\acquia_contenthub_metatag\EventSubscriber\SerializeContentField\EntityMetatagsSerializer
 */
class MetatagFieldSerializerTest extends AcquiaContentHubSerializerTestBase {

  /**
   * Export tracking table name.
   */
  protected const TABLE_NAME = 'acquia_contenthub_publisher_export_tracking';

  /**
   * Metatag field name.
   */
  protected const FIELD_NAME = 'field_meta_tags';

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = [
    'acquia_contenthub_metatag',
    'metatag',
    'token',
  ];

  /**
   * {@inheritDoc}
   */
  public function setUp(): void {
    parent::setUp();
    self::$modules = array_merge(parent::$modules, self::$modules);

    $this->createContentType('field_meta_tags', 'metatag');
  }

  /**
   * Tests the serialization of the metatag field.
   *
   * @param int $do_not_transform
   *   Transform metatag canonical url.
   * @param string $rand_str
   *   Random string.
   *
   * @dataProvider dataProvider
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testTransformMetatagValues(int $do_not_transform, string $rand_str) {
    $values = [
      self::FIELD_NAME => serialize([
        'title' => '[node:title] | [site:name]',
        'description' => '[node:summary]',
        'canonical_url' => '[node:url]' . $rand_str,
      ]),
    ];
    $this->entity = $this->createNode($values);
    $field = $this->entity->get(self::FIELD_NAME);
    $expected_output = $this->prepareOutput($do_not_transform, $rand_str);

    $event = $this->dispatchSerializeEvent(self::FIELD_NAME, $field);
    $langcode = $event->getEntity()->language()->getId();
    $actual_output = unserialize($event->getFieldData()['value'][$langcode]['value']);

    // Check expected output after metatag field serialization.
    $this->assertEquals($expected_output, $actual_output);
  }

  /**
   * Dataprovider for testMetatagSerializer().
   *
   * @return array
   *   Mock output.
   */
  public function dataProvider(): array {
    $random_string = $this->randomMachineName();
    return [
      [
        0,
        $random_string,
      ],
      [
        1,
        $random_string,
      ],
    ];
  }

  /**
   * Prepare output for testMetatagSerializer.
   *
   * @param int $do_not_transform
   *   Transform metatag canonical url.
   * @param string $rand_str
   *   Random string.
   *
   * @return array
   *   Array containing metatag values.
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  protected function prepareOutput(int $do_not_transform, string $rand_str): array {
    $this->configFactory
      ->getEditable('acquia_contenthub_metatag.settings')
      ->set('ach_metatag_node_url_do_not_transform', $do_not_transform)
      ->save();

    $canonical_url = ($do_not_transform) ? '[node:url]' . $rand_str : $this->entity->toUrl()->setAbsolute()->toString() . $rand_str;

    return [
      "title" => "[node:title] | [site:name]",
      "description" => "[node:summary]",
      "canonical_url" => $canonical_url,
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function tearDown(): void {
    // Delete the previously created content type.
    $node_type = $this->entityTypeManager->getStorage('node_type')->load(self::BUNDLE);
    $node_type->delete();

    // Delete Acquia Content Hub admin/metatag settings.
    $this->configFactory->getEditable('acquia_contenthub.admin_settings')->delete();
    $this->configFactory->getEditable('acquia_contenthub_metatag.settings')->delete();

    parent::tearDown();
  }

}
