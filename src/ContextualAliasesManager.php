<?php

namespace Drupal\contextual_aliases;

use Drupal\Core\Language\LanguageInterface;
use Drupal\path_alias\AliasManager;

class ContextualAliasesManager extends AliasManager {
	
  /**
   * The list of alias context resolvers.
   *
   * @var AliasContextResolverInterface[]
   */
  protected $contextResolvers = [];

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
   * {@inheritdoc}
   */
  public function getPathByAlias($alias, $langcode = NULL) {
    // If no language is explicitly specified we default to the current URL
    // language. If we used a language different from the one conveyed by the
    // requested URL, we might end up being unable to check if there is a path
    // alias matching the URL path.
    $langcode = $langcode ?: $this->languageManager->getCurrentLanguage(LanguageInterface::TYPE_URL)->getId();

    // If we already know that there are no paths for this alias simply return.
    if (empty($alias) || !empty($this->noPath[$langcode][$alias])) {
      return $alias;
    }

    // Look for the alias within the cached map.
    if (isset($this->lookupMap[$langcode]) && ($path = array_search($alias, $this->lookupMap[$langcode]))) {
      return $path;
    }

    $context = $this->getCurrentContext();

    // Look for path in storage.
    if ($path_alias = $this->pathAliasRepository->lookupByAlias($alias, $langcode, $context)) {
      $this->lookupMap[$langcode][$path_alias['path']] = $alias;
      return $path_alias['path'];
    }

    // We can't record anything into $this->lookupMap because we didn't find any
    // paths for this alias. Thus cache to $this->noPath.
    $this->noPath[$langcode][$alias] = TRUE;

    return $alias;
  }

  /**
   * {@inheritdoc}
   */
  public function getAliasByPath($path, $langcode = NULL) {
    if ($path[0] !== '/') {
      throw new \InvalidArgumentException(sprintf('Source path %s has to start with a slash.', $path));
    }
    // If no language is explicitly specified we default to the current URL
    // language. If we used a language different from the one conveyed by the
    // requested URL, we might end up being unable to check if there is a path
    // alias matching the URL path.
    $langcode = $langcode ?: $this->languageManager->getCurrentLanguage(LanguageInterface::TYPE_URL)->getId();

    // Check the path whitelist, if the top-level part before the first /
    // is not in the list, then there is no need to do anything further,
    // it is not in the database.
    if ($path === '/' || !$this->whitelist->get(strtok(trim($path, '/'), '/'))) {
      return $path;
    }

    // During the first call to this method per language, load the expected
    // paths for the page from cache.
    if (empty($this->langcodePreloaded[$langcode])) {
      $this->langcodePreloaded[$langcode] = TRUE;
      $this->lookupMap[$langcode] = [];

      // Load the cached paths that should be used for preloading. This only
      // happens if a cache key has been set.
      if ($this->preloadedPathLookups === FALSE) {
        $this->preloadedPathLookups = [];
        if ($this->cacheKey) {
          if ($cached = $this->cache->get($this->cacheKey)) {
            $this->preloadedPathLookups = $cached->data;
          }
          else {
            $this->cacheNeedsWriting = TRUE;
          }
        }
      }

      // Load paths from cache.
      if (!empty($this->preloadedPathLookups[$langcode])) {
        $this->lookupMap[$langcode] = $this->pathAliasRepository->preloadPathAlias($this->preloadedPathLookups[$langcode], $langcode);
        // Keep a record of paths with no alias to avoid querying twice.
        $this->noAlias[$langcode] = array_flip(array_diff_key($this->preloadedPathLookups[$langcode], array_keys($this->lookupMap[$langcode])));
      }
    }

    // If we already know that there are no aliases for this path simply return.
    if (!empty($this->noAlias[$langcode][$path])) {
      return $path;
    }

    // If the alias has already been loaded, return it from static cache.
    if (isset($this->lookupMap[$langcode][$path])) {
      return $this->lookupMap[$langcode][$path];
    }

    // Try to load alias from storage.
    if ($path_alias = $this->pathAliasRepository->lookupBySystemPath($path, $langcode)) {
      $this->lookupMap[$langcode][$path] = $path_alias['alias'];
      return $path_alias['alias'];
    }

    // We can't record anything into $this->lookupMap because we didn't find any
    // aliases for this path. Thus cache to $this->noAlias.
    $this->noAlias[$langcode][$path] = TRUE;
    return $path;
  }

}
