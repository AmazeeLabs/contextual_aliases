<?php

namespace Drupal\Tests\contextual_aliases\Kernel;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Path\AliasWhitelistInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\contextual_aliases\AliasContextResolverInterface;
use Drupal\contextual_aliases\ContextualAliasesContextManager;
use Prophecy\Argument;
use Symfony\Component\DependencyInjection\Definition;

/**
 * Kernel tests for contextual alias storage.
 */
class ContextualAliasesTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['system', 'contextual_aliases', 'options', 'entity_test', 'user'];

  /**
   * The alias storage.
   *
   * @var \Drupal\Core\Path\AliasStorageInterface
   */
  protected $aliasStorage;

  /**
   * The mocked instance of a context resolver.
   *
   * @var AliasContextResolverInterface
   */
  protected $resolverInstance;

  /**
   * The resolvers prophecy.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy
   */
  protected $resolver;

  /**
   * The alias manager.
   *
   * @var \Drupal\Core\Path\AliasManagerInterface
   */
  protected $manager;

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container) {
    parent::register($container);
    $this->resolver = $this->prophesize(AliasContextResolverInterface::class);
    $this->resolverInstance = $this->resolver->reveal();

    $definition = new Definition(get_class($this->resolverInstance));
    $definition->setFactory([$this, 'getResolverInstance']);

    $definition->addTag('alias_context_resolver');
    $container->addDefinitions([
      'test.alias_context_resolver' => $definition,
    ]);
  }

  /**
   * Factory method to get the mocked AliasContextResolver.
   *
   * @return \Drupal\contextual_aliases\AliasContextResolverInterface
   */
  public function getResolverInstance() {
    return $this->resolverInstance;
  }

  protected function createPathAlias($path, $alias, $langcode = LanguageInterface::LANGCODE_NOT_SPECIFIED, $context = NULL) {
    /** @var \Drupal\path_alias\PathAliasInterface $path_alias */
    $path_alias = $this->aliasStorage->create([
      'path' => $path,
      'alias' => $alias,
      'langcode' => $langcode,
      'context' => $context,
    ]);
    $path_alias->save();

    return $path_alias;
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $whitelist = $this->prophesize(AliasWhitelistInterface::class);
    $whitelist->get(Argument::any())->willReturn(TRUE);
    $this->container->set('path_alias.whitelist', $whitelist->reveal());
    $this->installEntitySchema('path_alias');
    $this->installEntitySchema('user');
    $this->installEntitySchema('entity_test');

    $this->resolver->resolveContext('/a')->willReturn('one');
    $this->resolver->resolveContext('/b')->willReturn('two');
    $this->resolver->resolveContext('/c')->willReturn(NULL);
    $this->resolver->resolveContext('/c-context-one')->willReturn('one');
    $this->resolver->resolveContext('/d')->willReturn(NULL);
    $this->resolver->resolveContext('/d-no-context')->willReturn(NULL);
    $this->resolver->resolveContext('/e')->willReturn('two');
    $this->resolver->resolveContext('/f')->willReturn('one');
    $this->resolver->getCurrentContext()->willReturn(NULL);

    $this->aliasStorage = \Drupal::entityTypeManager()->getStorage('path_alias');

    $this->createPathAlias('/a', '/A', 'en', 'one');
    $this->createPathAlias('/b', '/A', 'en');
    $this->createPathAlias('/b', '/B', 'en', 'two');
    $this->createPathAlias('/c', '/C', 'en');
    $this->createPathAlias('/c-context-one', '/C', 'en');
    $this->createPathAlias('/d-no-context', '/D', 'en');
    $this->createPathAlias('/d', '/one/D', 'en');
    $this->createPathAlias('/e', '/one/E', 'en', 'two');

    $this->manager = $this->container->get('path_alias.manager');
  }

  /**
   * Test for vanilla aliases without contexts.
   */
  public function testNoContextSimpleAlias() {
    $this->resolver->getCurrentContext()->willReturn(NULL);
    $this->assertEquals('/c', $this->manager->getPathByAlias('/C'));
    $this->assertEquals('/C', $this->manager->getAliasByPath('/c'));
  }

  /**
   * Test contextual aliases outside of global context.
   */
  public function testNoContextContextualAlias() {
    $this->resolver->getCurrentContext()->willReturn(NULL);
    // The source context is overridden in contextual_aliases_entity_presave, so
    // there is no source path under /A with no context.
    $this->assertEquals('/A', $this->manager->getPathByAlias('/A'));
    $this->assertEquals('/A', $this->manager->getAliasByPath('/a'));
  }

  /**
   * Test contextual aliases within a matching global context.
   */
  public function testContextMatchingAlias() {
    $this->resolver->getCurrentContext()->willReturn('one');
    $this->assertEquals('/a', $this->manager->getPathByAlias('/A'));
    $this->assertEquals('/A', $this->manager->getAliasByPath('/a'));
  }

  /**
   * Test contextual aliases within a different global context.
   */
  public function testContextDifferentMatchingAlias() {
    $this->resolver->getCurrentContext()->willReturn('two');
    $this->assertEquals('/b', $this->manager->getPathByAlias('/A'));
    $this->assertEquals('/A', $this->manager->getAliasByPath('/a'));
  }

  /**
   * Test contextual aliases within a different global context.
   */
  public function testContextNotMatchingAlias() {
    $this->resolver->getCurrentContext()->willReturn('three');
    $this->assertEquals('/c', $this->manager->getPathByAlias('/C'));
    $this->assertEquals('/D', $this->manager->getAliasByPath('/d-no-context'));
  }

  /**
   * Test simple aliases within a defined global context.
   */
  public function testContextSimpleAlias() {
    $this->resolver->getCurrentContext()->willReturn('one');
    $this->assertEquals('/c-context-one', $this->manager->getPathByAlias('/C'));
    $this->assertEquals('/C', $this->manager->getAliasByPath('/c-context-one'));
  }

  /**
   * Test aliases that contain another context's prefix.
   */
  public function testNonContextualConflictingAlias() {
    $this->resolver->getCurrentContext()->willReturn(NULL);
    $this->assertEquals('/d', $this->manager->getPathByAlias('/one/D'));
    $this->assertEquals('/one/D', $this->manager->getAliasByPath('/d'));

    $this->resolver->getCurrentContext()->willReturn('one');
    $this->assertEquals('/d', $this->manager->getPathByAlias('/one/D'));
    $this->assertEquals('/one/D', $this->manager->getAliasByPath('/d'));
  }

  /**
   * Test contextual aliases that contain another context's prefix.
   */
  public function testContextualConflictingAlias() {
    $this->resolver->getCurrentContext()->willReturn(NULL);
    $this->assertEquals('/one/E', $this->manager->getPathByAlias('/one/E'));

    $this->resolver->getCurrentContext()->willReturn('one');
    $this->assertEquals('/one/E', $this->manager->getPathByAlias('/one/E'));

    $this->resolver->getCurrentContext()->willReturn('two');
    $this->assertEquals('/one/E', $this->manager->getAliasByPath('/e'));
  }

  /**
   * Test contextual aliases that contain another context's prefix.
   */
  public function testCreationWithoutExplicitContext() {
    $this->resolver->getCurrentContext()->willReturn('one');
    $this->resolver->resolveContext('/implicit-context-one')->willReturn('one');
    $alias = $this->createPathAlias('/implicit-context-one', '/IMPLICIT-CONTEXT-ONE  ', 'en');
    $this->assertEquals('one', $alias->get('context')->value);

    $alias = $this->createPathAlias('/b', '/new-alias  ', 'en');
    // The current context is one, but the source context for /b is two so that
    // should take precedence.
    $this->assertEquals('two', $alias->get('context')->value);
  }

  /**
   * Test contextual aliases that contain another context's prefix.
   */
  public function testEntityQueryWithinContext() {
    $this->resolver->getCurrentContext()->willReturn(NULL);
    $result = $this->aliasStorage->getQuery()
      ->condition('path', '/b', '=')
      ->execute();
    $this->assertCount(2, $result);

    $this->resolver->getCurrentContext()->willReturn('two');
    $result = $this->aliasStorage->getQuery()
      ->condition('path', '/b', '=')
      ->execute();

    $this->assertCount(2, $result);
    $entities = $this->aliasStorage->loadMultiple($result);
    $aliases = [(array_shift($entities))->getAlias(), (array_shift($entities))->getAlias()];
    $this->assertArraySubset(['/A', '/B'], $aliases);

    $this->resolver->getCurrentContext()->willReturn(NULL);
    $result = $this->aliasStorage->getQuery()
      ->condition('alias', '/A', '=')
      ->execute();
    $this->assertCount(0, $result);

    $this->resolver->getCurrentContext()->willReturn('two');
    $result = $this->aliasStorage->getQuery()
      ->condition('alias', '/A', '=')
      ->execute();
    $this->assertCount(1, $result);
    /** @var \Drupal\path_alias\Entity\PathAlias $entity */
    $entity = $this->aliasStorage->load(array_shift($result));
    $this->assertEquals('/b', $entity->getPath());

    $this->resolver->getCurrentContext()->willReturn('one');
    $result = $this->aliasStorage->getQuery()
      ->condition('alias', '/A', '=')
      ->execute();
    $this->assertCount(1, $result);
    /** @var \Drupal\path_alias\Entity\PathAlias $entity */
    $entity = $this->aliasStorage->load(array_shift($result));
    $this->assertEquals('/a', $entity->getPath());
  }

  /**
   * Test contextual aliases that contain another context's prefix.
   */
  public function testUnaffectedEntityQuery() {
    /** @var \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager */
    $entityTypeManager = $this->container->get('entity_type.manager');
    $entityTestStorage = $entityTypeManager->getStorage('entity_test');

    $this->resolver->getCurrentContext()->willReturn(NULL);
    $entityTestStorage->getQuery()->execute();

    $this->resolver->getCurrentContext()->willReturn('two');
    $entityTestStorage->getQuery()->execute();
  }

  /**
   * Test contextual aliases that contain another context's prefix.
   */
  public function testUniquifier() {
    /** @var \Drupal\Core\Extension\ModuleExtensionList $moduleExtensionList */
    $moduleExtensionList = $this->container->get('extension.list.module');
    try {
      $moduleExtensionList->get('pathauto');
      $pathautoExists = TRUE;
    } catch (\Exception $ex) {
      $pathautoExists = FALSE;
    }
    if ($pathautoExists) {
      $this->enableModules(['pathauto', 'token', 'ctools']);
      /** @var \Drupal\contextual_aliases\ContextualAliasUniquifier $uniquifier */
      $uniquifier = $this->container->get('pathauto.alias_uniquifier');
      $this->resolver->resolveContext('/a')->willReturn('one');
      $this->resolver->resolveContext('/b')->willReturn('two');
      $this->resolver->resolveContext('/c')->willReturn(NULL);
      $this->resolver->resolveContext('/f')->willReturn('one');
      $this->resolver->resolveContext('/m')->willReturn(NULL);

      $freeAlias = '/Z';
      $uniquifier->uniquify($freeAlias, '/a', 'en');
      $this->assertEquals('/Z', $freeAlias);

      $existingAlias = '/A';
      $uniquifier->uniquify($existingAlias, '/a', 'en');
      // Existing aliases shouldn't be altered.
      $this->assertEquals( '/A', $existingAlias);

      $reservedAlias = '/A';
      $uniquifier->uniquify($reservedAlias, '/f', 'en');
      // Without setting the pathauto settings the uniquified alias ends up
      // as a '0'.
      $this->assertEquals('0', $reservedAlias);

      $this->resolver->getCurrentContext()->willReturn(NULL);
      $noContextAliasExisting = '/C';
      $uniquifier->uniquify($noContextAliasExisting, '/c', 'en');
      $this->assertEquals( '/C', $noContextAliasExisting);

      $noContextAliasConflicting = '/C';
      $uniquifier->uniquify($noContextAliasConflicting, '/m', 'en');
      $this->assertEquals( '0', $noContextAliasConflicting);
    }
  }

  /**
   * Tests the entity hooks.
   */
  public function testPathAliasCreateHook() {
    $this->resolver->getCurrentContext()->willReturn(NULL);
    $this->resolver->resolveContext('/h')->willReturn(NULL);
    $alias1 = $this->createPathAlias('/h', '/H', 'en');
    $this->assertEquals(NULL, $alias1->get('context')->value);

    $this->resolver->getCurrentContext()->willReturn(NULL);
    $this->resolver->resolveContext('/i')->willReturn('context1');
    $alias2 = $this->createPathAlias('/i', '/I', 'en');
    $this->assertEquals('context1', $alias2->get('context')->value);

    $this->resolver->getCurrentContext()->willReturn('context1');
    $this->resolver->resolveContext('/j')->willReturn(NULL);
    $alias2 = $this->createPathAlias('/j', '/J', 'en');
    $this->assertEquals('context1', $alias2->get('context')->value);
  }

  /**
   * Tests the context resolution
   */
  public function testLookupBySystemPath() {
    /** @var \Drupal\contextual_aliases\ContextualAliasesRepository $aliasRepository */
    $aliasRepository = \Drupal::service('path_alias.repository');
    $aliasManager = \Drupal::service('path_alias.manager');
    $routeProvider = \Drupal::service('router.route_provider');

    $this->resolver->resolveContext('/g')->willReturn(NULL);
    $alias1 = $this->createPathAlias('/g', '/G1', 'en');
    $alias2 = $this->createPathAlias('/g', '/G2', 'en', 'context1');
    $alias3 = $this->createPathAlias('/g', '/G3', 'en', 'context2');

    $this->resolver->resolveContext('/g')->willReturn(NULL);
    $alias = $aliasRepository->lookupBySystemPath('/g', 'en');
    // Since there is no context provided, loaded alias should be $alias1.
    $this->assertEquals('/G1', $alias['alias']);

    $this->resolver->resolveContext('/g')->willReturn('context1');
    // Since 'context1' was provided explicitly, loaded alias should
    // be $alias2.
    $alias = $aliasRepository->lookupBySystemPath('/g', 'en');
    $this->assertEquals('/G2', $alias['alias']);

    $this->resolver->resolveContext('/g')->willReturn('context2');
    // Since 'context1' was provided explicitly, loaded alias should
    // be $alias2.
    $alias = $aliasRepository->lookupBySystemPath('/g', 'en');
    $this->assertEquals('/G3', $alias['alias']);
  }

  /**
   * Test the entity queries looking up aliases by the system path.
   */
  public function testSourceContextOverrideInQuery() {
    $this->resolver->getCurrentContext()->willReturn(NULL);
    $result = $this->aliasStorage->getQuery()
      ->condition('path', '/a', '=')
      ->execute();
    $this->assertCount(1, $result);

    $nestedQuery = $this->aliasStorage->getQuery();
    $orConditionGroup = $nestedQuery->orConditionGroup();
    $orConditionGroup->condition('path', '/a', '=');
    $orConditionGroup->condition('alias', '/xyz', '=');
    $nestedQuery->condition($orConditionGroup);
    $nestedResult = $nestedQuery->execute();
    $this->assertCount(1, $nestedResult);
  }

  /**
   * Test `loadByProperty` with the path condition.
   *
   * This case results in a 'IN' condition that has to be treated separately
   * when processing context conditions.
   */
  public function testLoadByPathProperty() {
    $this->resolver->getCurrentContext()->willReturn('two');
    $aliases = $this->aliasStorage->loadByProperties(['path' => '/a']);
    $this->assertCount(1, $aliases);
  }

}
