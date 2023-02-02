<?php

namespace Drupal\acquia_contenthub_dashboard\Commands;

use Drupal\acquia_contenthub_dashboard\Libs\ContentHubAllowedOrigins;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drush\Commands\DrushCommands;

/**
 * Drush commands for Acquia Content Hub Dashboard allowed origins.
 *
 * @package Drupal\acquia_contenthub_subscriber\Commands
 */
class ContentHubAllowedOriginsCommands extends DrushCommands {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The publisher registered webhooks.
   *
   * @var \Drupal\acquia_contenthub_dashboard\Libs\ContentHubAllowedOrigins
   */
  protected $allowedOrigins;

  /**
   * AcquiaContentHubPublisherAuditCommands constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\acquia_contenthub_dashboard\Libs\ContentHubAllowedOrigins $allowed_origins
   *   The publisher registered webhooks.
   */
  public function __construct(ConfigFactoryInterface $config_factory, ContentHubAllowedOrigins $allowed_origins) {
    $this->configFactory = $config_factory;
    $this->allowedOrigins = $allowed_origins;
  }

  /**
   * Fetch the publisher registered webhooks and store inside the drupal config.
   *
   * @command acquia:contenthub-dashboard-allowed-origins
   * @aliases ach-dashboard-allowed-origins, ach-dao
   *
   * @throws \Exception
   */
  public function allowedOrigins() {
    $new_allowed_origins = $this->allowedOrigins->getAllowedOrigins();
    if (!empty($new_allowed_origins)) {
      $config = $this->configFactory->getEditable('acquia_contenthub_dashboard.settings');
      $already_allowed_origins = $config->get('allowed_origins') ?? [];
      $origins_to_add = array_unique(array_merge($already_allowed_origins, $new_allowed_origins));

      $config->set('allowed_origins', $origins_to_add);
      $config->save();
    }
  }

}
