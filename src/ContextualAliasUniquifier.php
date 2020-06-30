<?php

namespace Drupal\contextual_aliases;

use Drupal\Core\Language\LanguageInterface;
use Drupal\pathauto\AliasUniquifier;

class ContextualAliasUniquifier extends AliasUniquifier {

  /**
   * {@inheritDoc}
   */
  public function isReserved($alias, $source, $langcode = LanguageInterface::LANGCODE_NOT_SPECIFIED) {
    assert($this->aliasManager instanceof ContextualAliasesManager);

    if ($context = $this->aliasManager->getSourceContext($source)) {
      // If we have a context, run the uniquifier in that context.
      return $this->aliasManager->executeInContext(function () use ($alias, $source, $langcode, $context) {
        if ($existing_source = $this->aliasManager->getPathByAlias($alias, $langcode)) {
          if ($existing_source != $alias) {
            $existing_context = $this->aliasManager->getSourceContext($existing_source);
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
