<?php

namespace Drupal\contextual_aliases;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Language\Language;
use Drupal\redirect\RedirectRepository;

class ContextualRedirectRepository extends RedirectRepository {

  /**
   * The alias storage.
   *
   * @var \Drupal\contextual_aliases\ContextualAliasesContextManager
   */
  protected $contextManager;

  public function __construct(
    EntityManagerInterface $manager,
    Connection $connection,
    ConfigFactoryInterface $config_factory,
    ContextualAliasesContextManager $context_manager
  ) {
    $this->contextManager = $context_manager;
    parent::__construct($manager, $connection, $config_factory);
  }

  public function findMatchingRedirect(
    $source_path,
    array $query = [],
    $language = Language::LANGCODE_NOT_SPECIFIED
  ) {
    $context = $this->contextManager->getCurrentContext();
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
    return parent::findMatchingRedirect(
      $source_path,
      $query,
      $language
    );
  }

}
