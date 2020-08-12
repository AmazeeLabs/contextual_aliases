<?php

namespace Drupal\contextual_aliases\EntityQuery;

use Drupal\contextual_aliases\ContextualAliasesContextManager;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Query\ConditionInterface;
use Drupal\Core\Entity\Query\Sql\Query as BaseQuery;
use Drupal\Core\Render\Element;
use function GuzzleHttp\Psr7\build_query;

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
  public function __construct(
    EntityTypeInterface $entity_type,
    $conjunction,
    Connection $connection,
    array $namespaces,
    ContextualAliasesContextManager $contextManager
  ) {
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
        // If we are dealing with a condition group, recurse into each
        // condition.
        $nestedConditions = &$condition['field']->conditions();
        $this->addContextConditions($nestedConditions);
      }
      elseif (is_string($condition['field'])) {
        $isSourcePathCondition = $condition['field'] == 'path';
        $isAliasCondition = $condition['field'] == 'alias';

        // If the current condition is not about path or alias, skip it.
        if (!($isSourcePathCondition || $isAliasCondition)) {
          continue;
        }

        if ($isSourcePathCondition) {
          // If the query is testing for a given path, get the paths context
          // and make it the current query context.
          $context = $this->contextManager->getSourceContext(
            // `loadByProperties` generates an 'IN' condition which accepts an
            // array. We just use the first entry to derive the context, since
            // in this case it can never be more.
            is_array($condition['value']) ? $condition['value'][0] : $condition['value']
          );
          // If the filter argument does not contain context information,
          // fall back to the current execution context.
          if (!$context) {
            $context = $this->contextManager->getCurrentContext();
          }
        }
        else {
          // If the query is testing for an alias, always use the current
          // execution context.
          $context = $this->contextManager->getCurrentContext();
        }


        // Create an AND condition group and add add the original condition.
        $group = $this->andConditionGroup();
        $group->condition(
          $condition['field'],
          $condition['value'],
          $condition['operator']
        );

        if (!empty($context)) {
          // Create a new condition group that selects the current context and
          // aliases in the fallback (null) context.
          $contextCondition = $this->orConditionGroup();
          $contextCondition->notExists('context');
          $contextCondition->condition('context', $context, '=');

          $group->condition($contextCondition);

          // Sort by context decending, so if both the current and the fallback
          // context exist, the current context is preferred.
          array_unshift(
            $this->sort,
            [
              'field' => 'context',
              'direction' => 'DESC',
              'langcode' => NULL,
            ]
          );
        }
        else {
          // If we are outside of a context, always make sure to use
          // aliases that are not related to a context.
          $group->notExists('context');
        }

        $conditions[$key]['field'] = $group;
      }
    }
  }
}
