<?php

namespace Drupal\acquia_contenthub_publisher\Commands;

use Acquia\ContentHubClient\CDF\CDFObject;
use Acquia\ContentHubClient\CDF\CDFObjectInterface;
use Drupal\acquia_contenthub\Client\ClientFactory;
use Drupal\acquia_contenthub\ContentHubCommonActions;
use Drupal\acquia_contenthub_publisher\PublisherTracker;
use Drupal\Component\Uuid\Uuid;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\depcalc\DependencyCalculator;
use Drush\Commands\DrushCommands;
use Symfony\Component\Console\Helper\Table;

/**
 * Drush commands for Acquia Content Hub Publishers Audit Entity.
 *
 * @package Drupal\acquia_contenthub_publisher\Commands
 */
class AcquiaContentHubPublisherAuditEntityCommands extends DrushCommands {

  use DependencySerializationTrait;

  const RE_ORIGINATE   = "RE_ORIGINATE";
  const WEBHOOK_CHECK  = "WEBHOOK_CHECK";
  const NEEDS_REEXPORT = "REEXPORT";

  /**
   * The queue object.
   *
   * @var \Drupal\Core\Queue\QueueInterface
   */
  protected $queue;

  /**
   * The published entity tracker.
   *
   * @var \Drupal\acquia_contenthub_publisher\PublisherTracker
   */
  protected $tracker;

  /**
   * The Dependency Calculator.
   *
   * @var \Drupal\depcalc\DependencyCalculator
   */
  protected $calculator;

  /**
   * The Content Hub Common Actions Service.
   *
   * @var \Drupal\acquia_contenthub\ContentHubCommonActions
   */
  protected $commonActions;

  /**
   * The Content Hub Client Factory.
   *
   * @var \Drupal\acquia_contenthub\Client\ClientFactory
   */
  protected $factory;

  /**
   * Sets the Result of the evaluation.
   *
   * @var string[]
   */
  protected $results = [];

  /**
   * The Content Hub Client.
   *
   * @var \Acquia\ContentHubClient\ContentHubClient
   */
  protected $client;

  /**
   * AcquiaContentHubPublisherAuditEntityCommands constructor.
   *
   * @param \Drupal\acquia_contenthub_publisher\PublisherTracker $tracker
   *   The published entity tracker.
   * @param \Drupal\Core\Queue\QueueFactory $queue_factory
   *   Queue factory.
   * @param \Drupal\depcalc\DependencyCalculator $calculator
   *   The Dependency Calculator.
   * @param \Drupal\acquia_contenthub\ContentHubCommonActions $common_actions
   *   The Content Hub Common Actions Service.
   * @param \Drupal\acquia_contenthub\Client\ClientFactory $factory
   *   The Content Hub Client Factory.
   */
  public function __construct(PublisherTracker $tracker, QueueFactory $queue_factory, DependencyCalculator $calculator, ContentHubCommonActions $common_actions, ClientFactory $factory) {
    $this->queue = $queue_factory->get('acquia_contenthub_publish_export');
    $this->tracker = $tracker;
    $this->calculator = $calculator;
    $this->commonActions = $common_actions;
    $this->factory = $factory;
  }

  /**
   * Audits an entity for differences with existing CDF in Acquia Content Hub.
   *
   * @param string $entity_type
   *   Entity type.
   * @param string $id
   *   Entity ID or UUID.
   *
   * @usage drush acquia:contenthub-audit-entity node 123
   *   Audits the node with nid = 123 and all its dependencies.
   * @usage drush ach-audit-entity taxonomy_term d470026c-f248-4771-acd1-300a7d6ccbce
   *   Audits the taxonomy term with UUID=d470026c-f248-4771-acd1-300a7d6ccbce.
   * @usage drush ach-ae node 53fd2ed2-5d29-4028-9423-0713ef2f82b3
   *   Audits the node with UUID = 53fd2ed2-5d29-4028-9423-0713ef2f82b3.
   *
   * @command acquia:contenthub-audit-entity
   * @aliases ach-audit-entity, ach-ae
   *
   * @throws \Exception
   */
  public function auditEntity(string $entity_type, string $id) {
    if (empty($entity_type) || empty($id)) {
      throw new \Exception(dt("Missing required parameters: entity_type and entity_id (or entity_uuid)"));
    }
    $storage = \Drupal::entityTypeManager()->getStorage($entity_type);
    if (empty($storage)) {
      throw new \Exception(sprintf("The provided entity_type = '%s' does not exist.", $entity_type));
    }
    if (Uuid::isValid($id)) {
      $entity = $storage->loadByProperties(['uuid' => $id]);
      $entity = reset($entity);
    }
    else {
      $entity = $storage->load($id);
    }
    if (empty($entity)) {
      throw new \Exception(sprintf("The entity (%s, %s) does not exist.", $entity_type, $id));
    }

    // Obtaining Client Connection to Acquia Content Hub.
    $this->client = $this->factory->getClient();
    if (empty($this->client)) {
      throw new \Exception("This site is not Connected to Acquia Content Hub. Please check your configuration settings.");
    }
    $remote_cdf = $this->client->getEntity($entity->uuid());
    if (!($remote_cdf instanceof CDFObjectInterface)) {
      throw new \Exception("This entity was not exported yet. Please export it first.");
    }

    // Calculate the dependencies for the local entity.
    $data = $this->getEntityDependencies($entity);
    $cdf = $data[$entity->uuid()];
    $hash = $cdf->getAttribute('hash')->getValue()['und'];
    $remote_hash = $remote_cdf->getAttribute('hash')->getValue()['und'];
    $remote_dependencies = $remote_cdf->getDependencies();
    $dependencies = $cdf->getDependencies();

    // Verifying local and remote origins.
    $origin = $cdf->getOrigin();
    $remote_origin = $remote_cdf->getOrigin();

    // Auditing given Entity.
    $this->auditEntityCdf($entity, $origin, $remote_origin, $hash, $remote_hash, $dependencies, $remote_dependencies, $cdf, $remote_cdf);

    // Only keep analyzing if we are in the correct site.
    if ($origin == $remote_origin) {
      // Analyzing entity dependencies.
      $this->auditEntityDependencies($entity, $cdf, $dependencies, $data, $remote_dependencies, $hash, $remote_hash);

      // Analyzing module dependencies.
      $this->auditModuleDependencies($cdf, $remote_cdf);
    }

    // Present the action that needs to be taken.
    return $this->showAuditResults($entity, $cdf, $dependencies, $origin, $remote_origin);
  }

  /**
   * Audits the Entity CDF.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to audit.
   * @param string $origin
   *   The locally generated origin.
   * @param string $remote_origin
   *   The remote origin from the CDF.
   * @param string $hash
   *   The locally generated hash.
   * @param string $remote_hash
   *   The remote hash.
   * @param array $dependencies
   *   The locally generated entity dependencies.
   * @param array $remote_dependencies
   *   The remote dependencies obtained from the CDF.
   * @param \Acquia\ContentHubClient\CDF\CDFObjectInterface $cdf
   *   The CDF locally generated CDF.
   * @param \Acquia\ContentHubClient\CDF\CDFObjectInterface $remote_cdf
   *   The remote CDF stored in Acquia Content Hub.
   */
  protected function auditEntityCdf(EntityInterface $entity, string $origin, string $remote_origin, string $hash, string $remote_hash, array $dependencies, array $remote_dependencies, CDFObjectInterface $cdf, CDFObjectInterface $remote_cdf) {
    // Obtaining the record in the Publisher Tracking Table.
    $tracked_entity = $this->tracker->getRecord($entity->uuid());
    $tracked_state = strtoupper($tracked_entity->status);
    if ($tracked_entity->status === PublisherTracker::QUEUED) {
      $tracked_status = "<fg=yellow;options=bold>{$tracked_state}</>";
      $queue_id = $this->tracker->getQueueId($tracked_entity->entity_uuid);
      if ($queue_id) {
        $tracked_label = sprintf("<comment>Entity is already in the Publisher Queue with item_id = %s.</comment>", $queue_id);
      }
      else {
        $tracked_label = sprintf("<error>Entity is reported as Queued but is not in the Publisher Queue. Requires a re-export.</error>", $queue_id);
        $this->setResults(self::NEEDS_REEXPORT);
      }
    }
    elseif ($tracked_entity->status === PublisherTracker::EXPORTED) {
      $tracked_status = "<fg=yellow;options=bold>{$tracked_state}</>";
      $tracked_label = '<comment>Entity did not receive confirmation status. Check that this site is receiving webhooks.</comment>';
      $this->setResults(self::WEBHOOK_CHECK);
    }
    else {
      $tracked_status = "<info>{$tracked_state}</info>";
      $tracked_label = '<info>OK</info>';
    }

    // Verifying the origin matches remote origin.
    if ($origin !== $remote_origin) {
      $origin_label = sprintf('<error>Remote CDF was exported from another origin. Requires re-origination or purge to fix.</error>', $tracked_entity->hash);
      $this->setResults(self::RE_ORIGINATE);
    }
    else {
      $origin_label = "<info>OK</info>";
    }

    // Verifying that the tracked hash coincides with local or remote CDF.
    if ($tracked_entity->hash !== $hash) {
      $hash_label = sprintf('<error>Exported with an outdated hash: "%s". Requires a re-export.</error>', $tracked_entity->hash);
      $this->setResults(self::NEEDS_REEXPORT);
    }
    elseif ($hash !== $remote_hash) {
      $hash_label = '<error>Hash Mismatch. Requires a re-export.</error>';
      $this->setResults(self::NEEDS_REEXPORT);
    }
    else {
      $hash_label = '<info>OK</info>';
    }

    // Verifying number of dependencies.
    if (count($dependencies) == count($remote_dependencies)) {
      $dependencies_label = '<info>OK</info>';
    }
    else {
      $dependencies_label = '<error># of Dependencies Mismatch. Requires a re-export.</error>';
      $this->setResults(self::NEEDS_REEXPORT);
    }

    // Writing data into the terminal.
    $content = [
      ['Type', $cdf->getType(), $remote_cdf->getType(), ''],
      ['Entity Type', $entity->getEntityTypeId(),
        $remote_cdf->getAttribute('entity_type')->getValue()['und'],
        '',
      ],
      ['Entity Bundle',
        $entity->bundle(),
        $remote_cdf->getAttribute('bundle')->getValue()['und'],
        '',
      ],
      ['Entity ID', $entity->id(), '', ''],
      ['Entity UUID', $entity->uuid(), $remote_cdf->getUuid(), ''],
      ['Origin', $origin, $remote_origin, $origin_label],
      ['Hash', $hash, $remote_hash, $hash_label],
      ['Publisher Tracker Status', $tracked_status, '', $tracked_label],
      ['Publisher Tracker Created',
        $tracked_entity->created,
        $remote_cdf->getCreated(),
        'There could be small variations.',
      ],
      ['Publisher Tracker Modified',
        $tracked_entity->modified,
        $remote_cdf->getModified(),
        'There could be small variations.',
      ],
      ['# of Dependencies',
        count($dependencies),
        count($remote_dependencies),
        $dependencies_label,
      ],
    ];
    $message = sprintf("Analyzing CDF Entity: {$entity->getEntityTypeId()}/{$entity->id()}: {$entity->uuid()}");
    $headers = [
      'Parameter',
      'Local',
      'Remote',
      'Notes',
    ];
    $this->printTableOutput($message, $headers, $content);
  }

  /**
   * Audits Entity Dependencies.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to analyze.
   * @param \Acquia\ContentHubClient\CDF\CDFObjectInterface $cdf
   *   The locally generated CDF.
   * @param array $dependencies
   *   An array of depedendencies.
   * @param array $data
   *   An array of CDF Objects generated locally.
   * @param array $remote_dependencies
   *   The remote dependencies.
   * @param string $hash
   *   The locally generated entity hash.
   * @param string $remote_hash
   *   The remote CDF hash.
   *
   * @throws \Exception
   */
  protected function auditEntityDependencies(EntityInterface $entity, CDFObjectInterface $cdf, array $dependencies, array $data, array $remote_dependencies, string $hash, string $remote_hash) {
    $entity_type = $entity->getEntityTypeId();
    $content = [];
    $dep = 0;
    $content[] = [
      $dep++,
      $this->getTypeShort($cdf->getType()),
      $entity_type,
      $entity->bundle(),
      $entity->uuid(),
      $hash,
      $remote_hash,
      $remote_hash === $hash ? '<info>OK</info>' : '<error>Fail</error>',
    ];
    $dependencies_check = TRUE;
    foreach ($dependencies as $duuid => $dhash) {
      $remote_hash = $remote_dependencies[$duuid] ?: '<error>Not found</error>';
      if (isset($remote_dependencies[$duuid])) {
        unset($remote_dependencies[$duuid]);
      }
      $content[] = [
        $dep++,
        $this->getTypeShort($data[$duuid]->getType()),
        $data[$duuid]->getAttribute('entity_type')->getValue()['und'],
        $data[$duuid]->getAttribute('bundle') ? $data[$duuid]->getAttribute('bundle')->getValue()['und'] : '',
        $duuid,
        $dhash,
        $remote_hash,
        $remote_hash === $dhash ? '<info>OK</info>' : '<error>Fail</error>',
      ];
      $dependencies_check = $dependencies_check && ($remote_hash === $dhash);
    }
    // Iterating among the last remote dependencies.
    foreach ($remote_dependencies as $ruuid => $rhash) {
      $dependencies_check = FALSE;
      // Check if we can get the remote entity.
      $remote_entity = $this->client->getEntity($ruuid);
      if ($remote_entity) {
        $remote_type = $this->getTypeShort($remote_entity->getType());
        $rentity_type = $remote_entity->getAttribute('entity_type')->getValue()['und'];
        $remote_bundle = $remote_entity->getAttribute('bundle') ? $remote_entity->getAttribute('bundle')->getValue()['und'] : NULL;
      }
      $content[] = [
        '-',
        $remote_type ?: '<comment>Unknown</comment>',
        $rentity_type ?: '<comment>Unknown</comment>',
        $remote_bundle ?: '<comment>Unknown</comment>',
        $ruuid,
        '',
        $rhash,
        '<error>Fail</error>',
      ];
    }
    if (!$dependencies_check) {
      $this->setResults(self::NEEDS_REEXPORT);
    }

    $message = sprintf('CDF Entity Dependencies, Local vs Remote Analysis:');
    $headers = [
      '#',
      'Type',
      'Entity Type',
      'Entity Bundle',
      'UUID',
      'Hash',
      'Remote Hash',
      'Match',
    ];
    $this->printTableOutput($message, $headers, $content);
  }

  /**
   * Audits module dependencies.
   *
   * @param \Acquia\ContentHubClient\CDF\CDFObjectInterface $cdf
   *   The locally generated CDF.
   * @param \Acquia\ContentHubClient\CDF\CDFObjectInterface $remote_cdf
   *   The remote CDF.
   */
  protected function auditModuleDependencies(CDFObjectInterface $cdf, CDFObjectInterface $remote_cdf) {
    $modules = $cdf->getModuleDependencies();
    $remote_modules = $remote_cdf->getModuleDependencies();
    $m = 1;
    $modules_check = TRUE;
    $content = [];
    foreach ($modules as $module) {
      $remote_module = NULL;
      if (in_array($module, $remote_modules)) {
        $remote_module = $module;
        $remote_modules = array_diff($remote_modules, [$remote_module]);
      }
      $content[] = [
        $m++,
        $module,
        $remote_module ?? '',
        $remote_module ? '<info>OK</info>' : '<error>Fail</error>',
      ];
      $modules_check = $modules_check && (bool) $remote_module;
    }
    foreach ($remote_modules as $remote_module) {
      $content[] = [
        $m++,
        '',
        $remote_module,
        '<error>Fail</error>',
      ];
      $modules_check = FALSE;
    }
    if (!$modules_check) {
      $this->setResults(self::NEEDS_REEXPORT);
    }

    $message = sprintf('CDF Module Dependencies, Local vs Remote Analysis:');
    $headers = [
      '#',
      'Local',
      'Remote',
      'Match',
    ];
    $this->printTableOutput($message, $headers, $content);
  }

  /**
   * Prints Table Output.
   *
   * @param string $title
   *   The title of the Table.
   * @param array $headers
   *   The headers of the table.
   * @param array $content
   *   The content of the table.
   */
  protected function printTableOutput(string $title, array $headers, array $content) {
    $this->output()->writeln($title);
    (new Table($this->output))
      ->setHeaders($headers)
      ->setRows($content)
      ->render();
  }

  /**
   * Deletes depcalc cache, nullify hashes and enqueues entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Entity to export.
   * @param array $dependencies
   *   An array of dependencies.
   *
   * @throws \Exception
   */
  protected function reExportEntity(EntityInterface $entity, array $dependencies) {
    // Deleting depcalc cache entries so they are re-calculated.
    $uuids = array_merge([$entity->uuid()], array_keys($dependencies));
    $backend = \Drupal::cache('depcalc');
    $backend->deleteMultiple($uuids);

    // Nullifying hashes in tracking table.
    $this->tracker->nullifyHashes([], [], $uuids);

    // Enqueue entity.
    _acquia_contenthub_publisher_enqueue_entity($entity, 'update');
    $this->output->writeln(sprintf(
      'Entity (%s, %s): "%s" has been enqueued for export.',
      $entity->getEntityTypeId(),
      $entity->id(),
      $entity->uuid()
    ));
    $this->output->writeln('Also, the "depcalc" cache for this entity and all its dependencies has been cleared and Hashes Nullified.');
  }

  /**
   * Calculates all the dependencies of the current entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to calculate dependencies from.
   *
   * @return \Acquia\ContentHubClient\CDF\CDFObject[]|array
   *   An array of CDF Objects.
   *
   * @throws \Exception
   */
  protected function getEntityDependencies(EntityInterface $entity): array {
    $entities = [];
    $data = [];
    $objects = $this->commonActions->getEntityCdf($entity, $entities, FALSE, TRUE);
    foreach ($objects as $object) {
      $data[$object->getUuid()] = $object;
    }
    return $data;
  }

  /**
   * Returns the abbreviated version of the CDF Type.
   *
   * @param string $type
   *   The CDF type.
   *
   * @return string|null
   *   The Abbreviated version of the type or null.
   */
  protected function getTypeShort(string $type) {
    switch ($type) {
      case 'drupal8_config_entity':
        return 'config';

      case 'drupal8_content_entity':
        return 'content';

      default:
        return NULL;
    }
  }

  /**
   * Presents Results of the Audit and actions to take.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to audit.
   * @param \Acquia\ContentHubClient\CDF\CDFObject $cdf
   *   The CDF Object.
   * @param array $dependencies
   *   The list of entity dependencies.
   * @param string $origin
   *   The origin UUID.
   * @param string $remote_origin
   *   The remote origin UUID.
   *
   * @return bool
   *   The drush return.
   *
   * @throws \Exception
   */
  protected function showAuditResults(EntityInterface $entity, CDFObject $cdf, array $dependencies, string $origin, string $remote_origin):bool {
    $this->output->writeln('');
    $this->output->writeln('Results from the Audit:');
    $this->output->writeln('');
    if ($this->getResult(self::WEBHOOK_CHECK)) {
      $this->output->writeln('<comment>* Possible Webhook Issue</comment>:');
      $this->output->writeln('We detected that the entity did not have a <info>CONFIRMED</info> status. This could be caused by the site not receiving webhooks correctly.');
      $this->output->writeln('Make sure the site is able to receive webhooks by checking that:');
      $this->output->writeln(' - The Webhook URL is not suppressed.');
      $this->output->writeln(' - A mis-configured "shield" module could be blocking the reception of webhooks.');
      $this->output->writeln(' - An .htaccess apache redirect rule could be blocking the reception of webhooks.');
      $this->output->writeln(' - A CDN rule could be preventing the site to receive webhooks.');
      $this->output->writeln(' - etc. There are unlimited possible cases.');
      $this->output->writeln('You can tell that the issue is solved if you see log strings starting with "Webhook landing" in the Drupal Watchdog.');
      $this->output->writeln('');
    }
    if ($this->getResult(self::RE_ORIGINATE)) {
      $this->output->writeln('<error>* Client site ORIGIN does not match published Entity ORIGIN:</error>');
      $this->output->writeln(sprintf('You are trying to publish an entity with an origin (%s) that does not have ownership over the entity with UUID = "%s" (origin = "%s")', $origin, $cdf->getUuid(), $remote_origin));
      $this->output->writeln('Are you sure you are in the correct site?")');
      $owner = $this->client->getEntity($remote_origin);
      if ($owner instanceof CDFObjectInterface) {
        $webhook = $owner->getWebhook();
        $domain = $webhook['settings_url'] ?? '';
        $client_name = $owner->getClientName()->getValue()['und'];
        $this->output->writeln(sprintf('The client that has ownership of this content is: "%s" (%s).', $client_name, $domain));
        $this->output->writeln('');
      }
      else {
        $clients = $this->client->getClients();
        $origins = array_column($clients, 'name', 'uuid');
        if (isset($origins[$remote_origin])) {
          $this->output->writeln(sprintf('The client that has ownership of this content is: "%s".', $origins[$remote_origin]));
          $this->output->writeln('');
        }
        else {
          $this->output->writeln('The client that has ownership of this content does not seem to exist anymore in this subscription.');
          $this->output->writeln('<error>You cannot Export this content from this Publisher.</error>');
          $this->output->writeln('');
        }
      }

      $this->output->writeln('In order to fix this issue you can:');
      $this->output->writeln(' - Find the Site Origin where this content was originally published and run this command from there.');
      $this->output->writeln(' - If the original publisher origin still exist you can re-originate this content to the new publisher.');
      $this->output->writeln(' - If the original publisher origin does not exist anymore, you could purge the subscription and republish all content.');
      $this->output->writeln('');
      return TRUE;
    }

    // If the entity needs to be re-exported.
    if ($this->getResult(self::NEEDS_REEXPORT)) {
      $this->output->writeln('<error>* Entity needs to be re-exported</error>:');
      $this->output->writeln('The diagnostic shows that to fix the highlighted issues you need to re-export this content.');
      $this->output->writeln('');
      if ($this->io()->confirm('Do you want to re-export this entity and all it\'s dependencies?')) {
        $this->reExportEntity($entity, $dependencies);
      }
    }
    else {
      $this->output->writeln('Entity does not need to be re-exported.');
      $this->output->writeln('');
    }
    $this->output->writeln('Task completed.');
    return TRUE;
  }

  /**
   * Adds a result state to the results array.
   *
   * @param string $result
   *   The result state.
   */
  protected function setResults(string $result) {
    if (!in_array($result, $this->results)) {
      $this->results[] = $result;
    }
  }

  /**
   * Checks if the result state is found.
   *
   * @param string $result
   *   The result state to check for.
   *
   * @return bool
   *   TRUE if it is found, FALSE otherwise.
   */
  protected function getResult(string $result): bool {
    return in_array($result, $this->results);
  }

}
