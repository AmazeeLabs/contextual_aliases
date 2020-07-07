<?php

namespace Drupal\contextual_aliases\EntityQuery;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Query\QueryBase;
use Drupal\Core\Entity\Query\Sql\QueryFactory as BaseQueryFactory;
use Drupal\contextual_aliases\ContextualAliasesContextManager;

/**
 * Workspaces-specific entity query implementation.
 */
class QueryFactory extends BaseQueryFactory {

  /**
   * The workspace manager.
   *
   * @var ContextualAliasesContextManager
   */
  protected $contextManager;

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
   * @param ContextualAliasesContextManager $context_manager
   *   The contextual aliases manager.
   * @param bool $alter_entity_query
   */
  public function __construct(Connection $connection, ContextualAliasesContextManager $context_manager, Bool $alter_entity_query) {
    $this->connection = $connection;
    $this->namespaces = self::getClassNamespaces(get_parent_class($this));
    $this->contextManager = $context_manager;
    $this->alterEntityQuery = $alter_entity_query;
  }

  /**
   * {@inheritdoc}
   */
  public function get(EntityTypeInterface $entity_type, $conjunction) {
    if (!($this->contextManager instanceof ContextualAliasesContextManager) || $entity_type->id() != 'path_alias' || !$this->alterEntityQuery) {
      return parent::get($entity_type, $conjunction);
    }

    $namespaces = QueryBase::getNamespaces($this);
    $class = QueryBase::getClass($namespaces, 'Query');
    return new $class($entity_type, $conjunction, $this->connection, $namespaces, $this->contextManager);
  }

  /**
   * Gets a list of namespaces of the ancestors of a class.
   *
   * @param string $class
   *   A class name to start with.
   *
   * @return array
   *   A list containing the namespace of the class, the namespace of the
   *   parent of the class and so on and so on.
   */
  protected static function getClassNamespaces($class) {
    $namespaces = [];
    for ($tmpClass = $class; $tmpClass; $tmpClass = get_parent_class($tmpClass)) {
      $namespaces[] = substr($tmpClass, 0, strrpos($tmpClass, '\\'));
    }
    return $namespaces;
  }

}
