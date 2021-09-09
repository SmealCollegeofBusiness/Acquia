<?php

namespace Drupal\Tests\collapsiblock\FunctionalJavascript;

/**
 * Test the "Remember collapsed state on active pages" behavior.
 *
 * @group collapsiblock
 */
class SlideTypeTest extends CollapsiblockJavaScriptTestBase {

  /**
   * A block to test with.
   *
   * @var \Drupal\block\BlockInterface
   */
  protected $collapsiblockTestBlock;

  /**
   * An XPath string for the test block's title.
   *
   * @var string
   */
  protected $collapsiblockTestBlockTitleXpath;

  /**
   * An XPath string for the test block's content.
   *
   * @var string
   */
  protected $collapsiblockTestBlockContentXpath;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    // Create a test block with the Block collapse behavior of "Collapsible,
    // expanded by default"; and prepare some XPath selectors to find the title
    // and content elements after that block undergoes client-side DOM
    // transformation.
    $this->collapsiblockTestBlock = $this->drupalPlaceBlock('system_powered_by_block', [
      'label_display' => TRUE,
    ]);
    $this->setCollapsiblockBlockInstanceSetting($this->collapsiblockTestBlock, 2, 'collapse_action');
    $testBlockHtmlId = 'block-' . $this->collapsiblockTestBlock->id();
    $this->collapsiblockTestBlockTitleXpath = $this->assertSession()->buildXPathQuery('//*[@id=:blockId]//h2', [
      ':blockId' => $testBlockHtmlId,
    ]);
    $this->collapsiblockTestBlockContentXpath = $this->assertSession()->buildXPathQuery('//*[@id=:blockId]//span', [
      ':blockId' => $testBlockHtmlId,
      ':class' => 'content',
    ]);
  }

  /**
   * Test the "Fade and Slide" slide type does NOT set aria-hidden attributes.
   */
  public function testFadeAndSlideSlideType() {
    // Set the Default animation type to "Fade and slide"; then flush all caches
    // so the global configuration setting change will take effect.
    $this->setCollapsiblockGlobalSetting(2, 'slide_type');
    drupal_flush_all_caches();

    // Load a page that the block will be displayed on.
    $this->drupalLogin($this->getCollapsiblockUnprivilegedUser());
    $this->drupalGet('<front>');

    // Check that, initially, the aria-hidden attribute on the content wrapper
    // does not exist.
    $beforeContent = $this->getSession()->getPage()->find('xpath', $this->collapsiblockTestBlockContentXpath);
    $this->assertNotNull($beforeContent);
    $this->assertFalse($beforeContent->hasAttribute('aria-hidden'));

    // Click on the block title to collapse the block.
    $this->getSession()->getPage()->find('xpath', $this->collapsiblockTestBlockTitleXpath)->click();

    // Check that, after hiding the block content, the aria-hidden attribute on
    // the content wrapper does not exist.
    $afterCollapseContent = $this->getSession()->getPage()->find('xpath', $this->collapsiblockTestBlockContentXpath);
    $this->assertNotNull($afterCollapseContent);
    $this->assertFalse($afterCollapseContent->hasAttribute('aria-hidden'));

    // Click on the block title to expand the block.
    $this->getSession()->getPage()->find('xpath', $this->collapsiblockTestBlockTitleXpath)->click();

    // Check that, after showing the block content, the aria-hidden attribute on
    // the content wrapper does not exist.
    $afterReExpandContent = $this->getSession()->getPage()->find('xpath', $this->collapsiblockTestBlockContentXpath);
    $this->assertNotNull($afterReExpandContent);
    $this->assertFalse($afterReExpandContent->hasAttribute('aria-hidden'));
  }

  /**
   * Test that the "Slide" slide type does set aria-hidden attributes.
   */
  public function testSlideSlideType() {
    // Set the Default animation type to "Slide"; then flush all caches so the
    // global configuration setting change will take effect.
    $this->setCollapsiblockGlobalSetting(1, 'slide_type');
    drupal_flush_all_caches();

    // Load a page that the block will be displayed on.
    $this->drupalLogin($this->getCollapsiblockUnprivilegedUser());
    $this->drupalGet('<front>');

    // Check that, initially, the aria-hidden wrapper on the content wrapper
    // does not exist.
    $beforeContent = $this->getSession()->getPage()->find('xpath', $this->collapsiblockTestBlockContentXpath);
    $this->assertNotNull($beforeContent);
    $this->assertFalse($beforeContent->hasAttribute('aria-hidden'));

    // Click on the block title to collapse the block.
    $this->getSession()->getPage()->find('xpath', $this->collapsiblockTestBlockTitleXpath)->click();

    // Check that, after toggling visibility, the aria-hidden wrapper on the
    // content wrapper exists, and is true (i.e.: collapsed).
    $afterCollapseContent = $this->getSession()->getPage()->find('xpath', $this->collapsiblockTestBlockContentXpath);
    $this->assertNotNull($afterCollapseContent);
    $this->assertTrue($afterCollapseContent->hasAttribute('aria-hidden'));
    $this->assertEqual($afterCollapseContent->getAttribute('aria-hidden'), 'true');

    // Click on the block title to expand the block.
    $this->getSession()->getPage()->find('xpath', $this->collapsiblockTestBlockTitleXpath)->click();

    // Check that, after showing the block content, the aria-hidden wrapper on
    // the content wrapper exists, and is false (i.e.: expanded).
    $afterReExpandContent = $this->getSession()->getPage()->find('xpath', $this->collapsiblockTestBlockContentXpath);
    $this->assertNotNull($afterReExpandContent);
    $this->assertEqual($afterReExpandContent->getAttribute('aria-hidden'), 'false');
  }

}
