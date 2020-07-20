<?php

namespace Drupal\contextual_aliases\EntityQuery;

use Drupal\contextual_aliases\ContextualAliasesContextManager;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Query\ConditionInterface;
use Drupal\Core\Entity\Query\Sql\Query as BaseQuery;
use Drupal\Core\Render\Element;

/**
 * Alters entity queries to use a workspace revision instead of the default one.
 */
class Query extends BaseQuery {

  /**
   * The workspace manager.
   *
   * @var ContextualAliasesContextManager
   */
  protected $contextManager;

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
   * @param ContextualAliasesContextManager $contextManager
   *   The contextual aliases manager.
   */
  public function __construct(EntityTypeInterface $entity_type, $conjunction, Connection $connection, array $namespaces, ContextualAliasesContextManager $contextManager) {
    parent::__construct($entity_type, $conjunction, $connection, $namespaces);
    $this->contextManager = $contextManager;
  }

  /**
   * {@inheritdoc}
   */
  public function prepare() {
    parent::prepare();

    // 1. If there is no source path condition - add the current context condition.
    // 2. If there are any source path conditions - add the context to every single one of them with an AND.
    $conditions = &$this->condition->conditions();
    $this->addContextConditions($conditions);

    return $this;
  }

  /**
   * Adds the context conditions to the query, recursively if needed.
   *
   * @param array &$conditions
   *   An array of conditions as returned by the conditions() method.
   */
  protected function addContextConditions(&$conditions) {
    foreach (Element::children($conditions) as $key) {
      $condition = $conditions[$key];
      if ($condition['field'] instanceof ConditionInterface) {
        $nestedConditions = &$condition['field']->conditions();
        $this->addContextConditions($nestedConditions);
      } elseif (is_string($condition['field'])) {
        $isSourcePathCondition = $condition['field'] == 'path';
        $isAliasCondition = $condition['field'] == 'alias';

        if (!$isSourcePathCondition && !$isAliasCondition) {
          continue;
        }

        // Create an AND condition group and add add the original condition.
        $group = $this->andConditionGroup();
        $group->condition($condition['field'], $condition['value'], $condition['operator']);

        if ($isSourcePathCondition) {
          $sourceContext = $this->contextManager->getSourceContext($condition['value']);
          if ($sourceContext) {
            $group->condition('context', $sourceContext, '=');
          } else {
            $group->notExists('context');
          }
        }

        if ($isAliasCondition) {
          $context = $this->contextManager->getCurrentContext();

          if (!empty($context)) {
            $contextCondition = $this->orConditionGroup();
            $contextCondition->notExists('context');
            $contextCondition->condition('context', $context);
            $group->condition($contextCondition);
            $this->addOrderBy('context', 'DESC');
          } else {
            $group->notExists('context');
            $this->addOrderBy('context', 'ASC');
          }
        }

        // Add the condition.
        $conditions[$key]['field'] = $group;
      }
    }
  }

  /**
   * Adds a sort condition to the query, each combination only once.
   *
   * @param string $field
   *   The entity field to sort on.
   * @param $direction
   *   ASC or DESC.
   */
  protected function addOrderBy($field, $direction) {
    static $alreadyAdded = [];
    $key = "$field.$direction";
    if (!isset($alreadyAdded[$key])) {
      $this->sort($field, $direction);
      $alreadyAdded[$key] = TRUE;
    }
  }

}
