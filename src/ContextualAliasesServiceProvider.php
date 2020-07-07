<?php

namespace Drupal\contextual_aliases;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Drupal\contextual_aliases\Compiler\ContextualAliasesPass;
use Drupal\contextual_aliases\ContextualAliasesRepository;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Service provider that replaces the default alias storage.
 */
class ContextualAliasesServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container) {
    if ($container->has('path_alias.repository')) {
      $container->addCompilerPass(new ContextualAliasesPass());
    }
  }

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    if ($container->has('path_alias.repository')) {
      $container->getDefinition('path_alias.repository')
        ->setClass(ContextualAliasesRepository::class)
        ->addMethodCall('setContextManager', [new Reference('contextual_aliases.context_manager')]);

      if ($container->has('pathauto.alias_uniquifier')) {
        $container->getDefinition('pathauto.alias_uniquifier')
          ->setClass(ContextualAliasUniquifier::class)
          ->addMethodCall('setContextManager', [new Reference('contextual_aliases.context_manager')]);
      }

      if ($container->has('redirect.repository')) {
        $container->getDefinition('redirect.repository')
          ->setClass(ContextualRedirectRepository::class)
          ->addArgument(new Reference('path_alias.manager'));
      }
    }
  }

}
