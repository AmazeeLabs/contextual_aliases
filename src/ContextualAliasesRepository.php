<?php

namespace Drupal\contextual_aliases;

use Drupal\path_alias\AliasRepository;

class ContextualAliasesRepository extends AliasRepository {

  /**
   * The workspace manager.
   *
   * @var \Drupal\contextual_aliases\ContextualAliasesManager
   */
  protected $aliasesManager;

  /**
   * Sets the workspace manager.
   *
   * @param \Drupal\contextual_aliases\ContextualAliasesManager
   *   The workspace manager service.
   *
   * @return $this
   */
  public function setAliasManager($aliases_manager) {
    $this->aliasesManager = $aliases_manager;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function lookupByAlias($alias, $langcode) {
    // See the queries above. Use LIKE for case-insensitive matching.
    $select = $this->getBaseQuery()
      ->fields('base_table', ['id', 'path', 'alias', 'langcode'])
      ->condition('base_table.alias', $this->connection->escapeLike($alias), 'LIKE');

    $this->addContextConditions(
      $select,
      $this->aliasesManager->getCurrentContext()
    );

    $this->addLanguageFallback($select, $langcode);

    $select->orderBy('base_table.id', 'DESC');

    return $select->execute()->fetchAssoc() ?: NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function lookupBySystemPath($path, $langcode) {
    // See the queries above. Use LIKE for case-insensitive matching.
    $select = $this->getBaseQuery()
      ->fields('base_table', ['id', 'path', 'alias', 'langcode', 'context'])
      ->condition('base_table.path', $this->connection->escapeLike($path), 'LIKE');

    $this->addContextConditions(
      $select,
      $this->aliasesManager->getSourceContext($path)
    );

    $this->addLanguageFallback($select, $langcode);

    $select->orderBy('base_table.id', 'DESC');

    return $select->execute()->fetchAssoc() ?: NULL;
  }

  /**
   * Add context conditions to the given query.
   *
   * @param \Drupal\Core\Database\Query\SelectInterface $select
   * @param null|string $context
   */
  protected function addContextConditions($select, $context = NULL) {
    if (!empty($context)) {
      $contextCondition = $select->orConditionGroup();
      $contextCondition->isNull('base_table.context');
      $contextCondition->condition('base_table.context', $context);
      $select->condition($contextCondition);
      $select->orderBy('base_table.context', 'DESC');
    } else {
      $select->isNull('base_table.context');
      $select->orderBy('base_table.context', 'ASC');
    }
  }

}
