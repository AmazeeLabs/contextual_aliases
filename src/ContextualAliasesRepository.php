<?php

namespace Drupal\contextual_aliases;

use Drupal\path_alias\AliasRepository;

class ContextualAliasesRepository extends AliasRepository {
  
  /**
   * {@inheritdoc}
   */
  public function lookupByAlias($alias, $langcode, $context = NULL) {
    // See the queries above. Use LIKE for case-insensitive matching.
    $select = $this->getBaseQuery()
      ->fields('base_table', ['id', 'path', 'alias', 'langcode'])
      ->condition('base_table.alias', $this->connection->escapeLike($alias), 'LIKE');

    if (!empty($context)) {
      $contextCondition = $select->orConditionGroup();
      $contextCondition->isNull('context');
      $contextCondition->condition('context', $context);
      $select->condition($contextCondition);
      $select->orderBy('context', 'DESC');
    } 

    $this->addLanguageFallback($select, $langcode);

    $select->orderBy('base_table.id', 'DESC');

    return $select->execute()->fetchAssoc() ?: NULL;
  }

}