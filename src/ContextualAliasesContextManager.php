<?php

namespace Drupal\contextual_aliases;

use Drupal\Core\Language\LanguageInterface;
use Drupal\path_alias\AliasManager;

class ContextualAliasesContextManager {

  /**
   * The list of alias context resolvers.
   *
   * @var AliasContextResolverInterface[]
   */
  protected $contextResolvers = [];

  /**
   * Context override has priority over the resolved one.
   *
   * @var null|string
   */
  protected $contextOverride = NULL;

  /**
   * Add an alias context resolver.
   */
  public function addContextResolver(AliasContextResolverInterface $resolver) {
    $this->contextResolvers[] = $resolver;
  }

  /**
   * Retrieve the contexts for a given source path.
   *
   * @param string $source
   *   Source path.
   *
   * @return string
   *   The list of source contexts.
   */
  public function getSourceContext($source) {
    foreach ($this->contextResolvers as $resolver) {
      if ($context = $resolver->resolveContext($source)) {
        return $context;
      }
    }
    return NULL;
  }

  /**
   * Retrieve the current context.
   *
   * @return string
   *   The identifier for the current context.
   */
  public function getCurrentContext() {
    if ($this->contextOverride) {
      return $this->contextOverride;
    }
    foreach ($this->contextResolvers as $resolver) {
      if ($context = $resolver->getCurrentContext()) {
        return $context;
      }
    }
    return NULL;
  }

  /**
   * List of possible contexts.
   *
   * @return array
   *   The options array of contexts.
   */
  public function getContextOptions() {
    $return = array_reduce(array_map(function (AliasContextResolverInterface $resolver) {
      return $resolver->getContextOptions();
    }, $this->contextResolvers), 'array_merge', []);
    return $return;
  }

  /**
   * Execute a callable within a given context.
   *
   * Used to simulate context during certain procedures.
   *
   * @param callable $callable
   *   The callable to execute.
   * @oaram string $context
   *   The context to set.
   *
   * @throws \Exception
   *   Whatever exception the callable throws.
   *
   * @return mixed
   *   The callable return value.
   */
  public function executeInContext(callable $callable, $context) {
    $this->contextOverride = $context;
    try {
      return $callable();
    } catch(\Exception $exc) {
      throw $exc;
    } finally {
      $this->contextOverride = NULL;
    }
  }

}
