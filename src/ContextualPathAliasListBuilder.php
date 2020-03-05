<?php

namespace Drupal\contextual_aliases;

use Drupal\Component\Utility\Unicode;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Url;
use Drupal\contextual_aliases\Form\ContextualPathFilterForm;
use Drupal\path\PathAliasListBuilder;

class ContextualPathAliasListBuilder extends PathAliasListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header = [
      'alias' => [
        'data' => $this->t('Alias'),
        'field' => 'alias',
        'specifier' => 'alias',
        'sort' => 'asc',
      ],
      'path' => [
        'data' => $this->t('System path'),
        'field' => 'path',
        'specifier' => 'path',
      ],
      'context' => [
        'data' => $this->t('Context'),
        'field' => 'context',
        'specifier' => 'context',
      ],
    ];

    // Enable language column and filter if multiple languages are added.
    if ($this->languageManager->isMultilingual()) {
      $header['language_name'] = [
        'data' => $this->t('Language'),
        'field' => 'langcode',
        'specifier' => 'langcode',
        'class' => [RESPONSIVE_PRIORITY_MEDIUM],
      ];
    }
    $header['operations'] = $this->t('Operations');
    return $header;
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\Core\Path\Entity\PathAlias $entity */
    $langcode = $entity->language()->getId();
    $alias = $entity->getAlias();
    $path = $entity->getPath();
    $url = Url::fromUserInput($path);

    $row['data']['alias']['data'] = [
      '#type' => 'link',
      '#title' => Unicode::truncate($alias, 50, FALSE, TRUE),
      '#url' => $url->setOption('attributes', ['title' => $alias]),
    ];
    $row['data']['path']['data'] = [
      '#type' => 'link',
      '#title' => Unicode::truncate($path, 50, FALSE, TRUE),
      '#url' => $url->setOption('attributes', ['title' => $path]),
    ];

    $row['data']['context'] = $entity->context->value;

    if ($this->languageManager->isMultilingual()) {
      $row['data']['language_name'] = $this->languageManager->getLanguageName($langcode);
    }

    $row['data']['operations']['data'] = $this->buildOperations($entity);

    // If the system path maps to a different URL alias, highlight this table
    // row to let the user know of old aliases.
    if ($alias != $this->aliasManager->getAliasByPath($path, $langcode)) {
      $row['class'] = ['warning'];
    }

    return $row;
  }
 
}