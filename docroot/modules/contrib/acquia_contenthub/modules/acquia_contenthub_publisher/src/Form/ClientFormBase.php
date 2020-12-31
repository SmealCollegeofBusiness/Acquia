<?php

namespace Drupal\acquia_contenthub_publisher\Form;

use Drupal\acquia_contenthub\Client\ClientFactory;
use Drupal\Core\Form\FormBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class ClientFormBase.
 *
 * @package Drupal\acquia_contenthub_publisher\Form
 */
abstract class ClientFormBase extends FormBase {

  use SubscriptionManagerFormTrait;

  /**
   * The Acquia ContentHub Client object.
   *
   * @var \Acquia\ContentHubClient\ContentHubClient
   */
  protected $client;

  /**
   * The UUID of an item (a webhook or a client) to delete.
   *
   * @var string
   */
  protected $uuid;

  /**
   * SubscriptionManagerController constructor.
   *
   * @param \Drupal\acquia_contenthub\Client\ClientFactory $client_factory
   *   The client factory.
   */
  public function __construct(ClientFactory $client_factory) {
    $this->client = $client_factory->getClient();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('acquia_contenthub.client.factory')
    );
  }

}
