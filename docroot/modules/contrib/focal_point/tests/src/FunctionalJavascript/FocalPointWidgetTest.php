<?php

namespace Drupal\Tests\focal_point\FunctionalJavascript;

use Behat\Mink\Element\ElementInterface;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\image\Kernel\ImageFieldCreationTrait;

/**
 * Tests the Focal Point image field widget.
 *
 * @group focal_point
 */
class FocalPointWidgetTest extends WebDriverTestBase {

  use ImageFieldCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['focal_point', 'node'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->drupalCreateContentType(['type' => 'article']);
    $this->createImageField('field_image', 'article');

    $this->container->get('entity_display.repository')
      ->getFormDisplay('node', 'article')
      ->setComponent('field_image', [
        'type' => 'image_focal_point',
      ])
      ->save();
  }

  /**
   * Tests that preview works after changing the default focal point.
   */
  public function testChangeFocalPointAndPreview() {
    $assert_session = $this->assertSession();

    $account = $this->drupalCreateUser(['create article content']);
    $this->drupalLogin($account);
    $this->drupalGet('/node/add/article');

    $uri = uniqid('public://') . '.png';
    $uri = $this->getRandomGenerator()->image($uri, '100x100', '100x100');
    $path = $this->container->get('file_system')->realpath($uri);
    $this->assertNotEmpty($path);
    $this->assertFileExists($path);

    $this->getSession()->getPage()->attachFileToField('field_image', $path);
    $preview_image = $assert_session->waitForElementVisible('css', '.focal-point-indicator');
    $this->assertNotEmpty($preview_image);

    $get_preview_url = function () use ($assert_session) {
      return $assert_session->elementExists('css', '.focal-point-preview-link')
        ->getAttribute('href');
    };

    // Change the default focal point and ensure that we can open a preview
    // which reflects that.
    $old_preview_url = $get_preview_url();
    $this->setFocalPoint($preview_image, 10, 10);
    $new_preview_url = $get_preview_url();
    $this->assertNotSame($old_preview_url, $new_preview_url);

    $assert_session->elementExists('css', '.focal-point-preview-link')->click();
  }

  /**
   * Sets the focal point.
   *
   * This method directly calls methods of the underlying WebDriver session, so
   * it will fail if this test is not using the Selenium2 Mink driver.
   *
   * @param \Behat\Mink\Element\ElementInterface $preview_image
   *   The preview image element.
   * @param int $x_offset
   *   The X offset of the focal point, relative to the preview image.
   * @param int $y_offset
   *   The Y offset of the focal point, relative to the preview image.
   */
  private function setFocalPoint(ElementInterface $preview_image, $x_offset, $y_offset) {
    /** @var \Behat\Mink\Driver\Selenium2Driver $driver */
    $driver = $this->getSession()->getDriver();
    $this->assertInstanceOf('\Behat\Mink\Driver\Selenium2Driver', $driver);

    $wd = $driver->getWebDriverSession();

    // We need to get the preview image's element ID (a concept specific to
    // WebDriver) in order to click the mouse in a position relative to it.
    /** @var \WebDriver\Element $element */
    $element = $wd->element('xpath', $preview_image->getXpath());
    $this->assertInstanceOf('\WebDriver\Element', $element);

    $wd->moveto([
      'element' => $element->getID(),
      'xoffset' => $x_offset,
      'yoffset' => $y_offset,
    ]);
    // 0 is the left mouse button, according to the WebDriver specification.
    $wd->click(['button' => 0]);
  }

}
