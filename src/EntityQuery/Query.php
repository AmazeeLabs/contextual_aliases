<?php

namespace Drupal\contextual_aliases\EntityQuery;

use Drupal\contextual_aliases\ContextualAliasesManager;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Query\Sql\Query as BaseQuery;
use Drupal\Core\Path\AliasManager;

/**
 * Alters entity queries to use a workspace revision instead of the default one.
 */
class Query extends BaseQuery {
  /**
   * The workspace manager.
   *
   * @var \Drupal\Core\Path\AliasManager
   */
  protected $aliasManager;

  /**
   * Constructs a Query object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param string $conjunction
   *   - AND: all of the conditions on the query need to match.
   *   - OR: at least one of the conditions on the query need to match.
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection to run the query against.
   * @param array $namespaces
   *   List of potential namespaces of the classes belonging to this query.
   * @param \Drupal\Core\Path\AliasManager $alias_manager
   *   The contextual aliases manager.
   */
  public function __construct(EntityTypeInterface $entity_type, $conjunction, Connection $connection, array $namespaces, AliasManager $alias_manager) {
    parent::__construct($entity_type, $conjunction, $connection, $namespaces);
    $this->aliasManager = $alias_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function prepare() {
    parent::prepare();

    if (!($this->aliasManager instanceof ContextualAliasesManager)) {
      return $this;
    }

    $context = $this->aliasManager->getCurrentContext();
    if (!empty($context)) {
      $contextCondition = $this->sqlQuery->orConditionGroup();
      $contextCondition->isNull('base_table.context');
      $contextCondition->condition('base_table.context', $context);
      $this->sqlQuery->condition($contextCondition);
      $this->sqlQuery->orderBy('base_table.context', 'DESC');
    } else {
      $this->sqlQuery->isNull('base_table.context');
    }

    return $this;
  }

}
