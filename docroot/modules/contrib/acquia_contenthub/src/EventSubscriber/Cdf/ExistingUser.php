<?php

namespace Drupal\acquia_contenthub\EventSubscriber\Cdf;

use Drupal\acquia_contenthub\AcquiaContentHubEvents;
use Drupal\acquia_contenthub\Event\ParseCdfEntityEvent;
use Drupal\user\Entity\User;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Prevent user name conflicts.
 */
class ExistingUser implements EventSubscriberInterface {

  public const GENERATED_USER_PATTERN = '%s (%s)';

  public const PATTERN_SPECIFIERS = [
    '%s', '%d', '%u', '%c', '%o', '%x', '%X', '%b', '%g', '%G', '%e', '%E', '%f', '%F',
  ];

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[AcquiaContentHubEvents::PARSE_CDF][] = ['onParseCdf', 90];
    return $events;
  }

  /**
   * Parses the CDF representation of Content Entities.
   *
   * @param \Drupal\acquia_contenthub\Event\ParseCdfEntityEvent $event
   *   Event object.
   */
  public function onParseCdf(ParseCdfEntityEvent $event) {
    $cdf = $event->getCDF();
    // Bail early if this isn't a user entity.
    if ($cdf->getAttribute('entity_type')->getValue()['und'] !== 'user') {
      return;
    }

    $username = $cdf->getAttribute('username')->getValue()['und'];
    /** @var \Drupal\user\UserInterface $account */
    $account = user_load_by_name($username);
    if (!$account) {
      // No local user by that name, proceed.
      return;
    }
    if ($account->uuid() === $event->getEntity()->uuid()) {
      // If the uuids are the same, these are the same user.
      return;
    }
    if ($account->getEmail() !== $event->getEntity()->getEmail()) {
      /** @var \Drupal\user\Entity\User $entity */
      $entity = $event->getEntity();
      $username = $this->generateUsername(self::GENERATED_USER_PATTERN, $cdf->getUuid(), $username);
      $entity->setUsername($username);
      $event->setEntity($entity);
    }
  }

  /**
   * Generate a Username.
   *
   * @param string $pattern
   *   Pattern to use to generate username.
   * @param string[] $pattern_arguments
   *   The arguments to use with the pattern.
   *
   * @return bool|string
   *   Username or false if not generated.
   *
   * @throws \Exception
   */
  public function generateUsername(string $pattern, string ...$pattern_arguments) { // @codingStandardsIgnoreLine
    if (empty($pattern)) {
      throw new \Exception("No pattern could be found for the generated username.");
    }

    $count = 0;
    foreach (self::PATTERN_SPECIFIERS as $specifier) {
      $count += substr_count($pattern, $specifier);
    }
    $arguments_count = count($pattern_arguments);
    if ($count !== $arguments_count) {
      throw new \Exception(sprintf("Mismatched number of pattern arguments to pattern expectations while attempting to generate username. Expected %d; received %d", $count, $arguments_count));
    }

    $username = sprintf($pattern, ...$pattern_arguments);

    if (empty($username)) {
      throw new \Exception("Could not generate a username.");
    }
    $max_length = User::USERNAME_MAX_LENGTH;
    if (strlen($username) > $max_length) {
      return substr($username, 0, $max_length);
    }

    return $username;
  }

}
