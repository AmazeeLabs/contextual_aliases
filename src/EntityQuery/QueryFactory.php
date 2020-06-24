<?php

namespace Drupal\contextual_aliases\EntityQuery;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Query\QueryBase;
use Drupal\Core\Entity\Query\Sql\QueryFactory as BaseQueryFactory;
use Drupal\contextual_aliases\ContextualAliasesManager;
use Drupal\path_alias\AliasManager;

/**
 * Workspaces-specific entity query implementation.
 */
class QueryFactory extends BaseQueryFactory {

  /**
   * The workspace manager.
   *
   * @var \Drupal\contextual_aliases\ContextualAliasesManager
   */
  protected $contextualAliasesManager;

  /**
   * A parameter determining if the entity query should be altered.
   *
   * @var bool
   */
  protected $alterEntityQuery;

  /**
   * Constructs a QueryFactory object.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection used by the entity query.
   * @param \Drupal\path_alias\AliasManager $contextual_aliases_manager
   *   The contextual aliases manager.
   */
  public function __construct(Connection $connection, AliasManager $contextual_aliases_manager, Bool $alter_entity_query) {
    assert($contextual_aliases_manager instanceof ContextualAliasesManager);
    $this->connection = $connection;
    $this->contextualAliasesManager = $contextual_aliases_manager;
    $this->alterEntityQuery = $alter_entity_query;
    $this->namespaces = QueryBase::getNamespaces($this);
  }

  /**
   * {@inheritdoc}
   */
  public function get(EntityTypeInterface $entity_type, $conjunction) {
    if (!$this->alterEntityQuery) {
      return parent::get($entity_type, $conjunction);
    }
    $class = QueryBase::getClass($this->namespaces, 'Query');
    return new $class($entity_type, $conjunction, $this->connection, $this->namespaces, $this->contextualAliasesManager);
  }

}
