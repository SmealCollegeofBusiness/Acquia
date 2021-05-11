<?php

namespace Drupal\Tests\acquia_contenthub\Unit\EventSubscriber\Cdf;

use Drupal\acquia_contenthub\EventSubscriber\Cdf\ExistingUser;
use Drupal\Component\Uuid\Php;
use Drupal\Tests\UnitTestCase;

/**
 * Tests username generation.
 *
 * @group acquia_contenthub
 *
 * @package Drupal\Tests\acquia_contenthub\Unit\EventSubscriber\Cdf
 *
 * @covers \Drupal\acquia_contenthub\EventSubscriber\Cdf\ExistingUser::generateUsername
 */
class GenerateUserNameTest extends UnitTestCase {

  /**
   * Tests username generation to make sure character limit truncation occurs.
   */
  public function testUsernameGeneration() {
    $existingUser = new ExistingUser();
    $uuidGenerator = new Php();
    $uuid = $uuidGenerator->generate();
    $valid_user = $existingUser->generateUsername($existingUser::GENERATED_USER_PATTERN, $uuid, 'asimplename');
    $long_user = $existingUser->generateUsername($existingUser::GENERATED_USER_PATTERN, $uuid, 'alongusernamethatwillnotfitinthenormalsixtycharacterlimit');

    $this->assertEquals($uuid . ' (asimplename)', $valid_user);
    $this->assertEquals($uuid . ' (alongusernamethatwilln', $long_user);

    $pattern_sub = $existingUser->generateUsername("%s, %d, %s", "foo", 13, "bar");
    $this->assertEquals("foo, 13, bar", $pattern_sub);

    try {
      $existingUser->generateUsername('');
      $this->fail("Expected exception not properly thrown.");
    }
    catch (\Exception $exception) {
      $this->assertSame("No pattern could be found for the generated username.", $exception->getMessage());
    }

    try {
      $existingUser->generateUsername("%s %s %d %G", "foo", "bar", 13);
      $this->fail("Expected exception not properly thrown.");
    }
    catch (\Exception $exception) {
      $this->assertSame("Mismatched number of pattern arguments to pattern expectations while attempting to generate username. Expected 4; received 3", $exception->getMessage());
    }

    try {
      $existingUser->generateUsername("%d", "foo");
      $this->fail("Expected exception not properly thrown.");
    }
    catch (\Exception $exception) {
      $this->assertSame("Could not generate a username.", $exception->getMessage());
    }
  }

}
