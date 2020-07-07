<?php

namespace Drupal\contextual_aliases;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Language\Language;
use Drupal\Core\Path\AliasManager;
use Drupal\redirect\RedirectRepository;

class ContextualRedirectRepository extends RedirectRepository {

  /**
   * The alias storage.
   *
   * @var \Drupal\Core\Path\AliasManager
   */
  protected $aliasManager;

  public function __construct(
    EntityManagerInterface $manager,
    Connection $connection,
    ConfigFactoryInterface $config_factory,
    AliasManager $aliasManager
  ) {
    $this->aliasManager = $aliasManager;
    parent::__construct($manager, $connection, $config_factory);
  }


  public function findMatchingRedirect(
    $source_path,
    array $query = [],
    $language = Language::LANGCODE_NOT_SPECIFIED
  ) {
    if ($this->aliasManager instanceof ContextualAliasesContextManager) {
      $context = $this->aliasManager->getCurrentContext();
      if ($context) {
        return parent::findMatchingRedirect(
          $context . '/' . $source_path,
          $query,
          $language
        ) ?: parent::findMatchingRedirect(
          $source_path,
          $query,
          $language
        );
      }
    }
    return parent::findMatchingRedirect(
      $source_path,
      $query,
      $language
    );
  }

}
