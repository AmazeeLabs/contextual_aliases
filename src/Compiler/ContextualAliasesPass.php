<?php

namespace Drupal\contextual_aliases\Compiler;

use Drupal\contextual_aliases\ContextualAliasesRepository;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class ContextualAliasesPass implements CompilerPassInterface {

  /**
   * {@inheritdoc}
   */
  public function process(ContainerBuilder $container) {
    $container->getDefinition('path_alias.repository')
      ->setClass(ContextualAliasesRepository::class);
  }

}