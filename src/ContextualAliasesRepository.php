<?php

namespace Drupal\contextual_aliases;

use Drupal\Core\Path\AliasRepository;
use Drupal\workspaces\WorkspaceManagerInterface;

class ContextualAliasesRepository extends AliasRepository {

  /**
   * The workspace manager.
   *
   * @var \Drupal\workspaces\WorkspaceManagerInterface
   */
  protected $workspaceManager;

  /**
   * Sets the workspace manager.
   *
   * @param \Drupal\workspaces\WorkspaceManagerInterface $workspace_manager
   *   The workspace manager service.
   *
   * @return $this
   */
  public function setWorkspacesManager(WorkspaceManagerInterface $workspace_manager) {
    $this->workspaceManager = $workspace_manager;
    return $this;
  }

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

  /**
   * {@inheritdoc}
   */
  protected function getBaseQuery() {
    // Don't alter any queries if we're not in a workspace context.
    if (!$this->workspaceManager || !$this->workspaceManager->hasActiveWorkspace()) {
      return parent::getBaseQuery();
    }

    $active_workspace = $this->workspaceManager->getActiveWorkspace();

    $query = $this->connection->select('path_alias', 'base_table_2');
    $wa_join = $query->leftJoin('workspace_association', NULL, "%alias.target_entity_type_id = 'path_alias' AND %alias.target_entity_id = base_table_2.id AND %alias.workspace = :active_workspace_id", [
      ':active_workspace_id' => $active_workspace->id(),
    ]);
    $query->innerJoin('path_alias_revision', 'base_table', "%alias.revision_id = COALESCE($wa_join.target_entity_revision_id, base_table_2.revision_id)");

    return $query;
  }

}
