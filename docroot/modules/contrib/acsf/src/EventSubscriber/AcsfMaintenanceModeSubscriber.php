<?php

namespace Drupal\acsf\EventSubscriber;

use Drupal\acsf\AcsfCompatibilityHelper;
use Drupal\Core\State\StateInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\KernelEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Response subscriber to add SF maintenance mode header.
 */
class AcsfMaintenanceModeSubscriber implements EventSubscriberInterface {

  /**
   * Name of ACSF maintenance mode's response header.
   */
  public const HEADER = 'X-SF-Maintenance';

  /**
   * The state.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * Constructs a new AcsfMaintenanceModeSubscriber.
   *
   * @param \Drupal\Core\State\StateInterface $state
   *   The state.
   */
  public function __construct(StateInterface $state) {
    $this->state = $state;
  }

  /**
   * Sets SF maintenance mode header (on successful responses).
   *
   * @param \Symfony\Component\HttpKernel\Event\KernelEvent $event
   *   The event to process.
   */
  public function onRespond(KernelEvent $event) {
    if (!AcsfCompatibilityHelper::checkKernelEventIsMain($event)) {
      return;
    }

    if ($this->state->get('system.maintenance_mode')) {
      $response = $event->getResponse();
      $response->headers->set(self::HEADER, 'enabled');
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = [];
    $events[KernelEvents::RESPONSE][] = ['onRespond'];
    return $events;
  }

}
