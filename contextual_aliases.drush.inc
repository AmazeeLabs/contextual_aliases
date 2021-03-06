<?php

use \Drupal\contextual_aliases\ContextualAliasStorage;
use Drupal\Core\Entity\EntityStorageException;

/**
 * Implements hook_drush_command().
 */
function contextual_aliases_drush_command() {
  $commands = [];

  $commands['contextual-aliases-update'] = [
    'description' => 'Update aliases without context.',
  ];

  $commands['contextual-aliases-repair-redirects'] = [
    'description' => 'Update aliases without context.',
  ];

  return $commands;
}

function drush_contextual_aliases_repair_redirects() {
  /** @var \Drupal\Core\Database\Connection $db */
  $db = \Drupal::service('database');
  $query = $db->select('redirect');
  $query->isNotNull('context');
  $count = $query->countQuery()->execute()->fetchField();
  $batchSize = 50;
  $batchCount = ceil($count / $batchSize);

  drush_print('Updating ' . $count . ' redirects.');

  $batch = [
    'title' => t('Updating redirects.'),
    'operations' => array_map(function ($step) use ($batchSize, $count) {
      return ['drush_contextual_aliases_refresh_redirects_batch', [$step, $batchSize, $count]];
    }, range(0, $batchCount - 1)),
  ];

  batch_set($batch);
  drush_backend_batch_process();
}

/**
 * One batch of alias updates.
 */
function drush_contextual_aliases_refresh_redirects_batch($step, $size, $total) {
  $offset = $step * $size;
  $limit = ($step + 1) * $size;
  drush_print("Updating redirects $offset to $limit of $total.");

  /** @var \Drupal\Core\Database\Connection $db */
  $ids = \Drupal::entityQuery('redirect')
    ->exists('context')
    ->range($offset, $size)
    ->execute();
  /** @var \Drupal\redirect\Entity\Redirect $redirect */
  foreach (\Drupal\redirect\Entity\Redirect::loadMultiple($ids) as $redirect) {
    try {
      $redirect->save();
    } catch (EntityStorageException $exception) {
      drush_log("Deleting redirect: {$redirect->redirect_source->value} -> {$redirect->redirect_redirect->value} in context {$redirect->context->value}.");
      $redirect->delete();
    }
  }
}

/**
 * Implements hook_drush_command().
 */
function drush_contextual_aliases_update() {
  /** @var \Drupal\Core\Database\Connection $db */
  $db = \Drupal::service('database');
  $query = $db->select(ContextualAliasStorage::TABLE);
  $query->isNull('context');
  $count = $query->countQuery()->execute()->fetchField();
  $batchSize = 50;
  $batchCount = ceil($count / $batchSize);

  drush_print('Updating ' . $count . ' aliases.');

  $batch = [
    'title' => t('Updating aliases.'),
    'operations' => array_map(function ($step) use ($batchSize, $count) {
      return ['drush_contextual_aliases_update_batch', [$step, $batchSize, $count]];
    }, range(0, $batchCount - 1)),
  ];

  batch_set($batch);
  drush_backend_batch_process();
}

/**
 * One batch of alias updates.
 */
function drush_contextual_aliases_update_batch($step, $size, $total) {
  $offset = $step * $size;
  $limit = ($step + 1) * $size;
  drush_print("Updating aliases $offset to $limit of $total.");

  /** @var \Drupal\Core\Entity\EntityStorageInterface $redirectStorage */
  $redirectStorage = NULL;
  if (drush_module_exists('redirect')) {
    $redirectStorage = \Drupal::service('entity_type.manager')->getStorage('redirect');
  }

  /** @var ContextualAliasStorage $storage */
  $storage = \Drupal::service('path.alias_storage');

  /** @var \Drupal\Core\Database\Connection $db */
  $db = \Drupal::service('database');
  $query = $db->select(ContextualAliasStorage::TABLE);
  $query->fields(ContextualAliasStorage::TABLE);
  $query->isNull('context');
  $query->range($offset, $size);

  foreach ($query->execute()->fetchAll() as $row) {
    // If redirect is enabled, also update affected redirects.
    if ($redirectStorage) {
      /** @var \Drupal\redirect\Entity\Redirect[] $redirects */
      $redirects = $redirectStorage->loadByProperties(['redirect_redirect__uri' => 'internal:' . $row->source]);
      foreach ($redirects as $redirect) {
        $redirect->save();
      }
    }
    // Re-save alias to trigger context resolution.
    try {
      $storage->save($row->source, $row->alias, $row->langcode, $row->pid);
    } catch (Exception $exc) {}
  }

}
