<?php

namespace Drupal\contextual_aliases;

use Drupal\Core\Language\LanguageInterface;
use Drupal\pathauto\AliasUniquifier;

class ContextualAliasUniquifier extends AliasUniquifier {

  /**
   * The workspace manager.
   *
   * @var \Drupal\contextual_aliases\ContextualAliasesContextManager
   */
  protected $contextManager;

  /**
   * Sets the workspace manager.
   *
   * @param \Drupal\contextual_aliases\ContextualAliasesContextManager
   *   The workspace manager service.
   *
   * @return $this
   */
  public function setContextManager($context_manager) {
    $this->contextManager = $context_manager;
    return $this;
  }

  /**
   * {@inheritDoc}
   */
  public function isReserved($alias, $source, $langcode = LanguageInterface::LANGCODE_NOT_SPECIFIED) {
    if ($context = $this->contextManager->getSourceContext($source)) {
      // If we have a context, run the uniquifier in that context.
      return $this->contextManager->executeInContext(function () use ($alias, $source, $langcode, $context) {
        if ($existing_source = $this->aliasManager->getPathByAlias($alias, $langcode)) {
          if ($existing_source != $alias) {
            $existing_context = $this->contextManager->getSourceContext($existing_source);
            return $existing_source != $source && $context == $existing_context;
          }
        }
        return FALSE;
      }, $context);
    }

    return parent::isReserved(
      $alias,
      $source,
      $langcode
    );
  }

}
