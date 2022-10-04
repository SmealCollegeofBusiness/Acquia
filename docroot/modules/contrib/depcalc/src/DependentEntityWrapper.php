<?php

namespace Drupal\depcalc;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;

/**
 * An entity wrapper class for finding and tracking dependencies of an entity.
 */
class DependentEntityWrapper implements DependentEntityWrapperInterface {

  /**
   * The entity id.
   *
   * @var int|null|string
   */
  protected $id;

  /**
   * The entity type id.
   *
   * @var string
   */
  protected $entityTypeId;

  /**
   * The entity uuid.
   *
   * @var null|string
   */
  protected $uuid;

  /**
   * The sha1 hash value of the entity.
   *
   * @var string
   */
  protected $hash;

  /**
   * The remote uuid of the entity if it differs from the ContentHub uuid.
   *
   * @var string
   */
  protected $remoteUuid;

  /**
   * The list of uuid/hash values of dependencies of this entity.
   *
   * @var string[]
   */
  protected $dependencies = [];

  /**
   * The list of uuid/hash values of direct child dependencies of this entity.
   *
   * @var string[]
   */
  protected $childDependencies = [];

  /**
   * The modules this entity requires to operate.
   *
   * @var string[]
   */
  protected $modules = [];

  /**
   * Whether this entity needs additional processing.
   *
   * @var bool
   */
  protected $additionalProcessing;


  /**
   * DependentEntityWrapper constructor.
   *
   * The entity object is thrown away within this constructor and just the bare
   * minimum of details to reconstruct it are kept. This is to reduce memory
   * overhead during the run time of dependency calculation.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity for which we are calculating dependencies.
   * @param bool $additional_processing
   *   Whether or not the entity will require additional processing.
   *
   * @throws \Exception
   */
  public function __construct(EntityInterface $entity, $additional_processing = FALSE) {
    $this->entityTypeId = $entity->getEntityTypeId();
    $this->id = $entity->id();
    $uuid = $entity->uuid();
    $this->hash = $this->calculateHash($entity);
    if (empty($uuid)) {
      throw new \Exception(sprintf("The entity of type %s by id %s does not have a UUID. This indicates a larger problem with your application and should be remedied before attempting to calculate dependencies.", $this->entityTypeId, $this->id));
    }
    $this->uuid = $uuid;
    $this->additionalProcessing = $additional_processing;
  }

  /**
   * Calculates hash of an entity using its translations.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity in hand.
   *
   * @return string
   *   The calculated sha1 hash.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function calculateHash(EntityInterface $entity): string {
    if (!$entity instanceof ContentEntityInterface) {
      return sha1(json_encode($entity->toArray()));
    }
    $langs = array_keys($entity->getTranslationLanguages());
    $vals = [];
    foreach ($langs as $lang) {
      $translation = $entity->getTranslation($lang);
      $vals[$lang] = $this->getNormalizedEntityArray($translation);
    }

    return sha1(json_encode($vals));
  }

  /**
   * Returns a normalized array of the entity in hand.
   *
   * Entity field values are not always representing the actual state. While
   * in certain cases that could be fine, the hash calculation needs to be
   * accurate in order to achieve more performant caching.
   * Reason: https://www.drupal.org/project/drupal/issues/2978521
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to transform.
   *
   * @return array
   *   The entity field values.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getNormalizedEntityArray(ContentEntityInterface $entity): array {
    $values = [];
    foreach ($entity->getFieldDefinitions() as $name => $definition) {
      $val = $entity->get($name)->getValue();
      if (!empty($val) && ($definition->getType() === 'entity_reference' || $definition->getType() === 'entity_reference_revision')) {
        $type = $definition->getSetting('target_type');
        foreach ($val as $i => $item) {
          $sub_entity = \Drupal::entityTypeManager()->getStorage($type)->load($item['target_id']);
          if (!$sub_entity) {
            unset($val[$i]);
          }
        }

      }
      $values[$name] = $val;
    }
    return $values;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntity() {
    return \Drupal::entityTypeManager()->getStorage($this->entityTypeId)->load($this->id);
  }

  /**
   * {@inheritdoc}
   */
  public function setRemoteUuid($uuid) {
    $this->remoteUuid = $uuid;
  }

  /**
   * {@inheritdoc}
   */
  public function getRemoteUuid() {
    if (!empty($this->remoteUuid)) {
      return $this->remoteUuid;
    }
    return $this->getUuid();
  }

  /**
   * {@inheritdoc}
   */
  public function getId() {
    return $this->id;
  }

  /**
   * {@inheritdoc}
   */
  public function getUuid() {
    return $this->uuid;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityTypeId() {
    return $this->entityTypeId;
  }

  /**
   * {@inheritdoc}
   */
  public function addDependency(DependentEntityWrapperInterface $dependency, DependencyStack $stack, bool $direct_child = TRUE) {
    // Don't save a thing as a dependency of itself.
    if ($dependency->getUuid() === $this->getUUid()) {
      return;
    }
    if (!array_key_exists($dependency->getUuid(), $this->dependencies)) {
      $this->dependencies[$dependency->getUuid()] = $dependency->getHash();
      // Add this dependency to direct child dependency array.
      if ($direct_child && !array_key_exists($dependency->getUuid(), $this->childDependencies)) {
        // Minimal data needed to load this child entity.
        $this->childDependencies[$dependency->getUuid()] = $dependency->getHash();
      }
      if (!$stack->hasDependency($dependency->getUuid())) {
        $stack->addDependency($dependency);
        foreach ($stack->getDependenciesByUuid(array_keys($dependency->getDependencies())) as $sub_dependency) {
          // Since these are sub-dependencies, so setting the boolean to false.
          $this->addDependency($sub_dependency, $stack, FALSE);
        }
      }
      else {
        $this->addDependencies($stack, ...array_values($stack->getDependenciesByUuid(array_keys($stack->getDependency($dependency->getUuid())->getDependencies()))));
      }
      $modules = $stack->getDependency($dependency->getUuid())->getModuleDependencies();
      if ($modules) {
        $this->addModuleDependencies($modules);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function addDependencies(DependencyStack $stack, DependentEntityWrapperInterface ...$dependencies) {
    foreach ($dependencies as $dependency) {
      // This can't be added as a child because $dependencies holds
      // all the dependencies for a given entity whether direct or not,
      // so boolean set to false.
      $this->addDependency($dependency, $stack, FALSE);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getDependencies() {
    return $this->dependencies;
  }

  /**
   * {@inheritdoc}
   */
  public function addModuleDependencies(array $modules) {
    $this->modules = array_values(array_unique(NestedArray::mergeDeep($this->modules, $modules)));
  }

  /**
   * {@inheritdoc}
   */
  public function getModuleDependencies() {
    return $this->modules;
  }

  /**
   * {@inheritdoc}
   */
  public function getHash() {
    return $this->hash;
  }

  /**
   * {@inheritdoc}
   */
  public function needsAdditionalProcessing() {
    return $this->additionalProcessing;
  }

  /**
   * {@inheritDoc}
   */
  public function getChildDependencies(): array {
    return $this->childDependencies;
  }

}
