<?php

namespace Drupal\contextual_aliases;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Service provider that replaces the default alias storage.
 */
class ContextualAliasesServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    $container->getDefinition('path_alias.repository')
      ->setClass(ContextualAliasesRepository::class);

    $container->getDefinition('path_alias.manager')
      ->setClass(ContextualAliasesManager::class)
      ->addTag('service_collector', [
        'tag' => 'alias_context_resolver',
        'call' => 'addContextResolver',
      ]);

    /*$container->getDefinition('path.alias_storage')
      ->setClass(ContextualAliasStorage::class)
      ->addTag('service_collector', [
        'tag' => 'alias_context_resolver',
        'call' => 'addContextResolver',
      ]);*/

    if ($container->has('pathauto.alias_uniquifier')) {
      $container->getDefinition('pathauto.alias_uniquifier')
        ->setClass(ContextualAliasUniquifier::class)
        ->addArgument(new Reference('path.alias_storage'));
    }

    if ($container->has('redirect.repository')) {
      $container->getDefinition('redirect.repository')
        ->setClass(ContextualRedirectRepository::class)
        ->addArgument(new Reference('path.alias_storage'));
    }
  }

}