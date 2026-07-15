<?php declare(strict_types = 1);

return [
	'lastFullAnalysisTime' => 1784107792,
	'meta' => array (
  'cacheVersion' => 'v13-packageDependencies',
  'phpstanVersion' => '2.2.5',
  'fnsr' => false,
  'metaExtensions' => 
  array (
  ),
  'phpVersion' => 80423,
  'projectConfig' => '{conditionalTags: {Larastan\\Larastan\\Rules\\NoEnvCallsOutsideOfConfigRule: {phpstan.rules.rule: %noEnvCallsOutsideOfConfig%}, Larastan\\Larastan\\Rules\\NoModelMakeRule: {phpstan.rules.rule: %noModelMake%}, Larastan\\Larastan\\Rules\\NoUnnecessaryCollectionCallRule: {phpstan.rules.rule: %noUnnecessaryCollectionCall%}, Larastan\\Larastan\\Rules\\NoUnnecessaryEnumerableToArrayCallsRule: {phpstan.rules.rule: %noUnnecessaryEnumerableToArrayCalls%}, Larastan\\Larastan\\Rules\\OctaneCompatibilityRule: {phpstan.rules.rule: %checkOctaneCompatibility%}, Larastan\\Larastan\\Rules\\UnusedViewsRule: {phpstan.rules.rule: %checkUnusedViews%}, Larastan\\Larastan\\Rules\\NoMissingTranslationsRule: {phpstan.rules.rule: %checkMissingTranslations%}, Larastan\\Larastan\\Rules\\ModelAppendsRule: {phpstan.rules.rule: %checkModelAppends%}, Larastan\\Larastan\\Rules\\NoPublicModelScopeAndAccessorRule: {phpstan.rules.rule: %checkModelMethodVisibility%}, Larastan\\Larastan\\Rules\\NoAuthFacadeInRequestScopeRule: {phpstan.rules.rule: %checkAuthCallsWhenInRequestScope%}, Larastan\\Larastan\\Rules\\NoAuthHelperInRequestScopeRule: {phpstan.rules.rule: %checkAuthCallsWhenInRequestScope%}, Larastan\\Larastan\\ReturnTypes\\Helpers\\EnvFunctionDynamicFunctionReturnTypeExtension: {phpstan.broker.dynamicFunctionReturnTypeExtension: %generalizeEnvReturnType%}, Larastan\\Larastan\\ReturnTypes\\Helpers\\ConfigFunctionDynamicFunctionReturnTypeExtension: {phpstan.broker.dynamicFunctionReturnTypeExtension: %checkConfigTypes%}, Larastan\\Larastan\\ReturnTypes\\ConfigRepositoryDynamicMethodReturnTypeExtension: {phpstan.broker.dynamicMethodReturnTypeExtension: %checkConfigTypes%}, Larastan\\Larastan\\ReturnTypes\\ConfigFacadeCollectionDynamicStaticMethodReturnTypeExtension: {phpstan.broker.dynamicStaticMethodReturnTypeExtension: %checkConfigTypes%}, Larastan\\Larastan\\Rules\\ConfigCollectionRule: {phpstan.rules.rule: %checkConfigTypes%}}, parameters: {universalObjectCratesClasses: [Illuminate\\Http\\Request, Illuminate\\Support\\Optional], earlyTerminatingFunctionCalls: [abort, dd], mixinExcludeClasses: [Eloquent], bootstrapFiles: [bootstrap.php], checkOctaneCompatibility: false, noEnvCallsOutsideOfConfig: true, noModelMake: true, noUnnecessaryCollectionCall: true, noUnnecessaryCollectionCallOnly: [], noUnnecessaryCollectionCallExcept: [], noUnnecessaryEnumerableToArrayCalls: false, squashedMigrationsPath: [], databaseMigrationsPath: [], disableMigrationScan: false, disableSchemaScan: false, configDirectories: [], viewDirectories: [], translationDirectories: [], checkModelProperties: false, checkUnusedViews: false, checkMissingTranslations: false, checkModelAppends: true, checkModelMethodVisibility: false, generalizeEnvReturnType: false, checkConfigTypes: false, checkAuthCallsWhenInRequestScope: false, parseModelCastsMethod: true, enableMigrationCache: false, paths: [/var/www/html/app], level: 5, tmpDir: /var/www/html/storage/phpstan}, rules: [Larastan\\Larastan\\Rules\\UselessConstructs\\NoUselessWithFunctionCallsRule, Larastan\\Larastan\\Rules\\UselessConstructs\\NoUselessValueFunctionCallsRule, Larastan\\Larastan\\Rules\\DeferrableServiceProviderMissingProvidesRule, Larastan\\Larastan\\Rules\\ConsoleCommand\\UndefinedArgumentOrOptionRule], services: {{class: Larastan\\Larastan\\Methods\\RelationForwardsCallsExtension, tags: [phpstan.broker.methodsClassReflectionExtension]}, {class: Larastan\\Larastan\\Methods\\ModelForwardsCallsExtension, tags: [phpstan.broker.methodsClassReflectionExtension]}, {class: Larastan\\Larastan\\Methods\\EloquentBuilderForwardsCallsExtension, tags: [phpstan.broker.methodsClassReflectionExtension]}, {class: Larastan\\Larastan\\Methods\\HigherOrderTapProxyExtension, tags: [phpstan.broker.methodsClassReflectionExtension]}, {class: Larastan\\Larastan\\Methods\\HigherOrderCollectionProxyExtension, tags: [phpstan.broker.methodsClassReflectionExtension]}, {class: Larastan\\Larastan\\Methods\\StorageMethodsClassReflectionExtension, tags: [phpstan.broker.methodsClassReflectionExtension]}, {class: Larastan\\Larastan\\Methods\\ContractsMethodsExtension, tags: [phpstan.broker.methodsClassReflectionExtension]}, {class: Larastan\\Larastan\\Methods\\FacadesMethodsExtension, tags: [phpstan.broker.methodsClassReflectionExtension]}, {class: Larastan\\Larastan\\Methods\\ManagersMethodsExtension, tags: [phpstan.broker.methodsClassReflectionExtension]}, {class: Larastan\\Larastan\\Methods\\AuthsMethodsExtension, tags: [phpstan.broker.methodsClassReflectionExtension]}, {class: Larastan\\Larastan\\Methods\\ModelFactoryMethodsClassReflectionExtension, tags: [phpstan.broker.methodsClassReflectionExtension]}, {class: Larastan\\Larastan\\Methods\\RedirectResponseMethodsClassReflectionExtension, tags: [phpstan.broker.methodsClassReflectionExtension]}, {class: Larastan\\Larastan\\Methods\\MacroMethodsClassReflectionExtension, tags: [phpstan.broker.methodsClassReflectionExtension]}, {class: Larastan\\Larastan\\Methods\\ViewWithMethodsClassReflectionExtension, tags: [phpstan.broker.methodsClassReflectionExtension]}, {class: Larastan\\Larastan\\Properties\\ModelAccessorExtension, tags: [phpstan.broker.propertiesClassReflectionExtension]}, {class: Larastan\\Larastan\\Properties\\ModelPropertyExtension, tags: [phpstan.broker.propertiesClassReflectionExtension]}, {class: Larastan\\Larastan\\Properties\\HigherOrderCollectionProxyPropertyExtension, tags: [phpstan.broker.propertiesClassReflectionExtension]}, {class: Larastan\\Larastan\\ReturnTypes\\HigherOrderTapProxyExtension, tags: [phpstan.broker.dynamicMethodReturnTypeExtension]}, {class: Larastan\\Larastan\\ReturnTypes\\ContainerArrayAccessDynamicMethodReturnTypeExtension, tags: [phpstan.broker.dynamicMethodReturnTypeExtension], arguments: {className: Illuminate\\Contracts\\Container\\Container}}, {class: Larastan\\Larastan\\ReturnTypes\\ContainerArrayAccessDynamicMethodReturnTypeExtension, tags: [phpstan.broker.dynamicMethodReturnTypeExtension], arguments: {className: Illuminate\\Container\\Container}}, {class: Larastan\\Larastan\\ReturnTypes\\ContainerArrayAccessDynamicMethodReturnTypeExtension, tags: [phpstan.broker.dynamicMethodReturnTypeExtension], arguments: {className: Illuminate\\Foundation\\Application}}, {class: Larastan\\Larastan\\ReturnTypes\\ContainerArrayAccessDynamicMethodReturnTypeExtension, tags: [phpstan.broker.dynamicMethodReturnTypeExtension], arguments: {className: Illuminate\\Contracts\\Foundation\\Application}}, {class: Larastan\\Larastan\\Properties\\ModelRelationsExtension, tags: [phpstan.broker.propertiesClassReflectionExtension]}, {class: Larastan\\Larastan\\ReturnTypes\\ModelOnlyDynamicMethodReturnTypeExtension, tags: [phpstan.broker.dynamicMethodReturnTypeExtension]}, {class: Larastan\\Larastan\\ReturnTypes\\ModelFactoryDynamicStaticMethodReturnTypeExtension, tags: [phpstan.broker.dynamicStaticMethodReturnTypeExtension]}, {class: Larastan\\Larastan\\ReturnTypes\\ModelDynamicStaticMethodReturnTypeExtension, tags: [phpstan.broker.dynamicStaticMethodReturnTypeExtension]}, {class: Larastan\\Larastan\\ReturnTypes\\AppMakeDynamicReturnTypeExtension, tags: [phpstan.broker.dynamicStaticMethodReturnTypeExtension]}, {class: Larastan\\Larastan\\ReturnTypes\\AuthExtension, tags: [phpstan.broker.dynamicStaticMethodReturnTypeExtension]}, {class: Larastan\\Larastan\\ReturnTypes\\GuardDynamicStaticMethodReturnTypeExtension, tags: [phpstan.broker.dynamicStaticMethodReturnTypeExtension]}, {class: Larastan\\Larastan\\ReturnTypes\\AuthManagerExtension, tags: [phpstan.broker.dynamicMethodReturnTypeExtension]}, {class: Larastan\\Larastan\\ReturnTypes\\DateExtension, tags: [phpstan.broker.dynamicStaticMethodReturnTypeExtension]}, {class: Larastan\\Larastan\\ReturnTypes\\GuardExtension, tags: [phpstan.broker.dynamicMethodReturnTypeExtension]}, {class: Larastan\\Larastan\\ReturnTypes\\RequestFileExtension, tags: [phpstan.broker.dynamicMethodReturnTypeExtension]}, {class: Larastan\\Larastan\\ReturnTypes\\RequestRouteExtension, tags: [phpstan.broker.dynamicMethodReturnTypeExtension]}, {class: Larastan\\Larastan\\ReturnTypes\\RequestUserExtension, tags: [phpstan.broker.dynamicMethodReturnTypeExtension]}, {class: Larastan\\Larastan\\ReturnTypes\\EloquentBuilderExtension, tags: [phpstan.broker.dynamicMethodReturnTypeExtension]}, {class: Larastan\\Larastan\\ReturnTypes\\RelationCollectionExtension, tags: [phpstan.broker.dynamicMethodReturnTypeExtension]}, {class: Larastan\\Larastan\\ReturnTypes\\TestCaseExtension, tags: [phpstan.broker.dynamicMethodReturnTypeExtension]}, {class: Larastan\\Larastan\\Support\\CollectionHelper}, {class: Larastan\\Larastan\\ReturnTypes\\Helpers\\AuthExtension, tags: [phpstan.broker.dynamicFunctionReturnTypeExtension]}, {class: Larastan\\Larastan\\ReturnTypes\\Helpers\\CollectExtension, tags: [phpstan.broker.dynamicFunctionReturnTypeExtension]}, {class: Larastan\\Larastan\\ReturnTypes\\Helpers\\NowAndTodayExtension, tags: [phpstan.broker.dynamicFunctionReturnTypeExtension]}, {class: Larastan\\Larastan\\ReturnTypes\\Helpers\\ResponseExtension, tags: [phpstan.broker.dynamicFunctionReturnTypeExtension]}, {class: Larastan\\Larastan\\ReturnTypes\\Helpers\\ValidatorExtension, tags: [phpstan.broker.dynamicFunctionReturnTypeExtension]}, {class: Larastan\\Larastan\\ReturnTypes\\Helpers\\LiteralExtension, tags: [phpstan.broker.dynamicFunctionReturnTypeExtension]}, {class: Larastan\\Larastan\\ReturnTypes\\CollectionFilterRejectDynamicReturnTypeExtension, tags: [phpstan.broker.dynamicMethodReturnTypeExtension]}, {class: Larastan\\Larastan\\ReturnTypes\\CollectionWhereNotNullDynamicReturnTypeExtension, tags: [phpstan.broker.dynamicMethodReturnTypeExtension]}, {class: Larastan\\Larastan\\ReturnTypes\\NewModelQueryDynamicMethodReturnTypeExtension, tags: [phpstan.broker.dynamicMethodReturnTypeExtension]}, {class: Larastan\\Larastan\\ReturnTypes\\FactoryDynamicMethodReturnTypeExtension, tags: [phpstan.broker.dynamicMethodReturnTypeExtension]}, {class: Larastan\\Larastan\\Types\\AbortIfFunctionTypeSpecifyingExtension, tags: [phpstan.typeSpecifier.functionTypeSpecifyingExtension], arguments: {methodName: abort, negate: false}}, {class: Larastan\\Larastan\\Types\\AbortIfFunctionTypeSpecifyingExtension, tags: [phpstan.typeSpecifier.functionTypeSpecifyingExtension], arguments: {methodName: abort, negate: true}}, {class: Larastan\\Larastan\\Types\\AbortIfFunctionTypeSpecifyingExtension, tags: [phpstan.typeSpecifier.functionTypeSpecifyingExtension], arguments: {methodName: throw, negate: false}}, {class: Larastan\\Larastan\\Types\\AbortIfFunctionTypeSpecifyingExtension, tags: [phpstan.typeSpecifier.functionTypeSpecifyingExtension], arguments: {methodName: throw, negate: true}}, {class: Larastan\\Larastan\\ReturnTypes\\Helpers\\AppExtension, tags: [phpstan.broker.dynamicFunctionReturnTypeExtension]}, {class: Larastan\\Larastan\\ReturnTypes\\Helpers\\ValueExtension, tags: [phpstan.broker.dynamicFunctionReturnTypeExtension]}, {class: Larastan\\Larastan\\ReturnTypes\\Helpers\\StrExtension, tags: [phpstan.broker.dynamicFunctionReturnTypeExtension]}, {class: Larastan\\Larastan\\ReturnTypes\\Helpers\\TapExtension, tags: [phpstan.broker.dynamicFunctionReturnTypeExtension]}, {class: Larastan\\Larastan\\ReturnTypes\\StorageDynamicStaticMethodReturnTypeExtension, tags: [phpstan.broker.dynamicStaticMethodReturnTypeExtension]}, {class: Larastan\\Larastan\\Types\\GenericEloquentCollectionTypeNodeResolverExtension, tags: [phpstan.phpDoc.typeNodeResolverExtension]}, {class: Larastan\\Larastan\\Types\\ViewStringTypeNodeResolverExtension, tags: [phpstan.phpDoc.typeNodeResolverExtension]}, {class: Larastan\\Larastan\\Rules\\OctaneCompatibilityRule}, {class: Larastan\\Larastan\\Rules\\NoEnvCallsOutsideOfConfigRule, arguments: {configDirectories: %configDirectories%}}, {class: Larastan\\Larastan\\Rules\\NoModelMakeRule}, {class: Larastan\\Larastan\\Rules\\NoUnnecessaryCollectionCallRule, arguments: {onlyMethods: %noUnnecessaryCollectionCallOnly%, excludeMethods: %noUnnecessaryCollectionCallExcept%}}, {class: Larastan\\Larastan\\Rules\\NoUnnecessaryEnumerableToArrayCallsRule}, {class: Larastan\\Larastan\\Rules\\ModelAppendsRule}, {class: Larastan\\Larastan\\Rules\\NoPublicModelScopeAndAccessorRule}, {class: Larastan\\Larastan\\Types\\GenericEloquentBuilderTypeNodeResolverExtension, tags: [phpstan.phpDoc.typeNodeResolverExtension]}, {class: Larastan\\Larastan\\ReturnTypes\\AppEnvironmentReturnTypeExtension, tags: [phpstan.broker.dynamicMethodReturnTypeExtension], arguments: {class: Illuminate\\Foundation\\Application}}, {class: Larastan\\Larastan\\ReturnTypes\\AppEnvironmentReturnTypeExtension, tags: [phpstan.broker.dynamicMethodReturnTypeExtension], arguments: {class: Illuminate\\Contracts\\Foundation\\Application}}, {class: Larastan\\Larastan\\ReturnTypes\\AppFacadeEnvironmentReturnTypeExtension, tags: [phpstan.broker.dynamicStaticMethodReturnTypeExtension]}, {class: Larastan\\Larastan\\Types\\ModelProperty\\ModelPropertyTypeNodeResolverExtension, tags: [phpstan.phpDoc.typeNodeResolverExtension], arguments: {active: %checkModelProperties%}}, {class: Larastan\\Larastan\\Types\\CollectionOf\\CollectionOfTypeNodeResolverExtension, tags: [phpstan.phpDoc.typeNodeResolverExtension]}, {class: Larastan\\Larastan\\Properties\\MigrationHelper, arguments: {databaseMigrationPath: %databaseMigrationsPath%, disableMigrationScan: %disableMigrationScan%, parser: @migrationsParser, reflectionProvider: @reflectionProvider}}, iamcalSqlParser: {class: Larastan\\Larastan\\SQL\\IamcalSqlParser, autowired: false}, sqlParserFactory: {class: Larastan\\Larastan\\SQL\\SqlParserFactory, arguments: {iamcalSqlParser: @iamcalSqlParser}}, sqlParser: {type: Larastan\\Larastan\\SQL\\SqlParser, factory: [@sqlParserFactory, create]}, {class: Larastan\\Larastan\\Properties\\SquashedMigrationHelper, arguments: {schemaPaths: %squashedMigrationsPath%, disableSchemaScan: %disableSchemaScan%}}, {class: Larastan\\Larastan\\Properties\\ModelCastHelper, arguments: {parser: @currentPhpVersionSimpleDirectParser, parseModelCastsMethod: %parseModelCastsMethod%}}, {class: Larastan\\Larastan\\Properties\\MigrationCache, arguments: {cacheDirectory: %tmpDir%, enabled: %enableMigrationCache%}}, {class: Larastan\\Larastan\\Properties\\ModelPropertyHelper}, {class: Larastan\\Larastan\\Rules\\ModelRuleHelper}, {class: Larastan\\Larastan\\Methods\\BuilderHelper, arguments: {checkProperties: %checkModelProperties%}}, {class: Larastan\\Larastan\\Rules\\RelationExistenceRule, tags: [phpstan.rules.rule]}, {class: Larastan\\Larastan\\Rules\\CheckDispatchArgumentTypesCompatibleWithClassConstructorRule, arguments: {dispatchableClass: Illuminate\\Foundation\\Bus\\Dispatchable}, tags: [phpstan.rules.rule]}, {class: Larastan\\Larastan\\Rules\\CheckDispatchArgumentTypesCompatibleWithClassConstructorRule, arguments: {dispatchableClass: Illuminate\\Foundation\\Events\\Dispatchable}, tags: [phpstan.rules.rule]}, {class: Larastan\\Larastan\\Properties\\Schema\\MySqlDataTypeToPhpTypeConverter}, {class: Larastan\\Larastan\\LarastanStubFilesExtension, tags: [phpstan.stubFilesExtension]}, {class: Larastan\\Larastan\\Rules\\UnusedViewsRule}, {class: Larastan\\Larastan\\Collectors\\UsedViewFunctionCollector, tags: [phpstan.collector]}, {class: Larastan\\Larastan\\Collectors\\UsedEmailViewCollector, tags: [phpstan.collector]}, {class: Larastan\\Larastan\\Collectors\\UsedViewMakeCollector, tags: [phpstan.collector]}, {class: Larastan\\Larastan\\Collectors\\UsedViewFacadeMakeCollector, tags: [phpstan.collector]}, {class: Larastan\\Larastan\\Collectors\\UsedRouteFacadeViewCollector, tags: [phpstan.collector]}, {class: Larastan\\Larastan\\Collectors\\UsedViewInAnotherViewCollector}, {class: Larastan\\Larastan\\Support\\ViewFileHelper, arguments: {viewDirectories: %viewDirectories%}}, {class: Larastan\\Larastan\\Support\\ViewParser, arguments: {parser: @currentPhpVersionSimpleDirectParser}}, {class: Larastan\\Larastan\\Rules\\NoMissingTranslationsRule, arguments: {translationDirectories: %translationDirectories%}}, {class: Larastan\\Larastan\\Collectors\\UsedTranslationFunctionCollector, tags: [phpstan.collector]}, {class: Larastan\\Larastan\\Collectors\\UsedTranslationTranslatorCollector, tags: [phpstan.collector]}, {class: Larastan\\Larastan\\Collectors\\UsedTranslationFacadeCollector, tags: [phpstan.collector]}, {class: Larastan\\Larastan\\Collectors\\UsedTranslationViewCollector}, {class: Larastan\\Larastan\\ReturnTypes\\ApplicationMakeDynamicReturnTypeExtension, tags: [phpstan.broker.dynamicMethodReturnTypeExtension]}, {class: Larastan\\Larastan\\ReturnTypes\\ContainerMakeDynamicReturnTypeExtension, tags: [phpstan.broker.dynamicMethodReturnTypeExtension]}, {class: Larastan\\Larastan\\ReturnTypes\\ConsoleCommand\\ArgumentDynamicReturnTypeExtension, tags: [phpstan.broker.dynamicMethodReturnTypeExtension]}, {class: Larastan\\Larastan\\ReturnTypes\\ConsoleCommand\\HasArgumentDynamicReturnTypeExtension, tags: [phpstan.broker.dynamicMethodReturnTypeExtension]}, {class: Larastan\\Larastan\\ReturnTypes\\ConsoleCommand\\OptionDynamicReturnTypeExtension, tags: [phpstan.broker.dynamicMethodReturnTypeExtension]}, {class: Larastan\\Larastan\\ReturnTypes\\ConsoleCommand\\HasOptionDynamicReturnTypeExtension, tags: [phpstan.broker.dynamicMethodReturnTypeExtension]}, {class: Larastan\\Larastan\\ReturnTypes\\TranslatorGetReturnTypeExtension, tags: [phpstan.broker.dynamicMethodReturnTypeExtension]}, {class: Larastan\\Larastan\\ReturnTypes\\LangGetReturnTypeExtension, tags: [phpstan.broker.dynamicStaticMethodReturnTypeExtension]}, {class: Larastan\\Larastan\\ReturnTypes\\TransHelperReturnTypeExtension, tags: [phpstan.broker.dynamicFunctionReturnTypeExtension]}, {class: Larastan\\Larastan\\ReturnTypes\\DoubleUnderscoreHelperReturnTypeExtension, tags: [phpstan.broker.dynamicFunctionReturnTypeExtension]}, {class: Larastan\\Larastan\\ReturnTypes\\AppMakeHelper}, {class: Larastan\\Larastan\\Internal\\ConsoleApplicationResolver}, {class: Larastan\\Larastan\\Internal\\ConsoleApplicationHelper}, {class: Larastan\\Larastan\\Support\\HigherOrderCollectionProxyHelper}, {class: Larastan\\Larastan\\ReturnTypes\\Helpers\\ConfigFunctionDynamicFunctionReturnTypeExtension}, {class: Larastan\\Larastan\\ReturnTypes\\ConfigRepositoryDynamicMethodReturnTypeExtension}, {class: Larastan\\Larastan\\ReturnTypes\\ConfigFacadeCollectionDynamicStaticMethodReturnTypeExtension}, {class: Larastan\\Larastan\\Support\\ConfigParser, arguments: {parser: @currentPhpVersionSimpleDirectParser, configPaths: %configDirectories%, treatPhpDocTypesAsCertain: %treatPhpDocTypesAsCertain%}}, {class: Larastan\\Larastan\\Internal\\ConfigHelper}, {class: Larastan\\Larastan\\ReturnTypes\\Helpers\\EnvFunctionDynamicFunctionReturnTypeExtension}, {class: Larastan\\Larastan\\ReturnTypes\\FormRequestSafeDynamicMethodReturnTypeExtension, tags: [phpstan.broker.dynamicMethodReturnTypeExtension]}, {class: Larastan\\Larastan\\ReturnTypes\\EloquentCollectionMapDynamicReturnTypeExtension, tags: [phpstan.broker.dynamicMethodReturnTypeExtension]}, {class: Larastan\\Larastan\\Rules\\NoAuthFacadeInRequestScopeRule}, {class: Larastan\\Larastan\\Rules\\NoAuthHelperInRequestScopeRule}, {class: Larastan\\Larastan\\Rules\\ConfigCollectionRule}, {class: Illuminate\\Filesystem\\Filesystem, autowired: self}, migrationsParser: {class: PHPStan\\Parser\\CachedParser, arguments: {originalParser: @currentPhpVersionSimpleDirectParser, cachedNodesByStringCountMax: %cache.nodesByStringCountMax%}, autowired: false}}}',
  'analysedPaths' => 
  array (
    0 => '/var/www/html/app',
  ),
  'scannedFiles' => 
  array (
  ),
  'composerLocks' => 
  array (
    '/var/www/html/composer.lock' => 'aeb258a37446917fed010199c1bf2fb4b00df9242ff5bdebdbb39ee56b33f09d',
  ),
  'composerInstalled' => 
  array (
    '/var/www/html/vendor/composer/installed.php' => 
    array (
      'versions' => 
      array (
        'brick/math' => 
        array (
          'pretty_version' => '0.14.8',
          'version' => '0.14.8.0',
          'reference' => '63422359a44b7f06cae63c3b429b59e8efcc0629',
          'type' => 'library',
          'install_path' => '/var/www/html/vendor/composer/../brick/math',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'carbonphp/carbon-doctrine-types' => 
        array (
          'pretty_version' => '3.2.0',
          'version' => '3.2.0.0',
          'reference' => '18ba5ddfec8976260ead6e866180bd5d2f71aa1d',
          'type' => 'library',
          'install_path' => '/var/www/html/vendor/composer/../carbonphp/carbon-doctrine-types',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'cordoval/hamcrest-php' => 
        array (
          'dev_requirement' => true,
          'replaced' => 
          array (
            0 => '*',
          ),
        ),
        'davedevelopment/hamcrest-php' => 
        array (
          'dev_requirement' => true,
          'replaced' => 
          array (
            0 => '*',
          ),
        ),
        'dflydev/dot-access-data' => 
        array (
          'pretty_version' => 'v3.0.3',
          'version' => '3.0.3.0',
          'reference' => 'a23a2bf4f31d3518f3ecb38660c95715dfead60f',
          'type' => 'library',
          'install_path' => '/var/www/html/vendor/composer/../dflydev/dot-access-data',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'doctrine/inflector' => 
        array (
          'pretty_version' => '2.1.0',
          'version' => '2.1.0.0',
          'reference' => '6d6c96277ea252fc1304627204c3d5e6e15faa3b',
          'type' => 'library',
          'install_path' => '/var/www/html/vendor/composer/../doctrine/inflector',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'doctrine/lexer' => 
        array (
          'pretty_version' => '3.0.1',
          'version' => '3.0.1.0',
          'reference' => '31ad66abc0fc9e1a1f2d9bc6a42668d2fbbcd6dd',
          'type' => 'library',
          'install_path' => '/var/www/html/vendor/composer/../doctrine/lexer',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'dragonmantank/cron-expression' => 
        array (
          'pretty_version' => 'v3.6.0',
          'version' => '3.6.0.0',
          'reference' => 'd61a8a9604ec1f8c3d150d09db6ce98b32675013',
          'type' => 'library',
          'install_path' => '/var/www/html/vendor/composer/../dragonmantank/cron-expression',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'egulias/email-validator' => 
        array (
          'pretty_version' => '4.0.4',
          'version' => '4.0.4.0',
          'reference' => 'd42c8731f0624ad6bdc8d3e5e9a4524f68801cfa',
          'type' => 'library',
          'install_path' => '/var/www/html/vendor/composer/../egulias/email-validator',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'fakerphp/faker' => 
        array (
          'pretty_version' => 'v1.24.1',
          'version' => '1.24.1.0',
          'reference' => 'e0ee18eb1e6dc3cda3ce9fd97e5a0689a88a64b5',
          'type' => 'library',
          'install_path' => '/var/www/html/vendor/composer/../fakerphp/faker',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'filp/whoops' => 
        array (
          'pretty_version' => '2.18.4',
          'version' => '2.18.4.0',
          'reference' => 'd2102955e48b9fd9ab24280a7ad12ed552752c4d',
          'type' => 'library',
          'install_path' => '/var/www/html/vendor/composer/../filp/whoops',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'fruitcake/php-cors' => 
        array (
          'pretty_version' => 'v1.4.0',
          'version' => '1.4.0.0',
          'reference' => '38aaa6c3fd4c157ffe2a4d10aa8b9b16ba8de379',
          'type' => 'library',
          'install_path' => '/var/www/html/vendor/composer/../fruitcake/php-cors',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'graham-campbell/result-type' => 
        array (
          'pretty_version' => 'v1.1.4',
          'version' => '1.1.4.0',
          'reference' => 'e01f4a821471308ba86aa202fed6698b6b695e3b',
          'type' => 'library',
          'install_path' => '/var/www/html/vendor/composer/../graham-campbell/result-type',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'guzzlehttp/guzzle' => 
        array (
          'pretty_version' => '7.14.0',
          'version' => '7.14.0.0',
          'reference' => 'aef242412e13128b5049864867bb49fc37dd39de',
          'type' => 'library',
          'install_path' => '/var/www/html/vendor/composer/../guzzlehttp/guzzle',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'guzzlehttp/promises' => 
        array (
          'pretty_version' => '2.5.1',
          'version' => '2.5.1.0',
          'reference' => '9ad1e4fc607446a055b95870c7f668e93b5cff29',
          'type' => 'library',
          'install_path' => '/var/www/html/vendor/composer/../guzzlehttp/promises',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'guzzlehttp/psr7' => 
        array (
          'pretty_version' => '2.12.4',
          'version' => '2.12.4.0',
          'reference' => '51e27f9e2b332ab3e72f4520d5ff4f3c68c3577c',
          'type' => 'library',
          'install_path' => '/var/www/html/vendor/composer/../guzzlehttp/psr7',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'guzzlehttp/uri-template' => 
        array (
          'pretty_version' => 'v1.0.9',
          'version' => '1.0.9.0',
          'reference' => 'd7580af6d3f8384325d9cd3e99b21c3ed1848176',
          'type' => 'library',
          'install_path' => '/var/www/html/vendor/composer/../guzzlehttp/uri-template',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'hamcrest/hamcrest-php' => 
        array (
          'pretty_version' => 'v2.1.1',
          'version' => '2.1.1.0',
          'reference' => 'f8b1c0173b22fa6ec77a81fe63e5b01eba7e6487',
          'type' => 'library',
          'install_path' => '/var/www/html/vendor/composer/../hamcrest/hamcrest-php',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'iamcal/sql-parser' => 
        array (
          'pretty_version' => 'v0.7',
          'version' => '0.7.0.0',
          'reference' => '610392f38de49a44dab08dc1659960a29874c4b8',
          'type' => 'library',
          'install_path' => '/var/www/html/vendor/composer/../iamcal/sql-parser',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'illuminate/auth' => 
        array (
          'dev_requirement' => false,
          'replaced' => 
          array (
            0 => 'v12.63.0',
          ),
        ),
        'illuminate/broadcasting' => 
        array (
          'dev_requirement' => false,
          'replaced' => 
          array (
            0 => 'v12.63.0',
          ),
        ),
        'illuminate/bus' => 
        array (
          'dev_requirement' => false,
          'replaced' => 
          array (
            0 => 'v12.63.0',
          ),
        ),
        'illuminate/cache' => 
        array (
          'dev_requirement' => false,
          'replaced' => 
          array (
            0 => 'v12.63.0',
          ),
        ),
        'illuminate/collections' => 
        array (
          'dev_requirement' => false,
          'replaced' => 
          array (
            0 => 'v12.63.0',
          ),
        ),
        'illuminate/concurrency' => 
        array (
          'dev_requirement' => false,
          'replaced' => 
          array (
            0 => 'v12.63.0',
          ),
        ),
        'illuminate/conditionable' => 
        array (
          'dev_requirement' => false,
          'replaced' => 
          array (
            0 => 'v12.63.0',
          ),
        ),
        'illuminate/config' => 
        array (
          'dev_requirement' => false,
          'replaced' => 
          array (
            0 => 'v12.63.0',
          ),
        ),
        'illuminate/console' => 
        array (
          'dev_requirement' => false,
          'replaced' => 
          array (
            0 => 'v12.63.0',
          ),
        ),
        'illuminate/container' => 
        array (
          'dev_requirement' => false,
          'replaced' => 
          array (
            0 => 'v12.63.0',
          ),
        ),
        'illuminate/contracts' => 
        array (
          'dev_requirement' => false,
          'replaced' => 
          array (
            0 => 'v12.63.0',
          ),
        ),
        'illuminate/cookie' => 
        array (
          'dev_requirement' => false,
          'replaced' => 
          array (
            0 => 'v12.63.0',
          ),
        ),
        'illuminate/database' => 
        array (
          'dev_requirement' => false,
          'replaced' => 
          array (
            0 => 'v12.63.0',
          ),
        ),
        'illuminate/encryption' => 
        array (
          'dev_requirement' => false,
          'replaced' => 
          array (
            0 => 'v12.63.0',
          ),
        ),
        'illuminate/events' => 
        array (
          'dev_requirement' => false,
          'replaced' => 
          array (
            0 => 'v12.63.0',
          ),
        ),
        'illuminate/filesystem' => 
        array (
          'dev_requirement' => false,
          'replaced' => 
          array (
            0 => 'v12.63.0',
          ),
        ),
        'illuminate/hashing' => 
        array (
          'dev_requirement' => false,
          'replaced' => 
          array (
            0 => 'v12.63.0',
          ),
        ),
        'illuminate/http' => 
        array (
          'dev_requirement' => false,
          'replaced' => 
          array (
            0 => 'v12.63.0',
          ),
        ),
        'illuminate/json-schema' => 
        array (
          'dev_requirement' => false,
          'replaced' => 
          array (
            0 => 'v12.63.0',
          ),
        ),
        'illuminate/log' => 
        array (
          'dev_requirement' => false,
          'replaced' => 
          array (
            0 => 'v12.63.0',
          ),
        ),
        'illuminate/macroable' => 
        array (
          'dev_requirement' => false,
          'replaced' => 
          array (
            0 => 'v12.63.0',
          ),
        ),
        'illuminate/mail' => 
        array (
          'dev_requirement' => false,
          'replaced' => 
          array (
            0 => 'v12.63.0',
          ),
        ),
        'illuminate/notifications' => 
        array (
          'dev_requirement' => false,
          'replaced' => 
          array (
            0 => 'v12.63.0',
          ),
        ),
        'illuminate/pagination' => 
        array (
          'dev_requirement' => false,
          'replaced' => 
          array (
            0 => 'v12.63.0',
          ),
        ),
        'illuminate/pipeline' => 
        array (
          'dev_requirement' => false,
          'replaced' => 
          array (
            0 => 'v12.63.0',
          ),
        ),
        'illuminate/process' => 
        array (
          'dev_requirement' => false,
          'replaced' => 
          array (
            0 => 'v12.63.0',
          ),
        ),
        'illuminate/queue' => 
        array (
          'dev_requirement' => false,
          'replaced' => 
          array (
            0 => 'v12.63.0',
          ),
        ),
        'illuminate/redis' => 
        array (
          'dev_requirement' => false,
          'replaced' => 
          array (
            0 => 'v12.63.0',
          ),
        ),
        'illuminate/reflection' => 
        array (
          'dev_requirement' => false,
          'replaced' => 
          array (
            0 => 'v12.63.0',
          ),
        ),
        'illuminate/routing' => 
        array (
          'dev_requirement' => false,
          'replaced' => 
          array (
            0 => 'v12.63.0',
          ),
        ),
        'illuminate/session' => 
        array (
          'dev_requirement' => false,
          'replaced' => 
          array (
            0 => 'v12.63.0',
          ),
        ),
        'illuminate/support' => 
        array (
          'dev_requirement' => false,
          'replaced' => 
          array (
            0 => 'v12.63.0',
          ),
        ),
        'illuminate/testing' => 
        array (
          'dev_requirement' => false,
          'replaced' => 
          array (
            0 => 'v12.63.0',
          ),
        ),
        'illuminate/translation' => 
        array (
          'dev_requirement' => false,
          'replaced' => 
          array (
            0 => 'v12.63.0',
          ),
        ),
        'illuminate/validation' => 
        array (
          'dev_requirement' => false,
          'replaced' => 
          array (
            0 => 'v12.63.0',
          ),
        ),
        'illuminate/view' => 
        array (
          'dev_requirement' => false,
          'replaced' => 
          array (
            0 => 'v12.63.0',
          ),
        ),
        'kodova/hamcrest-php' => 
        array (
          'dev_requirement' => true,
          'replaced' => 
          array (
            0 => '*',
          ),
        ),
        'larastan/larastan' => 
        array (
          'pretty_version' => 'v3.10.0',
          'version' => '3.10.0.0',
          'reference' => '2970f83398154178a739609c244577267c7ee8eb',
          'type' => 'phpstan-extension',
          'install_path' => '/var/www/html/vendor/composer/../larastan/larastan',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'laravel/framework' => 
        array (
          'pretty_version' => 'v12.63.0',
          'version' => '12.63.0.0',
          'reference' => '7adfddbf4738f2e6cae5419b0e6bc46d4cccfbcf',
          'type' => 'library',
          'install_path' => '/var/www/html/vendor/composer/../laravel/framework',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'laravel/pail' => 
        array (
          'pretty_version' => 'v1.2.7',
          'version' => '1.2.7.0',
          'reference' => '2f7d27dada8effc48b8c424445a69cca7007daaa',
          'type' => 'library',
          'install_path' => '/var/www/html/vendor/composer/../laravel/pail',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'laravel/pint' => 
        array (
          'pretty_version' => 'v1.29.3',
          'version' => '1.29.3.0',
          'reference' => 'da1d1111a6aa2e082d2a388b194afe1ba0a05d14',
          'type' => 'project',
          'install_path' => '/var/www/html/vendor/composer/../laravel/pint',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'laravel/prompts' => 
        array (
          'pretty_version' => 'v0.3.21',
          'version' => '0.3.21.0',
          'reference' => '7753c65c281c2550c7c183f14e18062073b7d821',
          'type' => 'library',
          'install_path' => '/var/www/html/vendor/composer/../laravel/prompts',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'laravel/sail' => 
        array (
          'pretty_version' => 'v1.63.0',
          'version' => '1.63.0.0',
          'reference' => '51bbce3f803c1d386cabbb44e618c955a12ff5fc',
          'type' => 'library',
          'install_path' => '/var/www/html/vendor/composer/../laravel/sail',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'laravel/sanctum' => 
        array (
          'pretty_version' => 'v4.3.2',
          'version' => '4.3.2.0',
          'reference' => '2a9bccc18e9907808e0018dd15fa643937886b1e',
          'type' => 'library',
          'install_path' => '/var/www/html/vendor/composer/../laravel/sanctum',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'laravel/serializable-closure' => 
        array (
          'pretty_version' => 'v2.0.13',
          'version' => '2.0.13.0',
          'reference' => 'b566ee0dd251f3c4078bed003a7ce015f5ea6dce',
          'type' => 'library',
          'install_path' => '/var/www/html/vendor/composer/../laravel/serializable-closure',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'laravel/tinker' => 
        array (
          'pretty_version' => 'v2.11.1',
          'version' => '2.11.1.0',
          'reference' => 'c9f80cc835649b5c1842898fb043f8cc098dd741',
          'type' => 'library',
          'install_path' => '/var/www/html/vendor/composer/../laravel/tinker',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'league/commonmark' => 
        array (
          'pretty_version' => '2.8.2',
          'version' => '2.8.2.0',
          'reference' => '59fb075d2101740c337c7216e3f32b36c204218b',
          'type' => 'library',
          'install_path' => '/var/www/html/vendor/composer/../league/commonmark',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'league/config' => 
        array (
          'pretty_version' => 'v1.2.0',
          'version' => '1.2.0.0',
          'reference' => '754b3604fb2984c71f4af4a9cbe7b57f346ec1f3',
          'type' => 'library',
          'install_path' => '/var/www/html/vendor/composer/../league/config',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'league/flysystem' => 
        array (
          'pretty_version' => '3.35.2',
          'version' => '3.35.2.0',
          'reference' => 'b277b5dc3d56650b68904117124e79c851e12376',
          'type' => 'library',
          'install_path' => '/var/www/html/vendor/composer/../league/flysystem',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'league/flysystem-local' => 
        array (
          'pretty_version' => '3.31.0',
          'version' => '3.31.0.0',
          'reference' => '2f669db18a4c20c755c2bb7d3a7b0b2340488079',
          'type' => 'library',
          'install_path' => '/var/www/html/vendor/composer/../league/flysystem-local',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'league/mime-type-detection' => 
        array (
          'pretty_version' => '1.17.0',
          'version' => '1.17.0.0',
          'reference' => 'f5f47eff7c48ed1003069a2ca67f316fb4021c76',
          'type' => 'library',
          'install_path' => '/var/www/html/vendor/composer/../league/mime-type-detection',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'league/uri' => 
        array (
          'pretty_version' => '7.8.1',
          'version' => '7.8.1.0',
          'reference' => '08cf38e3924d4f56238125547b5720496fac8fd4',
          'type' => 'library',
          'install_path' => '/var/www/html/vendor/composer/../league/uri',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'league/uri-interfaces' => 
        array (
          'pretty_version' => '7.8.1',
          'version' => '7.8.1.0',
          'reference' => '85d5c77c5d6d3af6c54db4a78246364908f3c928',
          'type' => 'library',
          'install_path' => '/var/www/html/vendor/composer/../league/uri-interfaces',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'mockery/mockery' => 
        array (
          'pretty_version' => '1.6.12',
          'version' => '1.6.12.0',
          'reference' => '1f4efdd7d3beafe9807b08156dfcb176d18f1699',
          'type' => 'library',
          'install_path' => '/var/www/html/vendor/composer/../mockery/mockery',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'monolog/monolog' => 
        array (
          'pretty_version' => '3.10.0',
          'version' => '3.10.0.0',
          'reference' => 'b321dd6749f0bf7189444158a3ce785cc16d69b0',
          'type' => 'library',
          'install_path' => '/var/www/html/vendor/composer/../monolog/monolog',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'mtdowling/cron-expression' => 
        array (
          'dev_requirement' => false,
          'replaced' => 
          array (
            0 => '^1.0',
          ),
        ),
        'myclabs/deep-copy' => 
        array (
          'pretty_version' => '1.13.4',
          'version' => '1.13.4.0',
          'reference' => '07d290f0c47959fd5eed98c95ee5602db07e0b6a',
          'type' => 'library',
          'install_path' => '/var/www/html/vendor/composer/../myclabs/deep-copy',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'nesbot/carbon' => 
        array (
          'pretty_version' => '3.13.1',
          'version' => '3.13.1.0',
          'reference' => '2937ad3d1d2c506fd2bc97d571438a95641f44e2',
          'type' => 'library',
          'install_path' => '/var/www/html/vendor/composer/../nesbot/carbon',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'nette/schema' => 
        array (
          'pretty_version' => 'v1.3.5',
          'version' => '1.3.5.0',
          'reference' => 'f0ab1a3cda782dbc5da270d28545236aa80c4002',
          'type' => 'library',
          'install_path' => '/var/www/html/vendor/composer/../nette/schema',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'nette/utils' => 
        array (
          'pretty_version' => 'v4.1.4',
          'version' => '4.1.4.0',
          'reference' => '7da6c396d7ebe142bc857c20479d5e70a5e1aac7',
          'type' => 'library',
          'install_path' => '/var/www/html/vendor/composer/../nette/utils',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'nikic/php-parser' => 
        array (
          'pretty_version' => 'v5.8.0',
          'version' => '5.8.0.0',
          'reference' => '044a6a392ff8ad0d61f14370a5fbbd0a0107152f',
          'type' => 'library',
          'install_path' => '/var/www/html/vendor/composer/../nikic/php-parser',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'nunomaduro/collision' => 
        array (
          'pretty_version' => 'v8.9.4',
          'version' => '8.9.4.0',
          'reference' => '716af8f95a470e9094cfca09ed897b023be191a5',
          'type' => 'library',
          'install_path' => '/var/www/html/vendor/composer/../nunomaduro/collision',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'nunomaduro/termwind' => 
        array (
          'pretty_version' => 'v2.4.0',
          'version' => '2.4.0.0',
          'reference' => '712a31b768f5daea284c2169a7d227031001b9a8',
          'type' => 'library',
          'install_path' => '/var/www/html/vendor/composer/../nunomaduro/termwind',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'phar-io/manifest' => 
        array (
          'pretty_version' => '2.0.4',
          'version' => '2.0.4.0',
          'reference' => '54750ef60c58e43759730615a392c31c80e23176',
          'type' => 'library',
          'install_path' => '/var/www/html/vendor/composer/../phar-io/manifest',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'phar-io/version' => 
        array (
          'pretty_version' => '3.2.1',
          'version' => '3.2.1.0',
          'reference' => '4f7fd7836c6f332bb2933569e566a0d6c4cbed74',
          'type' => 'library',
          'install_path' => '/var/www/html/vendor/composer/../phar-io/version',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'phpoption/phpoption' => 
        array (
          'pretty_version' => '1.9.5',
          'version' => '1.9.5.0',
          'reference' => '75365b91986c2405cf5e1e012c5595cd487a98be',
          'type' => 'library',
          'install_path' => '/var/www/html/vendor/composer/../phpoption/phpoption',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'phpstan/phpstan' => 
        array (
          'pretty_version' => '2.2.5',
          'version' => '2.2.5.0',
          'reference' => '909c1e5fef7989ac0d0c1c5c42e32a5c4f6198a0',
          'type' => 'library',
          'install_path' => '/var/www/html/vendor/composer/../phpstan/phpstan',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'phpunit/php-code-coverage' => 
        array (
          'pretty_version' => '11.0.12',
          'version' => '11.0.12.0',
          'reference' => '2c1ed04922802c15e1de5d7447b4856de949cf56',
          'type' => 'library',
          'install_path' => '/var/www/html/vendor/composer/../phpunit/php-code-coverage',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'phpunit/php-file-iterator' => 
        array (
          'pretty_version' => '5.1.1',
          'version' => '5.1.1.0',
          'reference' => '2f3a64888c814fc235386b7387dd5b5ed92ad903',
          'type' => 'library',
          'install_path' => '/var/www/html/vendor/composer/../phpunit/php-file-iterator',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'phpunit/php-invoker' => 
        array (
          'pretty_version' => '5.0.1',
          'version' => '5.0.1.0',
          'reference' => 'c1ca3814734c07492b3d4c5f794f4b0995333da2',
          'type' => 'library',
          'install_path' => '/var/www/html/vendor/composer/../phpunit/php-invoker',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'phpunit/php-text-template' => 
        array (
          'pretty_version' => '4.0.1',
          'version' => '4.0.1.0',
          'reference' => '3e0404dc6b300e6bf56415467ebcb3fe4f33e964',
          'type' => 'library',
          'install_path' => '/var/www/html/vendor/composer/../phpunit/php-text-template',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'phpunit/php-timer' => 
        array (
          'pretty_version' => '7.0.1',
          'version' => '7.0.1.0',
          'reference' => '3b415def83fbcb41f991d9ebf16ae4ad8b7837b3',
          'type' => 'library',
          'install_path' => '/var/www/html/vendor/composer/../phpunit/php-timer',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'phpunit/phpunit' => 
        array (
          'pretty_version' => '11.5.56',
          'version' => '11.5.56.0',
          'reference' => '5f83edffa6967c3db468d48a695ec7bcb02e9256',
          'type' => 'library',
          'install_path' => '/var/www/html/vendor/composer/../phpunit/phpunit',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'psr/clock' => 
        array (
          'pretty_version' => '1.0.0',
          'version' => '1.0.0.0',
          'reference' => 'e41a24703d4560fd0acb709162f73b8adfc3aa0d',
          'type' => 'library',
          'install_path' => '/var/www/html/vendor/composer/../psr/clock',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'psr/clock-implementation' => 
        array (
          'dev_requirement' => false,
          'provided' => 
          array (
            0 => '1.0',
          ),
        ),
        'psr/container' => 
        array (
          'pretty_version' => '2.0.2',
          'version' => '2.0.2.0',
          'reference' => 'c71ecc56dfe541dbd90c5360474fbc405f8d5963',
          'type' => 'library',
          'install_path' => '/var/www/html/vendor/composer/../psr/container',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'psr/container-implementation' => 
        array (
          'dev_requirement' => false,
          'provided' => 
          array (
            0 => '1.1|2.0',
          ),
        ),
        'psr/event-dispatcher' => 
        array (
          'pretty_version' => '1.0.0',
          'version' => '1.0.0.0',
          'reference' => 'dbefd12671e8a14ec7f180cab83036ed26714bb0',
          'type' => 'library',
          'install_path' => '/var/www/html/vendor/composer/../psr/event-dispatcher',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'psr/event-dispatcher-implementation' => 
        array (
          'dev_requirement' => false,
          'provided' => 
          array (
            0 => '1.0',
          ),
        ),
        'psr/http-client' => 
        array (
          'pretty_version' => '1.0.3',
          'version' => '1.0.3.0',
          'reference' => 'bb5906edc1c324c9a05aa0873d40117941e5fa90',
          'type' => 'library',
          'install_path' => '/var/www/html/vendor/composer/../psr/http-client',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'psr/http-client-implementation' => 
        array (
          'dev_requirement' => false,
          'provided' => 
          array (
            0 => '1.0',
          ),
        ),
        'psr/http-factory' => 
        array (
          'pretty_version' => '1.1.0',
          'version' => '1.1.0.0',
          'reference' => '2b4765fddfe3b508ac62f829e852b1501d3f6e8a',
          'type' => 'library',
          'install_path' => '/var/www/html/vendor/composer/../psr/http-factory',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'psr/http-factory-implementation' => 
        array (
          'dev_requirement' => false,
          'provided' => 
          array (
            0 => '1.0',
          ),
        ),
        'psr/http-message' => 
        array (
          'pretty_version' => '2.0',
          'version' => '2.0.0.0',
          'reference' => '402d35bcb92c70c026d1a6a9883f06b2ead23d71',
          'type' => 'library',
          'install_path' => '/var/www/html/vendor/composer/../psr/http-message',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'psr/http-message-implementation' => 
        array (
          'dev_requirement' => false,
          'provided' => 
          array (
            0 => '1.0',
          ),
        ),
        'psr/log' => 
        array (
          'pretty_version' => '3.0.2',
          'version' => '3.0.2.0',
          'reference' => 'f16e1d5863e37f8d8c2a01719f5b34baa2b714d3',
          'type' => 'library',
          'install_path' => '/var/www/html/vendor/composer/../psr/log',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'psr/log-implementation' => 
        array (
          'dev_requirement' => false,
          'provided' => 
          array (
            0 => '1.0|2.0|3.0',
            1 => '3.0.0',
          ),
        ),
        'psr/simple-cache' => 
        array (
          'pretty_version' => '3.0.0',
          'version' => '3.0.0.0',
          'reference' => '764e0b3939f5ca87cb904f570ef9be2d78a07865',
          'type' => 'library',
          'install_path' => '/var/www/html/vendor/composer/../psr/simple-cache',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'psr/simple-cache-implementation' => 
        array (
          'dev_requirement' => false,
          'provided' => 
          array (
            0 => '1.0|2.0|3.0',
          ),
        ),
        'psy/psysh' => 
        array (
          'pretty_version' => 'v0.12.24',
          'version' => '0.12.24.0',
          'reference' => 'ca0fdcf8a7617afa3adfdf1b5fef573dffb69ca1',
          'type' => 'library',
          'install_path' => '/var/www/html/vendor/composer/../psy/psysh',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'ralouphie/getallheaders' => 
        array (
          'pretty_version' => '3.0.3',
          'version' => '3.0.3.0',
          'reference' => '120b605dfeb996808c31b6477290a714d356e822',
          'type' => 'library',
          'install_path' => '/var/www/html/vendor/composer/../ralouphie/getallheaders',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'ramsey/collection' => 
        array (
          'pretty_version' => '2.1.1',
          'version' => '2.1.1.0',
          'reference' => '344572933ad0181accbf4ba763e85a0306a8c5e2',
          'type' => 'library',
          'install_path' => '/var/www/html/vendor/composer/../ramsey/collection',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'ramsey/uuid' => 
        array (
          'pretty_version' => '4.9.3',
          'version' => '4.9.3.0',
          'reference' => '1df15849d00943a67d677dc9cfd80795f038c9f8',
          'type' => 'library',
          'install_path' => '/var/www/html/vendor/composer/../ramsey/uuid',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'rhumsaa/uuid' => 
        array (
          'dev_requirement' => false,
          'replaced' => 
          array (
            0 => '4.9.3',
          ),
        ),
        'sebastian/cli-parser' => 
        array (
          'pretty_version' => '3.0.2',
          'version' => '3.0.2.0',
          'reference' => '15c5dd40dc4f38794d383bb95465193f5e0ae180',
          'type' => 'library',
          'install_path' => '/var/www/html/vendor/composer/../sebastian/cli-parser',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'sebastian/code-unit' => 
        array (
          'pretty_version' => '3.0.3',
          'version' => '3.0.3.0',
          'reference' => '54391c61e4af8078e5b276ab082b6d3c54c9ad64',
          'type' => 'library',
          'install_path' => '/var/www/html/vendor/composer/../sebastian/code-unit',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'sebastian/code-unit-reverse-lookup' => 
        array (
          'pretty_version' => '4.0.1',
          'version' => '4.0.1.0',
          'reference' => '183a9b2632194febd219bb9246eee421dad8d45e',
          'type' => 'library',
          'install_path' => '/var/www/html/vendor/composer/../sebastian/code-unit-reverse-lookup',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'sebastian/comparator' => 
        array (
          'pretty_version' => '6.3.3',
          'version' => '6.3.3.0',
          'reference' => '2c95e1e86cb8dd41beb8d502057d1081ccc8eca9',
          'type' => 'library',
          'install_path' => '/var/www/html/vendor/composer/../sebastian/comparator',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'sebastian/complexity' => 
        array (
          'pretty_version' => '4.0.1',
          'version' => '4.0.1.0',
          'reference' => 'ee41d384ab1906c68852636b6de493846e13e5a0',
          'type' => 'library',
          'install_path' => '/var/www/html/vendor/composer/../sebastian/complexity',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'sebastian/diff' => 
        array (
          'pretty_version' => '6.0.2',
          'version' => '6.0.2.0',
          'reference' => 'b4ccd857127db5d41a5b676f24b51371d76d8544',
          'type' => 'library',
          'install_path' => '/var/www/html/vendor/composer/../sebastian/diff',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'sebastian/environment' => 
        array (
          'pretty_version' => '7.2.1',
          'version' => '7.2.1.0',
          'reference' => 'a5c75038693ad2e8d4b6c15ba2403532647830c4',
          'type' => 'library',
          'install_path' => '/var/www/html/vendor/composer/../sebastian/environment',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'sebastian/exporter' => 
        array (
          'pretty_version' => '6.3.2',
          'version' => '6.3.2.0',
          'reference' => '70a298763b40b213ec087c51c739efcaa90bcd74',
          'type' => 'library',
          'install_path' => '/var/www/html/vendor/composer/../sebastian/exporter',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'sebastian/global-state' => 
        array (
          'pretty_version' => '7.0.2',
          'version' => '7.0.2.0',
          'reference' => '3be331570a721f9a4b5917f4209773de17f747d7',
          'type' => 'library',
          'install_path' => '/var/www/html/vendor/composer/../sebastian/global-state',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'sebastian/lines-of-code' => 
        array (
          'pretty_version' => '3.0.1',
          'version' => '3.0.1.0',
          'reference' => 'd36ad0d782e5756913e42ad87cb2890f4ffe467a',
          'type' => 'library',
          'install_path' => '/var/www/html/vendor/composer/../sebastian/lines-of-code',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'sebastian/object-enumerator' => 
        array (
          'pretty_version' => '6.0.1',
          'version' => '6.0.1.0',
          'reference' => 'f5b498e631a74204185071eb41f33f38d64608aa',
          'type' => 'library',
          'install_path' => '/var/www/html/vendor/composer/../sebastian/object-enumerator',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'sebastian/object-reflector' => 
        array (
          'pretty_version' => '4.0.1',
          'version' => '4.0.1.0',
          'reference' => '6e1a43b411b2ad34146dee7524cb13a068bb35f9',
          'type' => 'library',
          'install_path' => '/var/www/html/vendor/composer/../sebastian/object-reflector',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'sebastian/recursion-context' => 
        array (
          'pretty_version' => '6.0.3',
          'version' => '6.0.3.0',
          'reference' => 'f6458abbf32a6c8174f8f26261475dc133b3d9dc',
          'type' => 'library',
          'install_path' => '/var/www/html/vendor/composer/../sebastian/recursion-context',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'sebastian/type' => 
        array (
          'pretty_version' => '5.1.3',
          'version' => '5.1.3.0',
          'reference' => 'f77d2d4e78738c98d9a68d2596fe5e8fa380f449',
          'type' => 'library',
          'install_path' => '/var/www/html/vendor/composer/../sebastian/type',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'sebastian/version' => 
        array (
          'pretty_version' => '5.0.2',
          'version' => '5.0.2.0',
          'reference' => 'c687e3387b99f5b03b6caa64c74b63e2936ff874',
          'type' => 'library',
          'install_path' => '/var/www/html/vendor/composer/../sebastian/version',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'spatie/once' => 
        array (
          'dev_requirement' => false,
          'replaced' => 
          array (
            0 => '*',
          ),
        ),
        'staabm/side-effects-detector' => 
        array (
          'pretty_version' => '1.0.5',
          'version' => '1.0.5.0',
          'reference' => 'd8334211a140ce329c13726d4a715adbddd0a163',
          'type' => 'library',
          'install_path' => '/var/www/html/vendor/composer/../staabm/side-effects-detector',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'symfony/clock' => 
        array (
          'pretty_version' => 'v8.1.0',
          'version' => '8.1.0.0',
          'reference' => '701ef4de9705d6c32292ebee5e8044094a09fbf6',
          'type' => 'library',
          'install_path' => '/var/www/html/vendor/composer/../symfony/clock',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'symfony/console' => 
        array (
          'pretty_version' => 'v7.4.14',
          'version' => '7.4.14.0',
          'reference' => '92f58bc4bf97a92ed1b9f367f0cd44f20bde0e87',
          'type' => 'library',
          'install_path' => '/var/www/html/vendor/composer/../symfony/console',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'symfony/css-selector' => 
        array (
          'pretty_version' => 'v8.1.0',
          'version' => '8.1.0.0',
          'reference' => 'dc0e2be45c9b5588c82414f02ac574b4b986abcd',
          'type' => 'library',
          'install_path' => '/var/www/html/vendor/composer/../symfony/css-selector',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'symfony/deprecation-contracts' => 
        array (
          'pretty_version' => 'v3.7.1',
          'version' => '3.7.1.0',
          'reference' => 'f3202fa1b5097b0af062dc978b32ecf63404e31d',
          'type' => 'library',
          'install_path' => '/var/www/html/vendor/composer/../symfony/deprecation-contracts',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'symfony/error-handler' => 
        array (
          'pretty_version' => 'v7.4.14',
          'version' => '7.4.14.0',
          'reference' => '4e1a093b481f323e6e326451f9760c3868430673',
          'type' => 'library',
          'install_path' => '/var/www/html/vendor/composer/../symfony/error-handler',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'symfony/event-dispatcher' => 
        array (
          'pretty_version' => 'v8.1.1',
          'version' => '8.1.1.0',
          'reference' => 'abd6c11dc468725d1627302ad10f6cd486e9e3d0',
          'type' => 'library',
          'install_path' => '/var/www/html/vendor/composer/../symfony/event-dispatcher',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'symfony/event-dispatcher-contracts' => 
        array (
          'pretty_version' => 'v3.7.1',
          'version' => '3.7.1.0',
          'reference' => 'c7de7a00ffb67842132da02ea92988a39ccd9f4e',
          'type' => 'library',
          'install_path' => '/var/www/html/vendor/composer/../symfony/event-dispatcher-contracts',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'symfony/event-dispatcher-implementation' => 
        array (
          'dev_requirement' => false,
          'provided' => 
          array (
            0 => '2.0|3.0',
          ),
        ),
        'symfony/finder' => 
        array (
          'pretty_version' => 'v7.4.14',
          'version' => '7.4.14.0',
          'reference' => '13b38720174286f55d1761152b575a8d1436fc25',
          'type' => 'library',
          'install_path' => '/var/www/html/vendor/composer/../symfony/finder',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'symfony/http-foundation' => 
        array (
          'pretty_version' => 'v7.4.14',
          'version' => '7.4.14.0',
          'reference' => '06db5ae1552177bf8572f8908839f12e3c06aed3',
          'type' => 'library',
          'install_path' => '/var/www/html/vendor/composer/../symfony/http-foundation',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'symfony/http-kernel' => 
        array (
          'pretty_version' => 'v7.4.14',
          'version' => '7.4.14.0',
          'reference' => 'e99af79b1e776646eda0e1c23b7b45c184ff99be',
          'type' => 'library',
          'install_path' => '/var/www/html/vendor/composer/../symfony/http-kernel',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'symfony/mailer' => 
        array (
          'pretty_version' => 'v7.4.14',
          'version' => '7.4.14.0',
          'reference' => 'f88ce03ae73e3edb5c176ce1f337709996e88495',
          'type' => 'library',
          'install_path' => '/var/www/html/vendor/composer/../symfony/mailer',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'symfony/mime' => 
        array (
          'pretty_version' => 'v7.4.13',
          'version' => '7.4.13.0',
          'reference' => 'a845722765c4f6b2ce88beaf4f4479975b186770',
          'type' => 'library',
          'install_path' => '/var/www/html/vendor/composer/../symfony/mime',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'symfony/polyfill-ctype' => 
        array (
          'pretty_version' => 'v1.37.0',
          'version' => '1.37.0.0',
          'reference' => '141046a8f9477948ff284fa65be2095baafb94f2',
          'type' => 'library',
          'install_path' => '/var/www/html/vendor/composer/../symfony/polyfill-ctype',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'symfony/polyfill-intl-grapheme' => 
        array (
          'pretty_version' => 'v1.38.1',
          'version' => '1.38.1.0',
          'reference' => 'e9247d281d694a5120554d9afaf54e070e88a603',
          'type' => 'library',
          'install_path' => '/var/www/html/vendor/composer/../symfony/polyfill-intl-grapheme',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'symfony/polyfill-intl-idn' => 
        array (
          'pretty_version' => 'v1.38.1',
          'version' => '1.38.1.0',
          'reference' => 'dc21118016c039a66235cf93d96b435ffb282412',
          'type' => 'library',
          'install_path' => '/var/www/html/vendor/composer/../symfony/polyfill-intl-idn',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'symfony/polyfill-intl-normalizer' => 
        array (
          'pretty_version' => 'v1.38.0',
          'version' => '1.38.0.0',
          'reference' => '2d446c214bdbe5b71bde5011b060a05fece3ae6b',
          'type' => 'library',
          'install_path' => '/var/www/html/vendor/composer/../symfony/polyfill-intl-normalizer',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'symfony/polyfill-mbstring' => 
        array (
          'pretty_version' => 'v1.38.2',
          'version' => '1.38.2.0',
          'reference' => 'd3d318bad5e7a1bfbd026009c8bfb8d8f99ae6b6',
          'type' => 'library',
          'install_path' => '/var/www/html/vendor/composer/../symfony/polyfill-mbstring',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'symfony/polyfill-php80' => 
        array (
          'pretty_version' => 'v1.37.0',
          'version' => '1.37.0.0',
          'reference' => 'dfb55726c3a76ea3b6459fcfda1ec2d80a682411',
          'type' => 'library',
          'install_path' => '/var/www/html/vendor/composer/../symfony/polyfill-php80',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'symfony/polyfill-php83' => 
        array (
          'pretty_version' => 'v1.38.2',
          'version' => '1.38.2.0',
          'reference' => '796a26abb75ce49f3a84433cd81bf1009d73d5f8',
          'type' => 'library',
          'install_path' => '/var/www/html/vendor/composer/../symfony/polyfill-php83',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'symfony/polyfill-php84' => 
        array (
          'pretty_version' => 'v1.38.1',
          'version' => '1.38.1.0',
          'reference' => 'f4e1dfaee5b74aba5964fe1fd4dfc7ba5e3085fa',
          'type' => 'library',
          'install_path' => '/var/www/html/vendor/composer/../symfony/polyfill-php84',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'symfony/polyfill-php85' => 
        array (
          'pretty_version' => 'v1.38.1',
          'version' => '1.38.1.0',
          'reference' => 'ba2ba04f3352cfa2dcbbcb90aee13ed967f505b1',
          'type' => 'library',
          'install_path' => '/var/www/html/vendor/composer/../symfony/polyfill-php85',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'symfony/polyfill-uuid' => 
        array (
          'pretty_version' => 'v1.37.0',
          'version' => '1.37.0.0',
          'reference' => '26dfec253c4cf3e51b541b52ddf7e42cb0908e94',
          'type' => 'library',
          'install_path' => '/var/www/html/vendor/composer/../symfony/polyfill-uuid',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'symfony/process' => 
        array (
          'pretty_version' => 'v7.4.13',
          'version' => '7.4.13.0',
          'reference' => 'f5804be144caceb570f6747519999636b664f24c',
          'type' => 'library',
          'install_path' => '/var/www/html/vendor/composer/../symfony/process',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'symfony/routing' => 
        array (
          'pretty_version' => 'v7.4.13',
          'version' => '7.4.13.0',
          'reference' => '3a162171bb008e5e0f15dce6581373a4c0e8390d',
          'type' => 'library',
          'install_path' => '/var/www/html/vendor/composer/../symfony/routing',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'symfony/service-contracts' => 
        array (
          'pretty_version' => 'v3.7.1',
          'version' => '3.7.1.0',
          'reference' => 'c0a284bab1ed8aa0417e3d69250ab437739563a0',
          'type' => 'library',
          'install_path' => '/var/www/html/vendor/composer/../symfony/service-contracts',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'symfony/string' => 
        array (
          'pretty_version' => 'v8.1.0',
          'version' => '8.1.0.0',
          'reference' => 'afd5944f4005862d961efb85c8bbd5c523c4e3c9',
          'type' => 'library',
          'install_path' => '/var/www/html/vendor/composer/../symfony/string',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'symfony/translation' => 
        array (
          'pretty_version' => 'v8.1.1',
          'version' => '8.1.1.0',
          'reference' => '342b4218630dc2cf284cedcb2080c80b13404014',
          'type' => 'library',
          'install_path' => '/var/www/html/vendor/composer/../symfony/translation',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'symfony/translation-contracts' => 
        array (
          'pretty_version' => 'v3.7.1',
          'version' => '3.7.1.0',
          'reference' => 'ccb206b98faccc511ebae8e5fad50f2dc0b30621',
          'type' => 'library',
          'install_path' => '/var/www/html/vendor/composer/../symfony/translation-contracts',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'symfony/translation-implementation' => 
        array (
          'dev_requirement' => false,
          'provided' => 
          array (
            0 => '2.3|3.0',
          ),
        ),
        'symfony/uid' => 
        array (
          'pretty_version' => 'v7.4.9',
          'version' => '7.4.9.0',
          'reference' => '2676b524340abcfe4d6151ec698463cebafee439',
          'type' => 'library',
          'install_path' => '/var/www/html/vendor/composer/../symfony/uid',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'symfony/var-dumper' => 
        array (
          'pretty_version' => 'v7.4.14',
          'version' => '7.4.14.0',
          'reference' => '9a3a56a4a1e65a5cb4f8d13801fe8ab0a170e358',
          'type' => 'library',
          'install_path' => '/var/www/html/vendor/composer/../symfony/var-dumper',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'symfony/yaml' => 
        array (
          'pretty_version' => 'v8.1.1',
          'version' => '8.1.1.0',
          'reference' => '8e4cdd4311683516be06944f4b85244063cdb886',
          'type' => 'library',
          'install_path' => '/var/www/html/vendor/composer/../symfony/yaml',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'theseer/tokenizer' => 
        array (
          'pretty_version' => '1.3.1',
          'version' => '1.3.1.0',
          'reference' => 'b7489ce515e168639d17feec34b8847c326b0b3c',
          'type' => 'library',
          'install_path' => '/var/www/html/vendor/composer/../theseer/tokenizer',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'tijsverkoyen/css-to-inline-styles' => 
        array (
          'pretty_version' => 'v2.4.0',
          'version' => '2.4.0.0',
          'reference' => 'f0292ccf0ec75843d65027214426b6b163b48b41',
          'type' => 'library',
          'install_path' => '/var/www/html/vendor/composer/../tijsverkoyen/css-to-inline-styles',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'vlucas/phpdotenv' => 
        array (
          'pretty_version' => 'v5.6.4',
          'version' => '5.6.4.0',
          'reference' => '416df702837983f8d5ff48c9c3fee4f5f57b980b',
          'type' => 'library',
          'install_path' => '/var/www/html/vendor/composer/../vlucas/phpdotenv',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'voku/portable-ascii' => 
        array (
          'pretty_version' => '2.1.1',
          'version' => '2.1.1.0',
          'reference' => '8e1051fe39379367aecf014f41744ce7539a856f',
          'type' => 'library',
          'install_path' => '/var/www/html/vendor/composer/../voku/portable-ascii',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
      ),
    ),
  ),
  'executedFilesHashes' => 
  array (
    '/var/www/html/vendor/larastan/larastan/bootstrap.php' => '5a3eacbf63b3e41659adfee92facededf8e020a932800f93c9a8b0e67f235805',
    'phar:///var/www/html/vendor/phpstan/phpstan/phpstan.phar/stubs/runtime/Attribute85.php' => 'cb8b31e82c61ce197871c9e8a6f122256751f2ab606dd2be90846d4fa5f8933e',
    'phar:///var/www/html/vendor/phpstan/phpstan/phpstan.phar/stubs/runtime/ReflectionAttribute.php' => 'c0068e383717870a304781d462f7e2afe1c6f24e9133851852a2aca96b4fa26f',
    'phar:///var/www/html/vendor/phpstan/phpstan/phpstan.phar/stubs/runtime/ReflectionIntersectionType.php' => '65fe0a8bc6fe285d8ddc8798ab5b9299920af70db5ad74596bc08df823e7c5d9',
    'phar:///var/www/html/vendor/phpstan/phpstan/phpstan.phar/stubs/runtime/ReflectionUnionType.php' => '1e2fe940e4ba4e00d9ee6adb2af3ee1bf333e6f8afe61c61deb038886d293427',
  ),
  'phpExtensions' => 
  array (
    0 => 'Core',
    1 => 'PDO',
    2 => 'Phar',
    3 => 'Reflection',
    4 => 'SPL',
    5 => 'SimpleXML',
    6 => 'Zend OPcache',
    7 => 'bcmath',
    8 => 'ctype',
    9 => 'curl',
    10 => 'date',
    11 => 'dom',
    12 => 'fileinfo',
    13 => 'filter',
    14 => 'gd',
    15 => 'hash',
    16 => 'iconv',
    17 => 'intl',
    18 => 'json',
    19 => 'libxml',
    20 => 'mbstring',
    21 => 'mysqlnd',
    22 => 'openssl',
    23 => 'pcre',
    24 => 'pdo_pgsql',
    25 => 'pdo_sqlite',
    26 => 'posix',
    27 => 'random',
    28 => 'readline',
    29 => 'redis',
    30 => 'session',
    31 => 'sodium',
    32 => 'sqlite3',
    33 => 'standard',
    34 => 'tokenizer',
    35 => 'xml',
    36 => 'xmlreader',
    37 => 'xmlwriter',
    38 => 'zip',
    39 => 'zlib',
  ),
  'stubFiles' => 
  array (
  ),
  'level' => '5',
),
	'projectExtensionFiles' => array (
),
	'errorsCallback' => static function (): array { return array (
); },
	'locallyIgnoredErrorsCallback' => static function (): array { return array (
); },
	'linesToIgnore' => array (
),
	'unmatchedLineIgnores' => array (
),
	'collectedDataCallback' => static function (): array { return array (
  '/var/www/html/app/Enums/AppointmentStatus.php' => 
  array (
    'PHPStan\\Rules\\DeadCode\\MethodWithoutImpurePointsCollector' => 
    array (
      0 => 
      array (
        0 => 'App\\Enums\\AppointmentStatus',
        1 => 'occupyingStatuses',
        2 => 'App\\Enums\\AppointmentStatus',
        3 => 
        array (
        ),
      ),
      1 => 
      array (
        0 => 'App\\Enums\\AppointmentStatus',
        1 => 'occupiesTime',
        2 => 'App\\Enums\\AppointmentStatus',
        3 => 
        array (
          0 => 'm' . "\0" . 'app\\enums\\appointmentstatus' . "\0" . 'occupyingstatuses',
        ),
      ),
    ),
  ),
  '/var/www/html/app/Exceptions/Appointments/DentistConflictException.php' => 
  array (
    'PHPStan\\Rules\\DeadCode\\ConstructorWithoutImpurePointsCollector' => 
    array (
      0 => 
      array (
        0 => 'App\\Exceptions\\Appointments\\DentistConflictException',
        1 => 
        array (
        ),
      ),
    ),
  ),
  '/var/www/html/app/Exceptions/Appointments/EarlyNoShowException.php' => 
  array (
    'PHPStan\\Rules\\DeadCode\\ConstructorWithoutImpurePointsCollector' => 
    array (
      0 => 
      array (
        0 => 'App\\Exceptions\\Appointments\\EarlyNoShowException',
        1 => 
        array (
        ),
      ),
    ),
  ),
  '/var/www/html/app/Exceptions/Appointments/InvalidStatusTransitionException.php' => 
  array (
    'PHPStan\\Rules\\DeadCode\\ConstructorWithoutImpurePointsCollector' => 
    array (
      0 => 
      array (
        0 => 'App\\Exceptions\\Appointments\\InvalidStatusTransitionException',
        1 => 
        array (
        ),
      ),
    ),
  ),
  '/var/www/html/app/Exceptions/Appointments/OutsideWorkingHoursException.php' => 
  array (
    'PHPStan\\Rules\\DeadCode\\ConstructorWithoutImpurePointsCollector' => 
    array (
      0 => 
      array (
        0 => 'App\\Exceptions\\Appointments\\OutsideWorkingHoursException',
        1 => 
        array (
        ),
      ),
    ),
  ),
  '/var/www/html/app/Exceptions/Appointments/PatientConflictException.php' => 
  array (
    'PHPStan\\Rules\\DeadCode\\ConstructorWithoutImpurePointsCollector' => 
    array (
      0 => 
      array (
        0 => 'App\\Exceptions\\Appointments\\PatientConflictException',
        1 => 
        array (
        ),
      ),
    ),
  ),
  '/var/www/html/app/Http/Controllers/Api/AppointmentController.php' => 
  array (
    'PHPStan\\Rules\\DeadCode\\ConstructorWithoutImpurePointsCollector' => 
    array (
      0 => 
      array (
        0 => 'App\\Http\\Controllers\\Api\\AppointmentController',
        1 => 
        array (
        ),
      ),
    ),
  ),
  '/var/www/html/app/Http/Controllers/Api/AppointmentTypeController.php' => 
  array (
    'PHPStan\\Rules\\DeadCode\\ConstructorWithoutImpurePointsCollector' => 
    array (
      0 => 
      array (
        0 => 'App\\Http\\Controllers\\Api\\AppointmentTypeController',
        1 => 
        array (
        ),
      ),
    ),
  ),
  '/var/www/html/app/Http/Controllers/Api/AuthController.php' => 
  array (
    'PHPStan\\Rules\\DeadCode\\ConstructorWithoutImpurePointsCollector' => 
    array (
      0 => 
      array (
        0 => 'App\\Http\\Controllers\\Api\\AuthController',
        1 => 
        array (
        ),
      ),
    ),
  ),
  '/var/www/html/app/Http/Controllers/Api/DashboardController.php' => 
  array (
    'PHPStan\\Rules\\DeadCode\\ConstructorWithoutImpurePointsCollector' => 
    array (
      0 => 
      array (
        0 => 'App\\Http\\Controllers\\Api\\DashboardController',
        1 => 
        array (
        ),
      ),
    ),
  ),
  '/var/www/html/app/Http/Controllers/Api/DentistTimeOffController.php' => 
  array (
    'PHPStan\\Rules\\DeadCode\\ConstructorWithoutImpurePointsCollector' => 
    array (
      0 => 
      array (
        0 => 'App\\Http\\Controllers\\Api\\DentistTimeOffController',
        1 => 
        array (
        ),
      ),
    ),
  ),
  '/var/www/html/app/Http/Controllers/Api/DentistWorkingHourController.php' => 
  array (
    'PHPStan\\Rules\\DeadCode\\ConstructorWithoutImpurePointsCollector' => 
    array (
      0 => 
      array (
        0 => 'App\\Http\\Controllers\\Api\\DentistWorkingHourController',
        1 => 
        array (
        ),
      ),
    ),
  ),
  '/var/www/html/app/Http/Controllers/Api/PatientController.php' => 
  array (
    'PHPStan\\Rules\\DeadCode\\ConstructorWithoutImpurePointsCollector' => 
    array (
      0 => 
      array (
        0 => 'App\\Http\\Controllers\\Api\\PatientController',
        1 => 
        array (
        ),
      ),
    ),
  ),
  '/var/www/html/app/Http/Controllers/Api/UserController.php' => 
  array (
    'PHPStan\\Rules\\DeadCode\\ConstructorWithoutImpurePointsCollector' => 
    array (
      0 => 
      array (
        0 => 'App\\Http\\Controllers\\Api\\UserController',
        1 => 
        array (
        ),
      ),
    ),
  ),
  '/var/www/html/app/Http/Controllers/Controller.php' => 
  array (
    'PHPStan\\Rules\\Traits\\TraitUseCollector' => 
    array (
      0 => 
      array (
        0 => 'Illuminate\\Foundation\\Auth\\Access\\AuthorizesRequests',
      ),
    ),
  ),
  '/var/www/html/app/Http/Requests/Appointment/AvailableSlotsRequest.php' => 
  array (
    'PHPStan\\Rules\\DeadCode\\MethodWithoutImpurePointsCollector' => 
    array (
      0 => 
      array (
        0 => 'App\\Http\\Requests\\Appointment\\AvailableSlotsRequest',
        1 => 'authorize',
        2 => 'App\\Http\\Requests\\Appointment\\AvailableSlotsRequest',
        3 => 
        array (
        ),
      ),
    ),
  ),
  '/var/www/html/app/Http/Requests/Appointment/CancelAppointmentRequest.php' => 
  array (
    'PHPStan\\Rules\\DeadCode\\MethodWithoutImpurePointsCollector' => 
    array (
      0 => 
      array (
        0 => 'App\\Http\\Requests\\Appointment\\CancelAppointmentRequest',
        1 => 'rules',
        2 => 'App\\Http\\Requests\\Appointment\\CancelAppointmentRequest',
        3 => 
        array (
        ),
      ),
    ),
  ),
  '/var/www/html/app/Http/Requests/Appointment/IndexAppointmentRequest.php' => 
  array (
    'PHPStan\\Rules\\DeadCode\\MethodWithoutImpurePointsCollector' => 
    array (
      0 => 
      array (
        0 => 'App\\Http\\Requests\\Appointment\\IndexAppointmentRequest',
        1 => 'authorize',
        2 => 'App\\Http\\Requests\\Appointment\\IndexAppointmentRequest',
        3 => 
        array (
        ),
      ),
    ),
  ),
  '/var/www/html/app/Http/Requests/Appointment/MarkNoShowAppointmentRequest.php' => 
  array (
    'PHPStan\\Rules\\DeadCode\\MethodWithoutImpurePointsCollector' => 
    array (
      0 => 
      array (
        0 => 'App\\Http\\Requests\\Appointment\\MarkNoShowAppointmentRequest',
        1 => 'rules',
        2 => 'App\\Http\\Requests\\Appointment\\MarkNoShowAppointmentRequest',
        3 => 
        array (
        ),
      ),
    ),
  ),
  '/var/www/html/app/Http/Requests/AppointmentType/StoreAppointmentTypeRequest.php' => 
  array (
    'PHPStan\\Rules\\DeadCode\\MethodWithoutImpurePointsCollector' => 
    array (
      0 => 
      array (
        0 => 'App\\Http\\Requests\\AppointmentType\\StoreAppointmentTypeRequest',
        1 => 'rules',
        2 => 'App\\Http\\Requests\\AppointmentType\\StoreAppointmentTypeRequest',
        3 => 
        array (
        ),
      ),
    ),
  ),
  '/var/www/html/app/Http/Requests/AppointmentType/UpdateAppointmentTypeRequest.php' => 
  array (
    'PHPStan\\Rules\\DeadCode\\MethodWithoutImpurePointsCollector' => 
    array (
      0 => 
      array (
        0 => 'App\\Http\\Requests\\AppointmentType\\UpdateAppointmentTypeRequest',
        1 => 'rules',
        2 => 'App\\Http\\Requests\\AppointmentType\\UpdateAppointmentTypeRequest',
        3 => 
        array (
        ),
      ),
    ),
  ),
  '/var/www/html/app/Http/Requests/Auth/LoginRequest.php' => 
  array (
    'PHPStan\\Rules\\DeadCode\\MethodWithoutImpurePointsCollector' => 
    array (
      0 => 
      array (
        0 => 'App\\Http\\Requests\\Auth\\LoginRequest',
        1 => 'authorize',
        2 => 'App\\Http\\Requests\\Auth\\LoginRequest',
        3 => 
        array (
        ),
      ),
      1 => 
      array (
        0 => 'App\\Http\\Requests\\Auth\\LoginRequest',
        1 => 'rules',
        2 => 'App\\Http\\Requests\\Auth\\LoginRequest',
        3 => 
        array (
        ),
      ),
    ),
  ),
  '/var/www/html/app/Http/Requests/DentistTimeOff/StoreDentistTimeOffRequest.php' => 
  array (
    'PHPStan\\Rules\\DeadCode\\MethodWithoutImpurePointsCollector' => 
    array (
      0 => 
      array (
        0 => 'App\\Http\\Requests\\DentistTimeOff\\StoreDentistTimeOffRequest',
        1 => 'rules',
        2 => 'App\\Http\\Requests\\DentistTimeOff\\StoreDentistTimeOffRequest',
        3 => 
        array (
        ),
      ),
    ),
  ),
  '/var/www/html/app/Http/Requests/DentistWorkingHour/StoreDentistWorkingHourRequest.php' => 
  array (
    'PHPStan\\Rules\\DeadCode\\MethodWithoutImpurePointsCollector' => 
    array (
      0 => 
      array (
        0 => 'App\\Http\\Requests\\DentistWorkingHour\\StoreDentistWorkingHourRequest',
        1 => 'rules',
        2 => 'App\\Http\\Requests\\DentistWorkingHour\\StoreDentistWorkingHourRequest',
        3 => 
        array (
        ),
      ),
    ),
  ),
  '/var/www/html/app/Http/Requests/Patient/StorePatientRequest.php' => 
  array (
    'PHPStan\\Rules\\DeadCode\\MethodWithoutImpurePointsCollector' => 
    array (
      0 => 
      array (
        0 => 'App\\Http\\Requests\\Patient\\StorePatientRequest',
        1 => 'rules',
        2 => 'App\\Http\\Requests\\Patient\\StorePatientRequest',
        3 => 
        array (
          0 => 'm' . "\0" . 'illuminate\\validation\\rule' . "\0" . 'enum',
          1 => 'm' . "\0" . 'illuminate\\validation\\rule' . "\0" . 'unique',
        ),
      ),
    ),
  ),
  '/var/www/html/app/Http/Resources/DentistWorkingHourResource.php' => 
  array (
    'PHPStan\\Rules\\DeadCode\\MethodWithoutImpurePointsCollector' => 
    array (
      0 => 
      array (
        0 => 'App\\Http\\Resources\\DentistWorkingHourResource',
        1 => 'toArray',
        2 => 'App\\Http\\Resources\\DentistWorkingHourResource',
        3 => 
        array (
        ),
      ),
    ),
  ),
  '/var/www/html/app/Http/Resources/UserResource.php' => 
  array (
    'PHPStan\\Rules\\DeadCode\\MethodWithoutImpurePointsCollector' => 
    array (
      0 => 
      array (
        0 => 'App\\Http\\Resources\\UserResource',
        1 => 'toArray',
        2 => 'App\\Http\\Resources\\UserResource',
        3 => 
        array (
        ),
      ),
    ),
  ),
  '/var/www/html/app/Models/Appointment.php' => 
  array (
    'PHPStan\\Rules\\Comparison\\ConstantConditionInTraitCollector' => 
    array (
      0 => 
      array (
        0 => 'PHPStan\\Rules\\Comparison\\ImpossibleCheckTypeStaticMethodCallRule',
        1 => 'App\\Models\\Concerns\\Auditable',
        2 => 'static::observe(\\App\\Observers\\AuditObserver::class):17',
        3 => NULL,
      ),
      1 => 
      array (
        0 => 'PHPStan\\Rules\\Comparison\\ImpossibleCheckTypeMethodCallRule',
        1 => 'App\\Models\\Concerns\\Auditable',
        2 => '$this->morphMany(\\App\\Models\\AuditLog::class, \'auditable\')->latest(\'created_at\'):22',
        3 => NULL,
      ),
      2 => 
      array (
        0 => 'PHPStan\\Rules\\Comparison\\ImpossibleCheckTypeMethodCallRule',
        1 => 'App\\Models\\Concerns\\Auditable',
        2 => '$this->morphMany(\\App\\Models\\AuditLog::class, \'auditable\'):22',
        3 => NULL,
      ),
    ),
    'PHPStan\\Rules\\DeadCode\\MethodWithoutImpurePointsCollector' => 
    array (
      0 => 
      array (
        0 => 'App\\Models\\Appointment',
        1 => 'casts',
        2 => 'App\\Models\\Appointment',
        3 => 
        array (
        ),
      ),
    ),
    'PHPStan\\Rules\\Traits\\TraitUseCollector' => 
    array (
      0 => 
      array (
        0 => 'App\\Models\\Concerns\\Auditable',
        1 => 'Illuminate\\Database\\Eloquent\\Factories\\HasFactory',
        2 => 'Illuminate\\Database\\Eloquent\\Concerns\\HasUuids',
        3 => 'Illuminate\\Database\\Eloquent\\SoftDeletes',
      ),
    ),
  ),
  '/var/www/html/app/Models/AppointmentReminder.php' => 
  array (
    'PHPStan\\Rules\\DeadCode\\MethodWithoutImpurePointsCollector' => 
    array (
      0 => 
      array (
        0 => 'App\\Models\\AppointmentReminder',
        1 => 'casts',
        2 => 'App\\Models\\AppointmentReminder',
        3 => 
        array (
        ),
      ),
    ),
    'PHPStan\\Rules\\Traits\\TraitUseCollector' => 
    array (
      0 => 
      array (
        0 => 'Illuminate\\Database\\Eloquent\\Factories\\HasFactory',
        1 => 'Illuminate\\Database\\Eloquent\\Concerns\\HasUuids',
      ),
    ),
  ),
  '/var/www/html/app/Models/AppointmentType.php' => 
  array (
    'PHPStan\\Rules\\DeadCode\\MethodWithoutImpurePointsCollector' => 
    array (
      0 => 
      array (
        0 => 'App\\Models\\AppointmentType',
        1 => 'casts',
        2 => 'App\\Models\\AppointmentType',
        3 => 
        array (
        ),
      ),
    ),
    'PHPStan\\Rules\\Traits\\TraitUseCollector' => 
    array (
      0 => 
      array (
        0 => 'Illuminate\\Database\\Eloquent\\Factories\\HasFactory',
        1 => 'Illuminate\\Database\\Eloquent\\Concerns\\HasUuids',
      ),
    ),
  ),
  '/var/www/html/app/Models/AuditLog.php' => 
  array (
    'PHPStan\\Rules\\DeadCode\\MethodWithoutImpurePointsCollector' => 
    array (
      0 => 
      array (
        0 => 'App\\Models\\AuditLog',
        1 => 'casts',
        2 => 'App\\Models\\AuditLog',
        3 => 
        array (
        ),
      ),
    ),
    'PHPStan\\Rules\\Traits\\TraitUseCollector' => 
    array (
      0 => 
      array (
        0 => 'Illuminate\\Database\\Eloquent\\Concerns\\HasUuids',
      ),
    ),
  ),
  '/var/www/html/app/Models/Concerns/Auditable.php' => 
  array (
    'PHPStan\\Rules\\Traits\\TraitDeclarationCollector' => 
    array (
      0 => 
      array (
        0 => 'App\\Models\\Concerns\\Auditable',
        1 => 13,
      ),
    ),
  ),
  '/var/www/html/app/Models/DentistTimeOff.php' => 
  array (
    'PHPStan\\Rules\\DeadCode\\MethodWithoutImpurePointsCollector' => 
    array (
      0 => 
      array (
        0 => 'App\\Models\\DentistTimeOff',
        1 => 'casts',
        2 => 'App\\Models\\DentistTimeOff',
        3 => 
        array (
        ),
      ),
    ),
    'PHPStan\\Rules\\Traits\\TraitUseCollector' => 
    array (
      0 => 
      array (
        0 => 'Illuminate\\Database\\Eloquent\\Factories\\HasFactory',
        1 => 'Illuminate\\Database\\Eloquent\\Concerns\\HasUuids',
      ),
    ),
  ),
  '/var/www/html/app/Models/DentistWorkingHour.php' => 
  array (
    'PHPStan\\Rules\\DeadCode\\MethodWithoutImpurePointsCollector' => 
    array (
      0 => 
      array (
        0 => 'App\\Models\\DentistWorkingHour',
        1 => 'casts',
        2 => 'App\\Models\\DentistWorkingHour',
        3 => 
        array (
        ),
      ),
    ),
    'PHPStan\\Rules\\Traits\\TraitUseCollector' => 
    array (
      0 => 
      array (
        0 => 'Illuminate\\Database\\Eloquent\\Factories\\HasFactory',
        1 => 'Illuminate\\Database\\Eloquent\\Concerns\\HasUuids',
      ),
    ),
  ),
  '/var/www/html/app/Models/Patient.php' => 
  array (
    'PHPStan\\Rules\\Comparison\\ConstantConditionInTraitCollector' => 
    array (
      0 => 
      array (
        0 => 'PHPStan\\Rules\\Comparison\\ImpossibleCheckTypeStaticMethodCallRule',
        1 => 'App\\Models\\Concerns\\Auditable',
        2 => 'static::observe(\\App\\Observers\\AuditObserver::class):17',
        3 => NULL,
      ),
      1 => 
      array (
        0 => 'PHPStan\\Rules\\Comparison\\ImpossibleCheckTypeMethodCallRule',
        1 => 'App\\Models\\Concerns\\Auditable',
        2 => '$this->morphMany(\\App\\Models\\AuditLog::class, \'auditable\')->latest(\'created_at\'):22',
        3 => NULL,
      ),
      2 => 
      array (
        0 => 'PHPStan\\Rules\\Comparison\\ImpossibleCheckTypeMethodCallRule',
        1 => 'App\\Models\\Concerns\\Auditable',
        2 => '$this->morphMany(\\App\\Models\\AuditLog::class, \'auditable\'):22',
        3 => NULL,
      ),
    ),
    'PHPStan\\Rules\\DeadCode\\MethodWithoutImpurePointsCollector' => 
    array (
      0 => 
      array (
        0 => 'App\\Models\\Patient',
        1 => 'casts',
        2 => 'App\\Models\\Patient',
        3 => 
        array (
        ),
      ),
      1 => 
      array (
        0 => 'App\\Models\\Patient',
        1 => 'getPatientCodeAttribute',
        2 => 'App\\Models\\Patient',
        3 => 
        array (
        ),
      ),
      2 => 
      array (
        0 => 'App\\Models\\Patient',
        1 => 'getFullNameAttribute',
        2 => 'App\\Models\\Patient',
        3 => 
        array (
        ),
      ),
    ),
    'PHPStan\\Rules\\Traits\\TraitUseCollector' => 
    array (
      0 => 
      array (
        0 => 'App\\Models\\Concerns\\Auditable',
        1 => 'Illuminate\\Database\\Eloquent\\Factories\\HasFactory',
        2 => 'Illuminate\\Database\\Eloquent\\Concerns\\HasUuids',
        3 => 'Illuminate\\Database\\Eloquent\\SoftDeletes',
      ),
    ),
  ),
  '/var/www/html/app/Models/User.php' => 
  array (
    'PHPStan\\Rules\\DeadCode\\MethodWithoutImpurePointsCollector' => 
    array (
      0 => 
      array (
        0 => 'App\\Models\\User',
        1 => 'casts',
        2 => 'App\\Models\\User',
        3 => 
        array (
        ),
      ),
      1 => 
      array (
        0 => 'App\\Models\\User',
        1 => 'isAdmin',
        2 => 'App\\Models\\User',
        3 => 
        array (
        ),
      ),
    ),
    'PHPStan\\Rules\\Traits\\TraitUseCollector' => 
    array (
      0 => 
      array (
        0 => 'Illuminate\\Database\\Eloquent\\Factories\\HasFactory',
        1 => 'Illuminate\\Database\\Eloquent\\Concerns\\HasUuids',
        2 => 'Illuminate\\Notifications\\Notifiable',
        3 => 'Illuminate\\Database\\Eloquent\\SoftDeletes',
      ),
    ),
  ),
  '/var/www/html/app/Observers/AuditObserver.php' => 
  array (
    'PHPStan\\Rules\\DeadCode\\ConstructorWithoutImpurePointsCollector' => 
    array (
      0 => 
      array (
        0 => 'App\\Observers\\AuditObserver',
        1 => 
        array (
        ),
      ),
    ),
  ),
  '/var/www/html/app/Policies/AppointmentPolicy.php' => 
  array (
    'PHPStan\\Rules\\DeadCode\\MethodWithoutImpurePointsCollector' => 
    array (
      0 => 
      array (
        0 => 'App\\Policies\\AppointmentPolicy',
        1 => 'viewAny',
        2 => 'App\\Policies\\AppointmentPolicy',
        3 => 
        array (
        ),
      ),
      1 => 
      array (
        0 => 'App\\Policies\\AppointmentPolicy',
        1 => 'view',
        2 => 'App\\Policies\\AppointmentPolicy',
        3 => 
        array (
        ),
      ),
      2 => 
      array (
        0 => 'App\\Policies\\AppointmentPolicy',
        1 => 'create',
        2 => 'App\\Policies\\AppointmentPolicy',
        3 => 
        array (
        ),
      ),
      3 => 
      array (
        0 => 'App\\Policies\\AppointmentPolicy',
        1 => 'update',
        2 => 'App\\Policies\\AppointmentPolicy',
        3 => 
        array (
        ),
      ),
      4 => 
      array (
        0 => 'App\\Policies\\AppointmentPolicy',
        1 => 'cancel',
        2 => 'App\\Policies\\AppointmentPolicy',
        3 => 
        array (
        ),
      ),
      5 => 
      array (
        0 => 'App\\Policies\\AppointmentPolicy',
        1 => 'markNoShow',
        2 => 'App\\Policies\\AppointmentPolicy',
        3 => 
        array (
        ),
      ),
      6 => 
      array (
        0 => 'App\\Policies\\AppointmentPolicy',
        1 => 'confirm',
        2 => 'App\\Policies\\AppointmentPolicy',
        3 => 
        array (
        ),
      ),
      7 => 
      array (
        0 => 'App\\Policies\\AppointmentPolicy',
        1 => 'checkIn',
        2 => 'App\\Policies\\AppointmentPolicy',
        3 => 
        array (
        ),
      ),
    ),
  ),
  '/var/www/html/app/Policies/AppointmentTypePolicy.php' => 
  array (
    'PHPStan\\Rules\\DeadCode\\MethodWithoutImpurePointsCollector' => 
    array (
      0 => 
      array (
        0 => 'App\\Policies\\AppointmentTypePolicy',
        1 => 'viewAny',
        2 => 'App\\Policies\\AppointmentTypePolicy',
        3 => 
        array (
        ),
      ),
      1 => 
      array (
        0 => 'App\\Policies\\AppointmentTypePolicy',
        1 => 'view',
        2 => 'App\\Policies\\AppointmentTypePolicy',
        3 => 
        array (
        ),
      ),
    ),
  ),
  '/var/www/html/app/Policies/DentistTimeOffPolicy.php' => 
  array (
    'PHPStan\\Rules\\DeadCode\\MethodWithoutImpurePointsCollector' => 
    array (
      0 => 
      array (
        0 => 'App\\Policies\\DentistTimeOffPolicy',
        1 => 'viewAny',
        2 => 'App\\Policies\\DentistTimeOffPolicy',
        3 => 
        array (
        ),
      ),
      1 => 
      array (
        0 => 'App\\Policies\\DentistTimeOffPolicy',
        1 => 'view',
        2 => 'App\\Policies\\DentistTimeOffPolicy',
        3 => 
        array (
        ),
      ),
    ),
  ),
  '/var/www/html/app/Policies/DentistWorkingHourPolicy.php' => 
  array (
    'PHPStan\\Rules\\DeadCode\\MethodWithoutImpurePointsCollector' => 
    array (
      0 => 
      array (
        0 => 'App\\Policies\\DentistWorkingHourPolicy',
        1 => 'viewAny',
        2 => 'App\\Policies\\DentistWorkingHourPolicy',
        3 => 
        array (
        ),
      ),
      1 => 
      array (
        0 => 'App\\Policies\\DentistWorkingHourPolicy',
        1 => 'view',
        2 => 'App\\Policies\\DentistWorkingHourPolicy',
        3 => 
        array (
        ),
      ),
    ),
  ),
  '/var/www/html/app/Policies/PatientPolicy.php' => 
  array (
    'PHPStan\\Rules\\DeadCode\\MethodWithoutImpurePointsCollector' => 
    array (
      0 => 
      array (
        0 => 'App\\Policies\\PatientPolicy',
        1 => 'viewAny',
        2 => 'App\\Policies\\PatientPolicy',
        3 => 
        array (
        ),
      ),
      1 => 
      array (
        0 => 'App\\Policies\\PatientPolicy',
        1 => 'view',
        2 => 'App\\Policies\\PatientPolicy',
        3 => 
        array (
        ),
      ),
      2 => 
      array (
        0 => 'App\\Policies\\PatientPolicy',
        1 => 'create',
        2 => 'App\\Policies\\PatientPolicy',
        3 => 
        array (
        ),
      ),
      3 => 
      array (
        0 => 'App\\Policies\\PatientPolicy',
        1 => 'update',
        2 => 'App\\Policies\\PatientPolicy',
        3 => 
        array (
        ),
      ),
    ),
  ),
  '/var/www/html/app/Policies/UserPolicy.php' => 
  array (
    'PHPStan\\Rules\\DeadCode\\MethodWithoutImpurePointsCollector' => 
    array (
      0 => 
      array (
        0 => 'App\\Policies\\UserPolicy',
        1 => 'viewAny',
        2 => 'App\\Policies\\UserPolicy',
        3 => 
        array (
        ),
      ),
      1 => 
      array (
        0 => 'App\\Policies\\UserPolicy',
        1 => 'view',
        2 => 'App\\Policies\\UserPolicy',
        3 => 
        array (
        ),
      ),
    ),
  ),
  '/var/www/html/app/Services/AppointmentService.php' => 
  array (
    'PHPStan\\Rules\\DeadCode\\MethodWithoutImpurePointsCollector' => 
    array (
      0 => 
      array (
        0 => 'App\\Services\\AppointmentService',
        1 => 'confirm',
        2 => 'App\\Services\\AppointmentService',
        3 => 
        array (
          0 => 'm' . "\0" . 'app\\services\\appointmentservice' . "\0" . 'transitionto',
        ),
      ),
      1 => 
      array (
        0 => 'App\\Services\\AppointmentService',
        1 => 'checkIn',
        2 => 'App\\Services\\AppointmentService',
        3 => 
        array (
          0 => 'm' . "\0" . 'app\\services\\appointmentservice' . "\0" . 'transitionto',
          1 => 'f' . "\0" . 'now',
        ),
      ),
      2 => 
      array (
        0 => 'App\\Services\\AppointmentService',
        1 => 'start',
        2 => 'App\\Services\\AppointmentService',
        3 => 
        array (
          0 => 'm' . "\0" . 'app\\services\\appointmentservice' . "\0" . 'transitionto',
          1 => 'f' . "\0" . 'now',
        ),
      ),
      3 => 
      array (
        0 => 'App\\Services\\AppointmentService',
        1 => 'complete',
        2 => 'App\\Services\\AppointmentService',
        3 => 
        array (
          0 => 'm' . "\0" . 'app\\services\\appointmentservice' . "\0" . 'transitionto',
          1 => 'f' . "\0" . 'now',
        ),
      ),
    ),
  ),
  '/var/www/html/app/Services/AppointmentTypeService.php' => 
  array (
    'PHPStan\\Rules\\DeadCode\\MethodWithoutImpurePointsCollector' => 
    array (
      0 => 
      array (
        0 => 'App\\Services\\AppointmentTypeService',
        1 => 'create',
        2 => 'App\\Services\\AppointmentTypeService',
        3 => 
        array (
          0 => 'm' . "\0" . 'illuminate\\database\\eloquent\\builder' . "\0" . 'create',
        ),
      ),
    ),
  ),
  '/var/www/html/app/Services/AuditLogService.php' => 
  array (
    'PHPStan\\Rules\\DeadCode\\PossiblyPureStaticCallCollector' => 
    array (
      0 => 
      array (
        0 => 'Illuminate\\Database\\Eloquent\\Builder',
        1 => 'create',
        2 => 23,
      ),
    ),
  ),
  '/var/www/html/app/Services/AuthService.php' => 
  array (
    'PHPStan\\Rules\\DeadCode\\PossiblyPureStaticCallCollector' => 
    array (
      0 => 
      array (
        0 => 'Illuminate\\Support\\Facades\\RateLimiter',
        1 => 'hit',
        2 => 28,
      ),
    ),
  ),
  '/var/www/html/app/Services/DashboardService.php' => 
  array (
    'PHPStan\\Rules\\DeadCode\\MethodWithoutImpurePointsCollector' => 
    array (
      0 => 
      array (
        0 => 'App\\Services\\DashboardService',
        1 => 'summary',
        2 => 'App\\Services\\DashboardService',
        3 => 
        array (
          0 => 'm' . "\0" . 'app\\services\\dashboardservice' . "\0" . 'countifexists',
        ),
      ),
    ),
  ),
  '/var/www/html/app/Services/DentistTimeOffService.php' => 
  array (
    'PHPStan\\Rules\\DeadCode\\MethodWithoutImpurePointsCollector' => 
    array (
      0 => 
      array (
        0 => 'App\\Services\\DentistTimeOffService',
        1 => 'create',
        2 => 'App\\Services\\DentistTimeOffService',
        3 => 
        array (
          0 => 'm' . "\0" . 'illuminate\\database\\eloquent\\builder' . "\0" . 'create',
        ),
      ),
    ),
  ),
  '/var/www/html/app/Services/DentistWorkingHourService.php' => 
  array (
    'PHPStan\\Rules\\DeadCode\\MethodWithoutImpurePointsCollector' => 
    array (
      0 => 
      array (
        0 => 'App\\Services\\DentistWorkingHourService',
        1 => 'create',
        2 => 'App\\Services\\DentistWorkingHourService',
        3 => 
        array (
          0 => 'm' . "\0" . 'illuminate\\database\\eloquent\\builder' . "\0" . 'create',
        ),
      ),
    ),
  ),
); },
	'dependencies' => array (
  '/var/www/html/app/Enums/AppointmentStatus.php' => 
  array (
    'fileHash' => '94663669680a7adbe90adcbaa031c1edf413a7697a3864c283ad906d1bf2c269',
    'dependentFiles' => 
    array (
      0 => '/var/www/html/app/Exceptions/Appointments/InvalidStatusTransitionException.php',
      1 => '/var/www/html/app/Http/Requests/Appointment/IndexAppointmentRequest.php',
      2 => '/var/www/html/app/Http/Resources/AppointmentResource.php',
      3 => '/var/www/html/app/Models/Appointment.php',
      4 => '/var/www/html/app/Services/AppointmentService.php',
    ),
  ),
  '/var/www/html/app/Enums/BloodType.php' => 
  array (
    'fileHash' => '9ab173795550e5a7cb2d9a8811bab7de3c75063c04ad12bcd3a4e81ca48dd843',
    'dependentFiles' => 
    array (
      0 => '/var/www/html/app/Http/Requests/Patient/StorePatientRequest.php',
      1 => '/var/www/html/app/Http/Requests/Patient/UpdatePatientRequest.php',
      2 => '/var/www/html/app/Http/Resources/PatientResource.php',
      3 => '/var/www/html/app/Models/Patient.php',
    ),
  ),
  '/var/www/html/app/Enums/PatientGender.php' => 
  array (
    'fileHash' => 'e076f5d6df184ed988b059d8a061546ecd257a7e6239fd519ffa31455512b5cb',
    'dependentFiles' => 
    array (
      0 => '/var/www/html/app/Http/Requests/Patient/StorePatientRequest.php',
      1 => '/var/www/html/app/Http/Requests/Patient/UpdatePatientRequest.php',
      2 => '/var/www/html/app/Http/Resources/PatientResource.php',
      3 => '/var/www/html/app/Models/Patient.php',
    ),
  ),
  '/var/www/html/app/Enums/UserRole.php' => 
  array (
    'fileHash' => 'd4bf79add40c640132e9de4648328781363c24355107773f4812dd493cecc4bc',
    'dependentFiles' => 
    array (
      0 => '/var/www/html/app/Http/Requests/Appointment/AvailableSlotsRequest.php',
      1 => '/var/www/html/app/Http/Requests/Appointment/IndexAppointmentRequest.php',
      2 => '/var/www/html/app/Http/Requests/Appointment/StoreAppointmentRequest.php',
      3 => '/var/www/html/app/Http/Requests/Appointment/UpdateAppointmentRequest.php',
      4 => '/var/www/html/app/Http/Requests/User/StoreUserRequest.php',
      5 => '/var/www/html/app/Http/Requests/User/UpdateUserRequest.php',
      6 => '/var/www/html/app/Http/Resources/UserResource.php',
      7 => '/var/www/html/app/Models/User.php',
      8 => '/var/www/html/app/Policies/AppointmentPolicy.php',
      9 => '/var/www/html/app/Policies/DentistTimeOffPolicy.php',
      10 => '/var/www/html/app/Policies/DentistWorkingHourPolicy.php',
      11 => '/var/www/html/app/Policies/PatientPolicy.php',
      12 => '/var/www/html/app/Services/UserService.php',
    ),
  ),
  '/var/www/html/app/Exceptions/Appointments/DentistConflictException.php' => 
  array (
    'fileHash' => '8f7d3440e1a75bd13ab651db3a16e8d52255bb018338ae1e01b76d7dfd44b89c',
    'dependentFiles' => 
    array (
      0 => '/var/www/html/app/Services/AppointmentService.php',
    ),
  ),
  '/var/www/html/app/Exceptions/Appointments/EarlyNoShowException.php' => 
  array (
    'fileHash' => '4c42035e1a463cbfbcc5edf3f940ae3fd2011d3f21ff3dabfd247ddb0f36ea58',
    'dependentFiles' => 
    array (
      0 => '/var/www/html/app/Services/AppointmentService.php',
    ),
  ),
  '/var/www/html/app/Exceptions/Appointments/InvalidStatusTransitionException.php' => 
  array (
    'fileHash' => 'cfa98c54229d0d74bd5222e729ee1efd97d2ff8d4adb29ebc42b9e648bb557f7',
    'dependentFiles' => 
    array (
      0 => '/var/www/html/app/Services/AppointmentService.php',
    ),
  ),
  '/var/www/html/app/Exceptions/Appointments/OutsideWorkingHoursException.php' => 
  array (
    'fileHash' => 'b47c0b6e2a46b8af8634d782445bc87afd5dbbf537f78f8bee5a75459998c4d4',
    'dependentFiles' => 
    array (
      0 => '/var/www/html/app/Services/AppointmentService.php',
    ),
  ),
  '/var/www/html/app/Exceptions/Appointments/PatientConflictException.php' => 
  array (
    'fileHash' => '03d5b6d7b344ea5c1b348b7ea5400d338bf0981728758d8e46b4362e119e0731',
    'dependentFiles' => 
    array (
      0 => '/var/www/html/app/Services/AppointmentService.php',
    ),
  ),
  '/var/www/html/app/Http/Controllers/Api/AppointmentController.php' => 
  array (
    'fileHash' => '9711a60cf603d5ce11b1b10e5ce70796ab7e0cabedbf623e3a1cb15a90ef58d3',
    'dependentFiles' => 
    array (
    ),
  ),
  '/var/www/html/app/Http/Controllers/Api/AppointmentTypeController.php' => 
  array (
    'fileHash' => '671f440a44d8272ef293f8c549ce01e18abad251df8624ca24d2c06dcc6deaf7',
    'dependentFiles' => 
    array (
    ),
  ),
  '/var/www/html/app/Http/Controllers/Api/AuthController.php' => 
  array (
    'fileHash' => 'bc9d4cbed0b044ffbe7480c530f4e8bc3924d05d03194e6d80998d71f832d0bb',
    'dependentFiles' => 
    array (
    ),
  ),
  '/var/www/html/app/Http/Controllers/Api/DashboardController.php' => 
  array (
    'fileHash' => 'b2ba14bd376fe5fd321ebe396a4bff056652e2a88f7bf355bc049515674fd24d',
    'dependentFiles' => 
    array (
    ),
  ),
  '/var/www/html/app/Http/Controllers/Api/DentistTimeOffController.php' => 
  array (
    'fileHash' => '0d7e3d7bd1a3d9b429a62ee83d5ef2c061230ae15a8b93d94620d0d1a9c0496d',
    'dependentFiles' => 
    array (
    ),
  ),
  '/var/www/html/app/Http/Controllers/Api/DentistWorkingHourController.php' => 
  array (
    'fileHash' => 'd01f4eb6605369d8502cd1f450d0862350e6839d499b39a1024978f5e91c1190',
    'dependentFiles' => 
    array (
    ),
  ),
  '/var/www/html/app/Http/Controllers/Api/PatientController.php' => 
  array (
    'fileHash' => '51283b3fdd541900e5197991bd007832a903627d162cb593291a0b9279ac0983',
    'dependentFiles' => 
    array (
    ),
  ),
  '/var/www/html/app/Http/Controllers/Api/UserController.php' => 
  array (
    'fileHash' => 'abe40ce259e5ca60314b185cafdcca04fc3cd2ef3df3f19b5da2a696f78a5740',
    'dependentFiles' => 
    array (
    ),
  ),
  '/var/www/html/app/Http/Controllers/Controller.php' => 
  array (
    'fileHash' => 'e5ddafa07059bfc9f8310767b0fc04dd3b8a1f50bcec1fd693b19f5555697825',
    'dependentFiles' => 
    array (
      0 => '/var/www/html/app/Http/Controllers/Api/AppointmentController.php',
      1 => '/var/www/html/app/Http/Controllers/Api/AppointmentTypeController.php',
      2 => '/var/www/html/app/Http/Controllers/Api/AuthController.php',
      3 => '/var/www/html/app/Http/Controllers/Api/DashboardController.php',
      4 => '/var/www/html/app/Http/Controllers/Api/DentistTimeOffController.php',
      5 => '/var/www/html/app/Http/Controllers/Api/DentistWorkingHourController.php',
      6 => '/var/www/html/app/Http/Controllers/Api/PatientController.php',
      7 => '/var/www/html/app/Http/Controllers/Api/UserController.php',
    ),
  ),
  '/var/www/html/app/Http/Requests/Appointment/AvailableSlotsRequest.php' => 
  array (
    'fileHash' => 'baea771fd42d07cf94efa0b27fb82ae57366f056606941a71d5c95ad50400adb',
    'dependentFiles' => 
    array (
      0 => '/var/www/html/app/Http/Controllers/Api/AppointmentController.php',
    ),
  ),
  '/var/www/html/app/Http/Requests/Appointment/CancelAppointmentRequest.php' => 
  array (
    'fileHash' => '70a001b7bc3ee9eb614238a88bbf7b5c0e0d3cc5caeee47236e4163b56c88c80',
    'dependentFiles' => 
    array (
      0 => '/var/www/html/app/Http/Controllers/Api/AppointmentController.php',
    ),
  ),
  '/var/www/html/app/Http/Requests/Appointment/IndexAppointmentRequest.php' => 
  array (
    'fileHash' => '3163316dc6d24ef3c0266a4fa1f4d29278fab1136e35cca69e73e52035f3a8cb',
    'dependentFiles' => 
    array (
      0 => '/var/www/html/app/Http/Controllers/Api/AppointmentController.php',
    ),
  ),
  '/var/www/html/app/Http/Requests/Appointment/MarkNoShowAppointmentRequest.php' => 
  array (
    'fileHash' => '6a446e7c8cfe9d0e4e671895f975dfe6d48f32c958b8c35e1dfe3309fac1b3be',
    'dependentFiles' => 
    array (
      0 => '/var/www/html/app/Http/Controllers/Api/AppointmentController.php',
    ),
  ),
  '/var/www/html/app/Http/Requests/Appointment/StoreAppointmentRequest.php' => 
  array (
    'fileHash' => '837c44526afa6ab075f8eec4e5956a8cfa6c9b3586a816816730aec5f54683b0',
    'dependentFiles' => 
    array (
      0 => '/var/www/html/app/Http/Controllers/Api/AppointmentController.php',
    ),
  ),
  '/var/www/html/app/Http/Requests/Appointment/UpdateAppointmentRequest.php' => 
  array (
    'fileHash' => 'd432ad44b0eee8d92f33fbe7ebe295679b32954587cfc2afe9421e2fb2a09626',
    'dependentFiles' => 
    array (
      0 => '/var/www/html/app/Http/Controllers/Api/AppointmentController.php',
    ),
  ),
  '/var/www/html/app/Http/Requests/AppointmentType/StoreAppointmentTypeRequest.php' => 
  array (
    'fileHash' => '5cd2b16eaee03fca09a6524deb8f851bbfff62294f06f960fb2e7df0914d1913',
    'dependentFiles' => 
    array (
      0 => '/var/www/html/app/Http/Controllers/Api/AppointmentTypeController.php',
    ),
  ),
  '/var/www/html/app/Http/Requests/AppointmentType/UpdateAppointmentTypeRequest.php' => 
  array (
    'fileHash' => 'e0a6044c2fb479c1d6a7968f2e3c53ee80e8ecec553f5444e6175536599fe947',
    'dependentFiles' => 
    array (
      0 => '/var/www/html/app/Http/Controllers/Api/AppointmentTypeController.php',
    ),
  ),
  '/var/www/html/app/Http/Requests/Auth/LoginRequest.php' => 
  array (
    'fileHash' => 'a3165c6c4295692bdf6edfbe886c08f0ff784e376c2e178f85ff33ac9ba8aea1',
    'dependentFiles' => 
    array (
      0 => '/var/www/html/app/Http/Controllers/Api/AuthController.php',
    ),
  ),
  '/var/www/html/app/Http/Requests/DentistTimeOff/StoreDentistTimeOffRequest.php' => 
  array (
    'fileHash' => '9016c790255d96c3e199e02ba87c94cd27a5559a745586e4d3fc4dc767cc10cd',
    'dependentFiles' => 
    array (
      0 => '/var/www/html/app/Http/Controllers/Api/DentistTimeOffController.php',
    ),
  ),
  '/var/www/html/app/Http/Requests/DentistWorkingHour/StoreDentistWorkingHourRequest.php' => 
  array (
    'fileHash' => '5d954feb0226f2632993a4001f25116462a7208dc77e2f8d6e13c8c9f943341b',
    'dependentFiles' => 
    array (
      0 => '/var/www/html/app/Http/Controllers/Api/DentistWorkingHourController.php',
    ),
  ),
  '/var/www/html/app/Http/Requests/Patient/StorePatientRequest.php' => 
  array (
    'fileHash' => '1e831f7735a18ddf8caf43a82c1ff5be2897bf3d79e6e9efb3c9334ea657e52e',
    'dependentFiles' => 
    array (
      0 => '/var/www/html/app/Http/Controllers/Api/PatientController.php',
    ),
  ),
  '/var/www/html/app/Http/Requests/Patient/UpdatePatientRequest.php' => 
  array (
    'fileHash' => '4e20d2cc9c6206da3544439e21aac01804111cc9aee572d988a1da9474e8d5d5',
    'dependentFiles' => 
    array (
      0 => '/var/www/html/app/Http/Controllers/Api/PatientController.php',
    ),
  ),
  '/var/www/html/app/Http/Requests/User/StoreUserRequest.php' => 
  array (
    'fileHash' => 'ff9453e92e3ca78f4befb42e2bbde7005eb8dd07e4fc8264e1ddc73246537829',
    'dependentFiles' => 
    array (
      0 => '/var/www/html/app/Http/Controllers/Api/UserController.php',
    ),
  ),
  '/var/www/html/app/Http/Requests/User/UpdateUserRequest.php' => 
  array (
    'fileHash' => 'ffacb155c0a9e3977a06504c9a27b540e24c69d2de5de136600a0bdf580b08b4',
    'dependentFiles' => 
    array (
      0 => '/var/www/html/app/Http/Controllers/Api/UserController.php',
    ),
  ),
  '/var/www/html/app/Http/Resources/AppointmentResource.php' => 
  array (
    'fileHash' => 'b257b53d6ce9cc3cad65a5a965e75755551761f6636f9b7406f6d3400870a431',
    'dependentFiles' => 
    array (
      0 => '/var/www/html/app/Http/Controllers/Api/AppointmentController.php',
    ),
  ),
  '/var/www/html/app/Http/Resources/AppointmentTypeResource.php' => 
  array (
    'fileHash' => '400bedcdcb07117aed15ad6bce14fbd8d0db913d079390bf31d5c46f40733e17',
    'dependentFiles' => 
    array (
      0 => '/var/www/html/app/Http/Controllers/Api/AppointmentTypeController.php',
      1 => '/var/www/html/app/Http/Resources/AppointmentResource.php',
    ),
  ),
  '/var/www/html/app/Http/Resources/AuditLogResource.php' => 
  array (
    'fileHash' => 'edaa74d3183cb1d3ea067ed73493459c10a108b0cabe85d2a97ad1df85738963',
    'dependentFiles' => 
    array (
      0 => '/var/www/html/app/Http/Controllers/Api/PatientController.php',
    ),
  ),
  '/var/www/html/app/Http/Resources/DentistTimeOffResource.php' => 
  array (
    'fileHash' => 'd59fdf4800013a15bfd668c42910344b47c836d35407fd48e95e02fe9181ae62',
    'dependentFiles' => 
    array (
      0 => '/var/www/html/app/Http/Controllers/Api/DentistTimeOffController.php',
    ),
  ),
  '/var/www/html/app/Http/Resources/DentistWorkingHourResource.php' => 
  array (
    'fileHash' => '3f37973a1ba14219f54689ebd4e413480da8bfc06298b89cb3974b46fceaae27',
    'dependentFiles' => 
    array (
      0 => '/var/www/html/app/Http/Controllers/Api/DentistWorkingHourController.php',
    ),
  ),
  '/var/www/html/app/Http/Resources/PatientResource.php' => 
  array (
    'fileHash' => '4575d786df46543e24f7871eb448e9aa1eb1801402d29edb4919e2945de21155',
    'dependentFiles' => 
    array (
      0 => '/var/www/html/app/Http/Controllers/Api/PatientController.php',
    ),
  ),
  '/var/www/html/app/Http/Resources/UserResource.php' => 
  array (
    'fileHash' => 'b03af7114cf1bd1958695b822d6ab7ee03a3500f07efde12d6dfbb97d6c1898e',
    'dependentFiles' => 
    array (
      0 => '/var/www/html/app/Http/Controllers/Api/AuthController.php',
      1 => '/var/www/html/app/Http/Controllers/Api/UserController.php',
    ),
  ),
  '/var/www/html/app/Models/Appointment.php' => 
  array (
    'fileHash' => '3478b04ccfbb0d7eb0bb040877b71937dd58836fdae15a48dbc5f6ccc9cc7629',
    'dependentFiles' => 
    array (
      0 => '/var/www/html/app/Http/Controllers/Api/AppointmentController.php',
      1 => '/var/www/html/app/Http/Requests/Appointment/StoreAppointmentRequest.php',
      2 => '/var/www/html/app/Http/Resources/AppointmentResource.php',
      3 => '/var/www/html/app/Models/AppointmentReminder.php',
      4 => '/var/www/html/app/Models/AppointmentType.php',
      5 => '/var/www/html/app/Models/Patient.php',
      6 => '/var/www/html/app/Models/User.php',
      7 => '/var/www/html/app/Policies/AppointmentPolicy.php',
      8 => '/var/www/html/app/Services/AppointmentService.php',
    ),
  ),
  '/var/www/html/app/Models/AppointmentReminder.php' => 
  array (
    'fileHash' => '1681a52046762f5a702f24ecfc134ae79c4a490ebf2b14269b9a4ee64a3ce474',
    'dependentFiles' => 
    array (
      0 => '/var/www/html/app/Models/Appointment.php',
    ),
  ),
  '/var/www/html/app/Models/AppointmentType.php' => 
  array (
    'fileHash' => 'f1b55db720b7b0b1d397c522eaec55791cfdfd8bd68f521c95f5a63e61c79eb1',
    'dependentFiles' => 
    array (
      0 => '/var/www/html/app/Http/Controllers/Api/AppointmentTypeController.php',
      1 => '/var/www/html/app/Http/Requests/AppointmentType/StoreAppointmentTypeRequest.php',
      2 => '/var/www/html/app/Http/Resources/AppointmentResource.php',
      3 => '/var/www/html/app/Http/Resources/AppointmentTypeResource.php',
      4 => '/var/www/html/app/Models/Appointment.php',
      5 => '/var/www/html/app/Policies/AppointmentTypePolicy.php',
      6 => '/var/www/html/app/Services/AppointmentTypeService.php',
    ),
  ),
  '/var/www/html/app/Models/AuditLog.php' => 
  array (
    'fileHash' => '3c70bac32f42333f0dfd73dafb606802375f3693005cbad833f7f05052d03748',
    'dependentFiles' => 
    array (
      0 => '/var/www/html/app/Http/Controllers/Api/PatientController.php',
      1 => '/var/www/html/app/Http/Resources/AuditLogResource.php',
      2 => '/var/www/html/app/Models/Appointment.php',
      3 => '/var/www/html/app/Models/Patient.php',
      4 => '/var/www/html/app/Services/AuditLogService.php',
    ),
  ),
  '/var/www/html/app/Models/Concerns/Auditable.php' => 
  array (
    'fileHash' => '3e8ba22f57ec908d120568497896d6a0cd1d96fd31dd773eb6321f6f736b7cb4',
    'dependentFiles' => 
    array (
      0 => '/var/www/html/app/Http/Controllers/Api/AppointmentController.php',
      1 => '/var/www/html/app/Http/Controllers/Api/PatientController.php',
      2 => '/var/www/html/app/Http/Requests/Appointment/StoreAppointmentRequest.php',
      3 => '/var/www/html/app/Http/Requests/Patient/StorePatientRequest.php',
      4 => '/var/www/html/app/Http/Resources/AppointmentResource.php',
      5 => '/var/www/html/app/Http/Resources/PatientResource.php',
      6 => '/var/www/html/app/Models/Appointment.php',
      7 => '/var/www/html/app/Models/AppointmentReminder.php',
      8 => '/var/www/html/app/Models/AppointmentType.php',
      9 => '/var/www/html/app/Models/Patient.php',
      10 => '/var/www/html/app/Models/User.php',
      11 => '/var/www/html/app/Policies/AppointmentPolicy.php',
      12 => '/var/www/html/app/Policies/PatientPolicy.php',
      13 => '/var/www/html/app/Services/AppointmentService.php',
      14 => '/var/www/html/app/Services/PatientService.php',
    ),
    'usedTraitDependentFiles' => 
    array (
      0 => '/var/www/html/app/Models/Appointment.php',
      1 => '/var/www/html/app/Models/Patient.php',
    ),
  ),
  '/var/www/html/app/Models/DentistTimeOff.php' => 
  array (
    'fileHash' => '06f63a956cc0ea7642e2938b6a4cb59cfe94426309e7c62647b3de65392e3538',
    'dependentFiles' => 
    array (
      0 => '/var/www/html/app/Http/Controllers/Api/DentistTimeOffController.php',
      1 => '/var/www/html/app/Http/Requests/DentistTimeOff/StoreDentistTimeOffRequest.php',
      2 => '/var/www/html/app/Http/Resources/DentistTimeOffResource.php',
      3 => '/var/www/html/app/Models/User.php',
      4 => '/var/www/html/app/Policies/DentistTimeOffPolicy.php',
      5 => '/var/www/html/app/Services/AppointmentService.php',
      6 => '/var/www/html/app/Services/DentistTimeOffService.php',
    ),
  ),
  '/var/www/html/app/Models/DentistWorkingHour.php' => 
  array (
    'fileHash' => '0a5ca64a99ea3d186ca6468daab6e32113cb298ab5f5b0cd8ebd6754e0420d21',
    'dependentFiles' => 
    array (
      0 => '/var/www/html/app/Http/Controllers/Api/DentistWorkingHourController.php',
      1 => '/var/www/html/app/Http/Requests/DentistWorkingHour/StoreDentistWorkingHourRequest.php',
      2 => '/var/www/html/app/Http/Resources/DentistWorkingHourResource.php',
      3 => '/var/www/html/app/Models/User.php',
      4 => '/var/www/html/app/Policies/DentistWorkingHourPolicy.php',
      5 => '/var/www/html/app/Services/AppointmentService.php',
      6 => '/var/www/html/app/Services/DentistWorkingHourService.php',
    ),
  ),
  '/var/www/html/app/Models/Patient.php' => 
  array (
    'fileHash' => 'f1e0cc69d3e21b8753c40e41c5a698f227c5178b2333cc0044ecd50fc7fc0eb2',
    'dependentFiles' => 
    array (
      0 => '/var/www/html/app/Http/Controllers/Api/PatientController.php',
      1 => '/var/www/html/app/Http/Requests/Patient/StorePatientRequest.php',
      2 => '/var/www/html/app/Http/Resources/AppointmentResource.php',
      3 => '/var/www/html/app/Http/Resources/PatientResource.php',
      4 => '/var/www/html/app/Models/Appointment.php',
      5 => '/var/www/html/app/Policies/PatientPolicy.php',
      6 => '/var/www/html/app/Services/PatientService.php',
    ),
  ),
  '/var/www/html/app/Models/User.php' => 
  array (
    'fileHash' => '8536a7ab7e0d8d16ffae548c1329ecfec34ca8c9194704a9307a27cbf1ff1261',
    'dependentFiles' => 
    array (
      0 => '/var/www/html/app/Http/Controllers/Api/AuthController.php',
      1 => '/var/www/html/app/Http/Controllers/Api/DentistTimeOffController.php',
      2 => '/var/www/html/app/Http/Controllers/Api/DentistWorkingHourController.php',
      3 => '/var/www/html/app/Http/Controllers/Api/UserController.php',
      4 => '/var/www/html/app/Http/Requests/Appointment/CancelAppointmentRequest.php',
      5 => '/var/www/html/app/Http/Requests/Appointment/MarkNoShowAppointmentRequest.php',
      6 => '/var/www/html/app/Http/Requests/Appointment/StoreAppointmentRequest.php',
      7 => '/var/www/html/app/Http/Requests/Appointment/UpdateAppointmentRequest.php',
      8 => '/var/www/html/app/Http/Requests/AppointmentType/StoreAppointmentTypeRequest.php',
      9 => '/var/www/html/app/Http/Requests/AppointmentType/UpdateAppointmentTypeRequest.php',
      10 => '/var/www/html/app/Http/Requests/DentistTimeOff/StoreDentistTimeOffRequest.php',
      11 => '/var/www/html/app/Http/Requests/DentistWorkingHour/StoreDentistWorkingHourRequest.php',
      12 => '/var/www/html/app/Http/Requests/Patient/StorePatientRequest.php',
      13 => '/var/www/html/app/Http/Requests/Patient/UpdatePatientRequest.php',
      14 => '/var/www/html/app/Http/Requests/User/StoreUserRequest.php',
      15 => '/var/www/html/app/Http/Requests/User/UpdateUserRequest.php',
      16 => '/var/www/html/app/Http/Resources/AppointmentResource.php',
      17 => '/var/www/html/app/Http/Resources/AuditLogResource.php',
      18 => '/var/www/html/app/Http/Resources/UserResource.php',
      19 => '/var/www/html/app/Models/Appointment.php',
      20 => '/var/www/html/app/Models/AuditLog.php',
      21 => '/var/www/html/app/Models/DentistTimeOff.php',
      22 => '/var/www/html/app/Models/DentistWorkingHour.php',
      23 => '/var/www/html/app/Policies/AppointmentPolicy.php',
      24 => '/var/www/html/app/Policies/AppointmentTypePolicy.php',
      25 => '/var/www/html/app/Policies/DentistTimeOffPolicy.php',
      26 => '/var/www/html/app/Policies/DentistWorkingHourPolicy.php',
      27 => '/var/www/html/app/Policies/PatientPolicy.php',
      28 => '/var/www/html/app/Policies/UserPolicy.php',
      29 => '/var/www/html/app/Services/AuthService.php',
      30 => '/var/www/html/app/Services/DentistTimeOffService.php',
      31 => '/var/www/html/app/Services/DentistWorkingHourService.php',
      32 => '/var/www/html/app/Services/UserService.php',
    ),
  ),
  '/var/www/html/app/Observers/AuditObserver.php' => 
  array (
    'fileHash' => '04e3adc7ea8ff249529bbc2f9e4606c32e80d01902a677891d3a6a585e2c3d92',
    'dependentFiles' => 
    array (
      0 => '/var/www/html/app/Models/Appointment.php',
      1 => '/var/www/html/app/Models/Patient.php',
    ),
  ),
  '/var/www/html/app/Policies/AppointmentPolicy.php' => 
  array (
    'fileHash' => '2cea590ea89f6af5cf31b956d094ad5d49da92ac6430df36a133901d1c15dedd',
    'dependentFiles' => 
    array (
    ),
  ),
  '/var/www/html/app/Policies/AppointmentTypePolicy.php' => 
  array (
    'fileHash' => '312d9bb2ec5b784752536a4f1ab79b3d38e7d060288c0b15c9e1b03016df5070',
    'dependentFiles' => 
    array (
    ),
  ),
  '/var/www/html/app/Policies/DentistTimeOffPolicy.php' => 
  array (
    'fileHash' => 'a58f67930fd02b2d56e12d252c6ca64c07c4536db75d13aaab70680a7bb55a55',
    'dependentFiles' => 
    array (
    ),
  ),
  '/var/www/html/app/Policies/DentistWorkingHourPolicy.php' => 
  array (
    'fileHash' => '6b165c1204c33120b56100011092c81afcf1f616a7f1b5aac27185ee8085a7da',
    'dependentFiles' => 
    array (
    ),
  ),
  '/var/www/html/app/Policies/PatientPolicy.php' => 
  array (
    'fileHash' => '06b3ec459260ddeb2113e7731dd33d5f5466d77cf14eac3e12242ffe047b2654',
    'dependentFiles' => 
    array (
    ),
  ),
  '/var/www/html/app/Policies/UserPolicy.php' => 
  array (
    'fileHash' => '38ece57a0f2467e59e4db0aa7fd002fd5294150894235f538a69a2ee98da3dc8',
    'dependentFiles' => 
    array (
    ),
  ),
  '/var/www/html/app/Providers/AppServiceProvider.php' => 
  array (
    'fileHash' => '9a87c5fd028a43f1b7d803ca9911d3d2a5fc31f5344a8f26e8855b3b44f59839',
    'dependentFiles' => 
    array (
    ),
  ),
  '/var/www/html/app/Services/AppointmentService.php' => 
  array (
    'fileHash' => '916374a2dcb8702178c236525ba4b5559f77a24db6fd53675e2d33c5a187dce8',
    'dependentFiles' => 
    array (
      0 => '/var/www/html/app/Http/Controllers/Api/AppointmentController.php',
    ),
  ),
  '/var/www/html/app/Services/AppointmentTypeService.php' => 
  array (
    'fileHash' => '346228f590b77ed3faa48f0edaf2bf73031442c818993dad077b7c8013c4768e',
    'dependentFiles' => 
    array (
      0 => '/var/www/html/app/Http/Controllers/Api/AppointmentTypeController.php',
    ),
  ),
  '/var/www/html/app/Services/AuditLogService.php' => 
  array (
    'fileHash' => 'db683d31d7b7f8f4598ed3abd8a9b27e63af9fab5a542459a7ff0919bc3fbc43',
    'dependentFiles' => 
    array (
      0 => '/var/www/html/app/Observers/AuditObserver.php',
    ),
  ),
  '/var/www/html/app/Services/AuthService.php' => 
  array (
    'fileHash' => '7181473120e229036093b74effe76fe8f151d642a9d62d33f3c4ace26825a699',
    'dependentFiles' => 
    array (
      0 => '/var/www/html/app/Http/Controllers/Api/AuthController.php',
    ),
  ),
  '/var/www/html/app/Services/DashboardService.php' => 
  array (
    'fileHash' => 'e02bad8bcffac96a1852bfe8bd335bb5b5d496e233ea17a8801750348c82305e',
    'dependentFiles' => 
    array (
      0 => '/var/www/html/app/Http/Controllers/Api/DashboardController.php',
    ),
  ),
  '/var/www/html/app/Services/DentistTimeOffService.php' => 
  array (
    'fileHash' => '34a52134264e6d7a610e165567566b2d98fd7b3503f89db3581d08db169b6991',
    'dependentFiles' => 
    array (
      0 => '/var/www/html/app/Http/Controllers/Api/DentistTimeOffController.php',
    ),
  ),
  '/var/www/html/app/Services/DentistWorkingHourService.php' => 
  array (
    'fileHash' => '004fafafa7dbdf3c449d4886e2b8a82b4975a0b729c0f4e397098a36b803808f',
    'dependentFiles' => 
    array (
      0 => '/var/www/html/app/Http/Controllers/Api/DentistWorkingHourController.php',
    ),
  ),
  '/var/www/html/app/Services/PatientService.php' => 
  array (
    'fileHash' => 'a849d1a0c4fa12c6465f1b0c1dbb87e1ae6552389a02d3befc43d1c54d7f32d9',
    'dependentFiles' => 
    array (
      0 => '/var/www/html/app/Http/Controllers/Api/PatientController.php',
    ),
  ),
  '/var/www/html/app/Services/UserService.php' => 
  array (
    'fileHash' => '46351036f52297019ebcc7f52f65b37a7f103e2be8564f155a48dc006a0369d5',
    'dependentFiles' => 
    array (
      0 => '/var/www/html/app/Http/Controllers/Api/UserController.php',
    ),
  ),
),
	'packageDependencies' => array (
  '/var/www/html/app/Enums/UserRole.php' => 
  array (
  ),
  '/var/www/html/app/Http/Controllers/Api/DashboardController.php' => 
  array (
    0 => 'laravel/framework',
    1 => 'symfony/http-foundation',
  ),
  '/var/www/html/app/Http/Controllers/Api/UserController.php' => 
  array (
    0 => 'laravel/framework',
    1 => 'symfony/http-foundation',
  ),
  '/var/www/html/app/Http/Requests/Appointment/MarkNoShowAppointmentRequest.php' => 
  array (
    0 => 'laravel/framework',
    1 => 'symfony/http-foundation',
  ),
  '/var/www/html/app/Http/Requests/Appointment/UpdateAppointmentRequest.php' => 
  array (
    0 => 'laravel/framework',
    1 => 'symfony/http-foundation',
  ),
  '/var/www/html/app/Http/Requests/AppointmentType/UpdateAppointmentTypeRequest.php' => 
  array (
    0 => 'laravel/framework',
    1 => 'symfony/http-foundation',
  ),
  '/var/www/html/app/Http/Requests/Patient/UpdatePatientRequest.php' => 
  array (
    0 => 'laravel/framework',
    1 => 'symfony/http-foundation',
  ),
  '/var/www/html/app/Http/Requests/User/StoreUserRequest.php' => 
  array (
    0 => 'laravel/framework',
    1 => 'symfony/http-foundation',
  ),
  '/var/www/html/app/Http/Requests/User/UpdateUserRequest.php' => 
  array (
    0 => 'laravel/framework',
    1 => 'symfony/http-foundation',
  ),
  '/var/www/html/app/Models/Appointment.php' => 
  array (
    0 => 'laravel/framework',
  ),
  '/var/www/html/app/Models/AppointmentReminder.php' => 
  array (
    0 => 'laravel/framework',
  ),
  '/var/www/html/app/Models/AuditLog.php' => 
  array (
    0 => 'laravel/framework',
  ),
  '/var/www/html/app/Observers/AuditObserver.php' => 
  array (
    0 => 'laravel/framework',
  ),
  '/var/www/html/app/Policies/DentistTimeOffPolicy.php' => 
  array (
    0 => 'laravel/framework',
  ),
  '/var/www/html/app/Providers/AppServiceProvider.php' => 
  array (
    0 => 'laravel/framework',
  ),
  '/var/www/html/app/Services/UserService.php' => 
  array (
    0 => 'laravel/framework',
  ),
  '/var/www/html/app/Enums/AppointmentStatus.php' => 
  array (
  ),
  '/var/www/html/app/Enums/BloodType.php' => 
  array (
  ),
  '/var/www/html/app/Enums/PatientGender.php' => 
  array (
  ),
  '/var/www/html/app/Http/Controllers/Api/AuthController.php' => 
  array (
    0 => 'laravel/framework',
    1 => 'symfony/http-foundation',
  ),
  '/var/www/html/app/Http/Controllers/Api/PatientController.php' => 
  array (
    0 => 'laravel/framework',
    1 => 'symfony/http-foundation',
  ),
  '/var/www/html/app/Http/Requests/AppointmentType/StoreAppointmentTypeRequest.php' => 
  array (
    0 => 'laravel/framework',
    1 => 'symfony/http-foundation',
  ),
  '/var/www/html/app/Http/Requests/DentistWorkingHour/StoreDentistWorkingHourRequest.php' => 
  array (
    0 => 'laravel/framework',
    1 => 'symfony/http-foundation',
  ),
  '/var/www/html/app/Http/Resources/PatientResource.php' => 
  array (
    0 => 'laravel/framework',
    1 => 'symfony/http-foundation',
    2 => 'nesbot/carbon',
  ),
  '/var/www/html/app/Http/Resources/UserResource.php' => 
  array (
    0 => 'laravel/framework',
    1 => 'symfony/http-foundation',
  ),
  '/var/www/html/app/Models/Concerns/Auditable.php' => 
  array (
  ),
  '/var/www/html/app/Models/Patient.php' => 
  array (
    0 => 'laravel/framework',
  ),
  '/var/www/html/app/Policies/AppointmentPolicy.php' => 
  array (
    0 => 'laravel/framework',
  ),
  '/var/www/html/app/Policies/PatientPolicy.php' => 
  array (
    0 => 'laravel/framework',
  ),
  '/var/www/html/app/Policies/UserPolicy.php' => 
  array (
    0 => 'laravel/framework',
  ),
  '/var/www/html/app/Services/DashboardService.php' => 
  array (
    0 => 'laravel/framework',
  ),
  '/var/www/html/app/Services/PatientService.php' => 
  array (
    0 => 'laravel/framework',
  ),
  '/var/www/html/app/Http/Controllers/Controller.php' => 
  array (
    0 => 'laravel/framework',
  ),
  '/var/www/html/app/Http/Requests/Appointment/CancelAppointmentRequest.php' => 
  array (
    0 => 'laravel/framework',
    1 => 'symfony/http-foundation',
  ),
  '/var/www/html/app/Http/Requests/Appointment/StoreAppointmentRequest.php' => 
  array (
    0 => 'laravel/framework',
    1 => 'symfony/http-foundation',
  ),
  '/var/www/html/app/Http/Requests/Auth/LoginRequest.php' => 
  array (
    0 => 'laravel/framework',
    1 => 'symfony/http-foundation',
  ),
  '/var/www/html/app/Http/Requests/DentistTimeOff/StoreDentistTimeOffRequest.php' => 
  array (
    0 => 'laravel/framework',
    1 => 'symfony/http-foundation',
  ),
  '/var/www/html/app/Http/Requests/Patient/StorePatientRequest.php' => 
  array (
    0 => 'laravel/framework',
    1 => 'symfony/http-foundation',
  ),
  '/var/www/html/app/Http/Resources/AuditLogResource.php' => 
  array (
    0 => 'laravel/framework',
    1 => 'symfony/http-foundation',
    2 => 'nesbot/carbon',
  ),
  '/var/www/html/app/Models/AppointmentType.php' => 
  array (
    0 => 'laravel/framework',
  ),
  '/var/www/html/app/Models/DentistTimeOff.php' => 
  array (
    0 => 'laravel/framework',
  ),
  '/var/www/html/app/Models/DentistWorkingHour.php' => 
  array (
    0 => 'laravel/framework',
  ),
  '/var/www/html/app/Models/User.php' => 
  array (
    0 => 'laravel/framework',
  ),
  '/var/www/html/app/Policies/AppointmentTypePolicy.php' => 
  array (
    0 => 'laravel/framework',
  ),
  '/var/www/html/app/Policies/DentistWorkingHourPolicy.php' => 
  array (
    0 => 'laravel/framework',
  ),
  '/var/www/html/app/Services/AuditLogService.php' => 
  array (
    0 => 'laravel/framework',
    1 => 'symfony/http-foundation',
  ),
  '/var/www/html/app/Services/AuthService.php' => 
  array (
    0 => 'laravel/framework',
    1 => 'symfony/http-foundation',
  ),
  '/var/www/html/app/Exceptions/Appointments/DentistConflictException.php' => 
  array (
    0 => 'laravel/framework',
    1 => 'symfony/http-foundation',
  ),
  '/var/www/html/app/Exceptions/Appointments/EarlyNoShowException.php' => 
  array (
    0 => 'laravel/framework',
    1 => 'symfony/http-foundation',
  ),
  '/var/www/html/app/Exceptions/Appointments/InvalidStatusTransitionException.php' => 
  array (
    0 => 'laravel/framework',
    1 => 'symfony/http-foundation',
  ),
  '/var/www/html/app/Exceptions/Appointments/OutsideWorkingHoursException.php' => 
  array (
    0 => 'laravel/framework',
    1 => 'symfony/http-foundation',
  ),
  '/var/www/html/app/Exceptions/Appointments/PatientConflictException.php' => 
  array (
    0 => 'laravel/framework',
    1 => 'symfony/http-foundation',
  ),
  '/var/www/html/app/Services/AppointmentService.php' => 
  array (
    0 => 'nesbot/carbon',
    1 => 'laravel/framework',
    2 => 'symfony/http-foundation',
  ),
  '/var/www/html/app/Http/Controllers/Api/AppointmentController.php' => 
  array (
    0 => 'laravel/framework',
    1 => 'symfony/http-foundation',
    2 => 'nesbot/carbon',
  ),
  '/var/www/html/app/Http/Controllers/Api/AppointmentTypeController.php' => 
  array (
    0 => 'laravel/framework',
    1 => 'symfony/http-foundation',
  ),
  '/var/www/html/app/Http/Controllers/Api/DentistTimeOffController.php' => 
  array (
    0 => 'laravel/framework',
    1 => 'symfony/http-foundation',
  ),
  '/var/www/html/app/Http/Controllers/Api/DentistWorkingHourController.php' => 
  array (
    0 => 'laravel/framework',
    1 => 'symfony/http-foundation',
  ),
  '/var/www/html/app/Http/Requests/Appointment/AvailableSlotsRequest.php' => 
  array (
    0 => 'laravel/framework',
    1 => 'symfony/http-foundation',
  ),
  '/var/www/html/app/Http/Requests/Appointment/IndexAppointmentRequest.php' => 
  array (
    0 => 'laravel/framework',
    1 => 'symfony/http-foundation',
  ),
  '/var/www/html/app/Http/Resources/AppointmentResource.php' => 
  array (
    0 => 'laravel/framework',
    1 => 'symfony/http-foundation',
    2 => 'nesbot/carbon',
  ),
  '/var/www/html/app/Http/Resources/AppointmentTypeResource.php' => 
  array (
    0 => 'laravel/framework',
    1 => 'symfony/http-foundation',
    2 => 'nesbot/carbon',
  ),
  '/var/www/html/app/Http/Resources/DentistTimeOffResource.php' => 
  array (
    0 => 'laravel/framework',
    1 => 'symfony/http-foundation',
    2 => 'nesbot/carbon',
  ),
  '/var/www/html/app/Http/Resources/DentistWorkingHourResource.php' => 
  array (
    0 => 'laravel/framework',
    1 => 'symfony/http-foundation',
  ),
  '/var/www/html/app/Services/AppointmentTypeService.php' => 
  array (
    0 => 'laravel/framework',
  ),
  '/var/www/html/app/Services/DentistTimeOffService.php' => 
  array (
    0 => 'laravel/framework',
  ),
  '/var/www/html/app/Services/DentistWorkingHourService.php' => 
  array (
    0 => 'laravel/framework',
  ),
),
	'exportedNodesCallback' => static function (): array { return array (
  '/var/www/html/app/Enums/AppointmentStatus.php' => 
  array (
    0 => 
    \PHPStan\Dependency\ExportedNode\ExportedEnumNode::__set_state(array(
       'name' => 'App\\Enums\\AppointmentStatus',
       'scalarType' => 'string',
       'phpDoc' => NULL,
       'implements' => 
      array (
      ),
       'statements' => 
      array (
        0 => 
        \PHPStan\Dependency\ExportedNode\ExportedEnumCaseNode::__set_state(array(
           'name' => 'Scheduled',
           'value' => '\'scheduled\'',
           'phpDoc' => NULL,
        )),
        1 => 
        \PHPStan\Dependency\ExportedNode\ExportedEnumCaseNode::__set_state(array(
           'name' => 'Confirmed',
           'value' => '\'confirmed\'',
           'phpDoc' => NULL,
        )),
        2 => 
        \PHPStan\Dependency\ExportedNode\ExportedEnumCaseNode::__set_state(array(
           'name' => 'CheckedIn',
           'value' => '\'checked_in\'',
           'phpDoc' => NULL,
        )),
        3 => 
        \PHPStan\Dependency\ExportedNode\ExportedEnumCaseNode::__set_state(array(
           'name' => 'InProgress',
           'value' => '\'in_progress\'',
           'phpDoc' => NULL,
        )),
        4 => 
        \PHPStan\Dependency\ExportedNode\ExportedEnumCaseNode::__set_state(array(
           'name' => 'Completed',
           'value' => '\'completed\'',
           'phpDoc' => NULL,
        )),
        5 => 
        \PHPStan\Dependency\ExportedNode\ExportedEnumCaseNode::__set_state(array(
           'name' => 'Cancelled',
           'value' => '\'cancelled\'',
           'phpDoc' => NULL,
        )),
        6 => 
        \PHPStan\Dependency\ExportedNode\ExportedEnumCaseNode::__set_state(array(
           'name' => 'NoShow',
           'value' => '\'no_show\'',
           'phpDoc' => NULL,
        )),
        7 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'occupyingStatuses',
           'phpDoc' => 
          \PHPStan\Dependency\ExportedNode\ExportedPhpDocNode::__set_state(array(
             'phpDocString' => '/**
     * Statuses that occupy the dentist\'s time slot. Mirrors the status list hardcoded in the
     * appointments_no_overlapping_dentist_slots Postgres EXCLUDE constraint migration.
     *
     * @return list<self>
     */',
             'namespace' => 'App\\Enums',
             'uses' => 
            array (
            ),
             'constUses' => 
            array (
            ),
          )),
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => true,
           'returnType' => 'array',
           'parameters' => 
          array (
          ),
           'attributes' => 
          array (
          ),
        )),
        8 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'occupiesTime',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'bool',
           'parameters' => 
          array (
          ),
           'attributes' => 
          array (
          ),
        )),
      ),
       'attributes' => 
      array (
      ),
    )),
  ),
  '/var/www/html/app/Enums/BloodType.php' => 
  array (
    0 => 
    \PHPStan\Dependency\ExportedNode\ExportedEnumNode::__set_state(array(
       'name' => 'App\\Enums\\BloodType',
       'scalarType' => 'string',
       'phpDoc' => NULL,
       'implements' => 
      array (
      ),
       'statements' => 
      array (
        0 => 
        \PHPStan\Dependency\ExportedNode\ExportedEnumCaseNode::__set_state(array(
           'name' => 'APositive',
           'value' => '\'A+\'',
           'phpDoc' => NULL,
        )),
        1 => 
        \PHPStan\Dependency\ExportedNode\ExportedEnumCaseNode::__set_state(array(
           'name' => 'ANegative',
           'value' => '\'A-\'',
           'phpDoc' => NULL,
        )),
        2 => 
        \PHPStan\Dependency\ExportedNode\ExportedEnumCaseNode::__set_state(array(
           'name' => 'BPositive',
           'value' => '\'B+\'',
           'phpDoc' => NULL,
        )),
        3 => 
        \PHPStan\Dependency\ExportedNode\ExportedEnumCaseNode::__set_state(array(
           'name' => 'BNegative',
           'value' => '\'B-\'',
           'phpDoc' => NULL,
        )),
        4 => 
        \PHPStan\Dependency\ExportedNode\ExportedEnumCaseNode::__set_state(array(
           'name' => 'ABPositive',
           'value' => '\'AB+\'',
           'phpDoc' => NULL,
        )),
        5 => 
        \PHPStan\Dependency\ExportedNode\ExportedEnumCaseNode::__set_state(array(
           'name' => 'ABNegative',
           'value' => '\'AB-\'',
           'phpDoc' => NULL,
        )),
        6 => 
        \PHPStan\Dependency\ExportedNode\ExportedEnumCaseNode::__set_state(array(
           'name' => 'OPositive',
           'value' => '\'O+\'',
           'phpDoc' => NULL,
        )),
        7 => 
        \PHPStan\Dependency\ExportedNode\ExportedEnumCaseNode::__set_state(array(
           'name' => 'ONegative',
           'value' => '\'O-\'',
           'phpDoc' => NULL,
        )),
      ),
       'attributes' => 
      array (
      ),
    )),
  ),
  '/var/www/html/app/Enums/PatientGender.php' => 
  array (
    0 => 
    \PHPStan\Dependency\ExportedNode\ExportedEnumNode::__set_state(array(
       'name' => 'App\\Enums\\PatientGender',
       'scalarType' => 'string',
       'phpDoc' => NULL,
       'implements' => 
      array (
      ),
       'statements' => 
      array (
        0 => 
        \PHPStan\Dependency\ExportedNode\ExportedEnumCaseNode::__set_state(array(
           'name' => 'Male',
           'value' => '\'male\'',
           'phpDoc' => NULL,
        )),
        1 => 
        \PHPStan\Dependency\ExportedNode\ExportedEnumCaseNode::__set_state(array(
           'name' => 'Female',
           'value' => '\'female\'',
           'phpDoc' => NULL,
        )),
      ),
       'attributes' => 
      array (
      ),
    )),
  ),
  '/var/www/html/app/Enums/UserRole.php' => 
  array (
    0 => 
    \PHPStan\Dependency\ExportedNode\ExportedEnumNode::__set_state(array(
       'name' => 'App\\Enums\\UserRole',
       'scalarType' => 'string',
       'phpDoc' => NULL,
       'implements' => 
      array (
      ),
       'statements' => 
      array (
        0 => 
        \PHPStan\Dependency\ExportedNode\ExportedEnumCaseNode::__set_state(array(
           'name' => 'Admin',
           'value' => '\'admin\'',
           'phpDoc' => NULL,
        )),
        1 => 
        \PHPStan\Dependency\ExportedNode\ExportedEnumCaseNode::__set_state(array(
           'name' => 'Dentist',
           'value' => '\'dentist\'',
           'phpDoc' => NULL,
        )),
        2 => 
        \PHPStan\Dependency\ExportedNode\ExportedEnumCaseNode::__set_state(array(
           'name' => 'Receptionist',
           'value' => '\'receptionist\'',
           'phpDoc' => NULL,
        )),
      ),
       'attributes' => 
      array (
      ),
    )),
  ),
  '/var/www/html/app/Exceptions/Appointments/DentistConflictException.php' => 
  array (
    0 => 
    \PHPStan\Dependency\ExportedNode\ExportedClassNode::__set_state(array(
       'name' => 'App\\Exceptions\\Appointments\\DentistConflictException',
       'phpDoc' => 
      \PHPStan\Dependency\ExportedNode\ExportedPhpDocNode::__set_state(array(
         'phpDocString' => '/**
 * Hard block, never overridable (design doc §5.3, §10): the dentist already has an
 * overlapping, time-occupying appointment.
 */',
         'namespace' => 'App\\Exceptions\\Appointments',
         'uses' => 
        array (
          'jsonresponse' => 'Illuminate\\Http\\JsonResponse',
          'request' => 'Illuminate\\Http\\Request',
          'runtimeexception' => 'RuntimeException',
        ),
         'constUses' => 
        array (
        ),
      )),
       'abstract' => false,
       'final' => false,
       'extends' => 'RuntimeException',
       'implements' => 
      array (
      ),
       'usedTraits' => 
      array (
      ),
       'traitUseAdaptations' => 
      array (
      ),
       'statements' => 
      array (
        0 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => '__construct',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => NULL,
           'parameters' => 
          array (
          ),
           'attributes' => 
          array (
          ),
        )),
        1 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'render',
           'phpDoc' => 
          \PHPStan\Dependency\ExportedNode\ExportedPhpDocNode::__set_state(array(
             'phpDocString' => '/**
     * 409 Conflict — framed the same way as PatientConflictException (both describe an
     * overlapping-appointment conflict), but with no `overridable` key: this one is a hard
     * block, so the frontend has nothing to offer the user except picking a different slot.
     */',
             'namespace' => 'App\\Exceptions\\Appointments',
             'uses' => 
            array (
              'jsonresponse' => 'Illuminate\\Http\\JsonResponse',
              'request' => 'Illuminate\\Http\\Request',
              'runtimeexception' => 'RuntimeException',
            ),
             'constUses' => 
            array (
            ),
          )),
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'Illuminate\\Http\\JsonResponse',
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'request',
               'type' => 'Illuminate\\Http\\Request',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
      ),
       'attributes' => 
      array (
      ),
    )),
  ),
  '/var/www/html/app/Exceptions/Appointments/EarlyNoShowException.php' => 
  array (
    0 => 
    \PHPStan\Dependency\ExportedNode\ExportedClassNode::__set_state(array(
       'name' => 'App\\Exceptions\\Appointments\\EarlyNoShowException',
       'phpDoc' => 
      \PHPStan\Dependency\ExportedNode\ExportedPhpDocNode::__set_state(array(
         'phpDocString' => '/**
 * Soft warning (design doc §5.9/§13): marking no-show before `start_at` has passed is
 * unusual but allowed for staff backfilling records. Overridable via `override_early_no_show`.
 */',
         'namespace' => 'App\\Exceptions\\Appointments',
         'uses' => 
        array (
          'jsonresponse' => 'Illuminate\\Http\\JsonResponse',
          'request' => 'Illuminate\\Http\\Request',
          'runtimeexception' => 'RuntimeException',
        ),
         'constUses' => 
        array (
        ),
      )),
       'abstract' => false,
       'final' => false,
       'extends' => 'RuntimeException',
       'implements' => 
      array (
      ),
       'usedTraits' => 
      array (
      ),
       'traitUseAdaptations' => 
      array (
      ),
       'statements' => 
      array (
        0 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => '__construct',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => NULL,
           'parameters' => 
          array (
          ),
           'attributes' => 
          array (
          ),
        )),
        1 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'render',
           'phpDoc' => 
          \PHPStan\Dependency\ExportedNode\ExportedPhpDocNode::__set_state(array(
             'phpDocString' => '/**
     * 422 — a business-rule validation failure on the request as submitted.
     */',
             'namespace' => 'App\\Exceptions\\Appointments',
             'uses' => 
            array (
              'jsonresponse' => 'Illuminate\\Http\\JsonResponse',
              'request' => 'Illuminate\\Http\\Request',
              'runtimeexception' => 'RuntimeException',
            ),
             'constUses' => 
            array (
            ),
          )),
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'Illuminate\\Http\\JsonResponse',
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'request',
               'type' => 'Illuminate\\Http\\Request',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
      ),
       'attributes' => 
      array (
      ),
    )),
  ),
  '/var/www/html/app/Exceptions/Appointments/InvalidStatusTransitionException.php' => 
  array (
    0 => 
    \PHPStan\Dependency\ExportedNode\ExportedClassNode::__set_state(array(
       'name' => 'App\\Exceptions\\Appointments\\InvalidStatusTransitionException',
       'phpDoc' => 
      \PHPStan\Dependency\ExportedNode\ExportedPhpDocNode::__set_state(array(
         'phpDocString' => '/**
 * Enforces the state machine in design doc §3 — never trust status changes from the client.
 */',
         'namespace' => 'App\\Exceptions\\Appointments',
         'uses' => 
        array (
          'appointmentstatus' => 'App\\Enums\\AppointmentStatus',
          'jsonresponse' => 'Illuminate\\Http\\JsonResponse',
          'request' => 'Illuminate\\Http\\Request',
          'runtimeexception' => 'RuntimeException',
        ),
         'constUses' => 
        array (
        ),
      )),
       'abstract' => false,
       'final' => false,
       'extends' => 'RuntimeException',
       'implements' => 
      array (
      ),
       'usedTraits' => 
      array (
      ),
       'traitUseAdaptations' => 
      array (
      ),
       'statements' => 
      array (
        0 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => '__construct',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => NULL,
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'from',
               'type' => 'App\\Enums\\AppointmentStatus',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
            1 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'to',
               'type' => 'App\\Enums\\AppointmentStatus',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
        1 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'render',
           'phpDoc' => 
          \PHPStan\Dependency\ExportedNode\ExportedPhpDocNode::__set_state(array(
             'phpDocString' => '/**
     * 422 — a business-rule validation failure on the request as submitted. No `overridable`
     * key: an invalid transition isn\'t something the frontend can force past, unlike the soft
     * warnings above.
     */',
             'namespace' => 'App\\Exceptions\\Appointments',
             'uses' => 
            array (
              'appointmentstatus' => 'App\\Enums\\AppointmentStatus',
              'jsonresponse' => 'Illuminate\\Http\\JsonResponse',
              'request' => 'Illuminate\\Http\\Request',
              'runtimeexception' => 'RuntimeException',
            ),
             'constUses' => 
            array (
            ),
          )),
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'Illuminate\\Http\\JsonResponse',
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'request',
               'type' => 'Illuminate\\Http\\Request',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
      ),
       'attributes' => 
      array (
      ),
    )),
  ),
  '/var/www/html/app/Exceptions/Appointments/OutsideWorkingHoursException.php' => 
  array (
    0 => 
    \PHPStan\Dependency\ExportedNode\ExportedClassNode::__set_state(array(
       'name' => 'App\\Exceptions\\Appointments\\OutsideWorkingHoursException',
       'phpDoc' => 
      \PHPStan\Dependency\ExportedNode\ExportedPhpDocNode::__set_state(array(
         'phpDocString' => '/**
 * Soft warning (design doc §5.5): the requested slot falls outside the dentist\'s working
 * hours or during their time-off. Overridable via `override_outside_working_hours`.
 */',
         'namespace' => 'App\\Exceptions\\Appointments',
         'uses' => 
        array (
          'jsonresponse' => 'Illuminate\\Http\\JsonResponse',
          'request' => 'Illuminate\\Http\\Request',
          'runtimeexception' => 'RuntimeException',
        ),
         'constUses' => 
        array (
        ),
      )),
       'abstract' => false,
       'final' => false,
       'extends' => 'RuntimeException',
       'implements' => 
      array (
      ),
       'usedTraits' => 
      array (
      ),
       'traitUseAdaptations' => 
      array (
      ),
       'statements' => 
      array (
        0 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => '__construct',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => NULL,
           'parameters' => 
          array (
          ),
           'attributes' => 
          array (
          ),
        )),
        1 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'render',
           'phpDoc' => 
          \PHPStan\Dependency\ExportedNode\ExportedPhpDocNode::__set_state(array(
             'phpDocString' => '/**
     * 422 — a business-rule validation failure on the request as submitted (not a resource
     * conflict), same family as EarlyNoShowException/InvalidStatusTransitionException.
     */',
             'namespace' => 'App\\Exceptions\\Appointments',
             'uses' => 
            array (
              'jsonresponse' => 'Illuminate\\Http\\JsonResponse',
              'request' => 'Illuminate\\Http\\Request',
              'runtimeexception' => 'RuntimeException',
            ),
             'constUses' => 
            array (
            ),
          )),
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'Illuminate\\Http\\JsonResponse',
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'request',
               'type' => 'Illuminate\\Http\\Request',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
      ),
       'attributes' => 
      array (
      ),
    )),
  ),
  '/var/www/html/app/Exceptions/Appointments/PatientConflictException.php' => 
  array (
    0 => 
    \PHPStan\Dependency\ExportedNode\ExportedClassNode::__set_state(array(
       'name' => 'App\\Exceptions\\Appointments\\PatientConflictException',
       'phpDoc' => 
      \PHPStan\Dependency\ExportedNode\ExportedPhpDocNode::__set_state(array(
         'phpDocString' => '/**
 * Soft warning (design doc §5.4): the patient already has a genuinely overlapping
 * appointment (not just another one the same day). Overridable via `override_patient_conflict`.
 */',
         'namespace' => 'App\\Exceptions\\Appointments',
         'uses' => 
        array (
          'jsonresponse' => 'Illuminate\\Http\\JsonResponse',
          'request' => 'Illuminate\\Http\\Request',
          'runtimeexception' => 'RuntimeException',
        ),
         'constUses' => 
        array (
        ),
      )),
       'abstract' => false,
       'final' => false,
       'extends' => 'RuntimeException',
       'implements' => 
      array (
      ),
       'usedTraits' => 
      array (
      ),
       'traitUseAdaptations' => 
      array (
      ),
       'statements' => 
      array (
        0 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => '__construct',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => NULL,
           'parameters' => 
          array (
          ),
           'attributes' => 
          array (
          ),
        )),
        1 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'render',
           'phpDoc' => 
          \PHPStan\Dependency\ExportedNode\ExportedPhpDocNode::__set_state(array(
             'phpDocString' => '/**
     * 409 Conflict — same status family as DentistConflictException (both describe an
     * overlapping-appointment conflict), but `overridable`/`override_field` let the frontend
     * distinguish this soft warning and offer a "Book Anyway" confirmation.
     */',
             'namespace' => 'App\\Exceptions\\Appointments',
             'uses' => 
            array (
              'jsonresponse' => 'Illuminate\\Http\\JsonResponse',
              'request' => 'Illuminate\\Http\\Request',
              'runtimeexception' => 'RuntimeException',
            ),
             'constUses' => 
            array (
            ),
          )),
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'Illuminate\\Http\\JsonResponse',
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'request',
               'type' => 'Illuminate\\Http\\Request',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
      ),
       'attributes' => 
      array (
      ),
    )),
  ),
  '/var/www/html/app/Http/Controllers/Api/AppointmentController.php' => 
  array (
    0 => 
    \PHPStan\Dependency\ExportedNode\ExportedClassNode::__set_state(array(
       'name' => 'App\\Http\\Controllers\\Api\\AppointmentController',
       'phpDoc' => NULL,
       'abstract' => false,
       'final' => false,
       'extends' => 'App\\Http\\Controllers\\Controller',
       'implements' => 
      array (
      ),
       'usedTraits' => 
      array (
      ),
       'traitUseAdaptations' => 
      array (
      ),
       'statements' => 
      array (
        0 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => '__construct',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => NULL,
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'appointmentService',
               'type' => 'App\\Services\\AppointmentService',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 4,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
        1 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'index',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => NULL,
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'request',
               'type' => 'App\\Http\\Requests\\Appointment\\IndexAppointmentRequest',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
        2 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'store',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => NULL,
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'request',
               'type' => 'App\\Http\\Requests\\Appointment\\StoreAppointmentRequest',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
        3 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'show',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => NULL,
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'appointment',
               'type' => 'App\\Models\\Appointment',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
        4 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'update',
           'phpDoc' => 
          \PHPStan\Dependency\ExportedNode\ExportedPhpDocNode::__set_state(array(
             'phpDocString' => '/**
     * Services both plain field edits and in-place reschedules (design doc §11/§16) — there is
     * no separate reschedule endpoint.
     */',
             'namespace' => 'App\\Http\\Controllers\\Api',
             'uses' => 
            array (
              'controller' => 'App\\Http\\Controllers\\Controller',
              'availableslotsrequest' => 'App\\Http\\Requests\\Appointment\\AvailableSlotsRequest',
              'cancelappointmentrequest' => 'App\\Http\\Requests\\Appointment\\CancelAppointmentRequest',
              'indexappointmentrequest' => 'App\\Http\\Requests\\Appointment\\IndexAppointmentRequest',
              'marknoshowappointmentrequest' => 'App\\Http\\Requests\\Appointment\\MarkNoShowAppointmentRequest',
              'storeappointmentrequest' => 'App\\Http\\Requests\\Appointment\\StoreAppointmentRequest',
              'updateappointmentrequest' => 'App\\Http\\Requests\\Appointment\\UpdateAppointmentRequest',
              'appointmentresource' => 'App\\Http\\Resources\\AppointmentResource',
              'appointment' => 'App\\Models\\Appointment',
              'appointmentservice' => 'App\\Services\\AppointmentService',
              'carbon' => 'Carbon\\Carbon',
            ),
             'constUses' => 
            array (
            ),
          )),
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => NULL,
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'request',
               'type' => 'App\\Http\\Requests\\Appointment\\UpdateAppointmentRequest',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
            1 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'appointment',
               'type' => 'App\\Models\\Appointment',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
        5 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'destroy',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => NULL,
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'appointment',
               'type' => 'App\\Models\\Appointment',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
        6 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'cancel',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => NULL,
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'request',
               'type' => 'App\\Http\\Requests\\Appointment\\CancelAppointmentRequest',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
            1 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'appointment',
               'type' => 'App\\Models\\Appointment',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
        7 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'noShow',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => NULL,
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'request',
               'type' => 'App\\Http\\Requests\\Appointment\\MarkNoShowAppointmentRequest',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
            1 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'appointment',
               'type' => 'App\\Models\\Appointment',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
        8 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'confirm',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => NULL,
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'appointment',
               'type' => 'App\\Models\\Appointment',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
        9 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'checkIn',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => NULL,
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'appointment',
               'type' => 'App\\Models\\Appointment',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
        10 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'start',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => NULL,
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'appointment',
               'type' => 'App\\Models\\Appointment',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
        11 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'complete',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => NULL,
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'appointment',
               'type' => 'App\\Models\\Appointment',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
        12 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'availableSlots',
           'phpDoc' => 
          \PHPStan\Dependency\ExportedNode\ExportedPhpDocNode::__set_state(array(
             'phpDocString' => '/**
     * Top-level route (design doc §16), not nested under `/api/appointments/`.
     */',
             'namespace' => 'App\\Http\\Controllers\\Api',
             'uses' => 
            array (
              'controller' => 'App\\Http\\Controllers\\Controller',
              'availableslotsrequest' => 'App\\Http\\Requests\\Appointment\\AvailableSlotsRequest',
              'cancelappointmentrequest' => 'App\\Http\\Requests\\Appointment\\CancelAppointmentRequest',
              'indexappointmentrequest' => 'App\\Http\\Requests\\Appointment\\IndexAppointmentRequest',
              'marknoshowappointmentrequest' => 'App\\Http\\Requests\\Appointment\\MarkNoShowAppointmentRequest',
              'storeappointmentrequest' => 'App\\Http\\Requests\\Appointment\\StoreAppointmentRequest',
              'updateappointmentrequest' => 'App\\Http\\Requests\\Appointment\\UpdateAppointmentRequest',
              'appointmentresource' => 'App\\Http\\Resources\\AppointmentResource',
              'appointment' => 'App\\Models\\Appointment',
              'appointmentservice' => 'App\\Services\\AppointmentService',
              'carbon' => 'Carbon\\Carbon',
            ),
             'constUses' => 
            array (
            ),
          )),
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => NULL,
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'request',
               'type' => 'App\\Http\\Requests\\Appointment\\AvailableSlotsRequest',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
      ),
       'attributes' => 
      array (
      ),
    )),
  ),
  '/var/www/html/app/Http/Controllers/Api/AppointmentTypeController.php' => 
  array (
    0 => 
    \PHPStan\Dependency\ExportedNode\ExportedClassNode::__set_state(array(
       'name' => 'App\\Http\\Controllers\\Api\\AppointmentTypeController',
       'phpDoc' => NULL,
       'abstract' => false,
       'final' => false,
       'extends' => 'App\\Http\\Controllers\\Controller',
       'implements' => 
      array (
      ),
       'usedTraits' => 
      array (
      ),
       'traitUseAdaptations' => 
      array (
      ),
       'statements' => 
      array (
        0 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => '__construct',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => NULL,
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'appointmentTypeService',
               'type' => 'App\\Services\\AppointmentTypeService',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 4,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
        1 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'index',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => NULL,
           'parameters' => 
          array (
          ),
           'attributes' => 
          array (
          ),
        )),
        2 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'store',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => NULL,
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'request',
               'type' => 'App\\Http\\Requests\\AppointmentType\\StoreAppointmentTypeRequest',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
        3 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'show',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => NULL,
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'appointmentType',
               'type' => 'App\\Models\\AppointmentType',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
        4 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'update',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => NULL,
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'request',
               'type' => 'App\\Http\\Requests\\AppointmentType\\UpdateAppointmentTypeRequest',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
            1 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'appointmentType',
               'type' => 'App\\Models\\AppointmentType',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
        5 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'destroy',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => NULL,
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'appointmentType',
               'type' => 'App\\Models\\AppointmentType',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
      ),
       'attributes' => 
      array (
      ),
    )),
  ),
  '/var/www/html/app/Http/Controllers/Api/AuthController.php' => 
  array (
    0 => 
    \PHPStan\Dependency\ExportedNode\ExportedClassNode::__set_state(array(
       'name' => 'App\\Http\\Controllers\\Api\\AuthController',
       'phpDoc' => NULL,
       'abstract' => false,
       'final' => false,
       'extends' => 'App\\Http\\Controllers\\Controller',
       'implements' => 
      array (
      ),
       'usedTraits' => 
      array (
      ),
       'traitUseAdaptations' => 
      array (
      ),
       'statements' => 
      array (
        0 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => '__construct',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => NULL,
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'authService',
               'type' => 'App\\Services\\AuthService',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 4,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
        1 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'login',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => NULL,
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'request',
               'type' => 'App\\Http\\Requests\\Auth\\LoginRequest',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
        2 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'logout',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => NULL,
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'request',
               'type' => 'Illuminate\\Http\\Request',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
        3 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'user',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => NULL,
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'request',
               'type' => 'Illuminate\\Http\\Request',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
      ),
       'attributes' => 
      array (
      ),
    )),
  ),
  '/var/www/html/app/Http/Controllers/Api/DashboardController.php' => 
  array (
    0 => 
    \PHPStan\Dependency\ExportedNode\ExportedClassNode::__set_state(array(
       'name' => 'App\\Http\\Controllers\\Api\\DashboardController',
       'phpDoc' => NULL,
       'abstract' => false,
       'final' => false,
       'extends' => 'App\\Http\\Controllers\\Controller',
       'implements' => 
      array (
      ),
       'usedTraits' => 
      array (
      ),
       'traitUseAdaptations' => 
      array (
      ),
       'statements' => 
      array (
        0 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => '__construct',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => NULL,
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'dashboardService',
               'type' => 'App\\Services\\DashboardService',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 4,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
        1 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'summary',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => NULL,
           'parameters' => 
          array (
          ),
           'attributes' => 
          array (
          ),
        )),
      ),
       'attributes' => 
      array (
      ),
    )),
  ),
  '/var/www/html/app/Http/Controllers/Api/DentistTimeOffController.php' => 
  array (
    0 => 
    \PHPStan\Dependency\ExportedNode\ExportedClassNode::__set_state(array(
       'name' => 'App\\Http\\Controllers\\Api\\DentistTimeOffController',
       'phpDoc' => NULL,
       'abstract' => false,
       'final' => false,
       'extends' => 'App\\Http\\Controllers\\Controller',
       'implements' => 
      array (
      ),
       'usedTraits' => 
      array (
      ),
       'traitUseAdaptations' => 
      array (
      ),
       'statements' => 
      array (
        0 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => '__construct',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => NULL,
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'timeOffService',
               'type' => 'App\\Services\\DentistTimeOffService',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 4,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
        1 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'index',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => NULL,
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'user',
               'type' => 'App\\Models\\User',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
        2 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'store',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => NULL,
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'request',
               'type' => 'App\\Http\\Requests\\DentistTimeOff\\StoreDentistTimeOffRequest',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
            1 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'user',
               'type' => 'App\\Models\\User',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
        3 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'destroy',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => NULL,
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'user',
               'type' => 'App\\Models\\User',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
            1 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'timeOff',
               'type' => 'App\\Models\\DentistTimeOff',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
      ),
       'attributes' => 
      array (
      ),
    )),
  ),
  '/var/www/html/app/Http/Controllers/Api/DentistWorkingHourController.php' => 
  array (
    0 => 
    \PHPStan\Dependency\ExportedNode\ExportedClassNode::__set_state(array(
       'name' => 'App\\Http\\Controllers\\Api\\DentistWorkingHourController',
       'phpDoc' => NULL,
       'abstract' => false,
       'final' => false,
       'extends' => 'App\\Http\\Controllers\\Controller',
       'implements' => 
      array (
      ),
       'usedTraits' => 
      array (
      ),
       'traitUseAdaptations' => 
      array (
      ),
       'statements' => 
      array (
        0 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => '__construct',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => NULL,
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'workingHourService',
               'type' => 'App\\Services\\DentistWorkingHourService',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 4,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
        1 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'index',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => NULL,
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'user',
               'type' => 'App\\Models\\User',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
        2 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'store',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => NULL,
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'request',
               'type' => 'App\\Http\\Requests\\DentistWorkingHour\\StoreDentistWorkingHourRequest',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
            1 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'user',
               'type' => 'App\\Models\\User',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
        3 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'destroy',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => NULL,
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'user',
               'type' => 'App\\Models\\User',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
            1 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'workingHour',
               'type' => 'App\\Models\\DentistWorkingHour',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
      ),
       'attributes' => 
      array (
      ),
    )),
  ),
  '/var/www/html/app/Http/Controllers/Api/PatientController.php' => 
  array (
    0 => 
    \PHPStan\Dependency\ExportedNode\ExportedClassNode::__set_state(array(
       'name' => 'App\\Http\\Controllers\\Api\\PatientController',
       'phpDoc' => NULL,
       'abstract' => false,
       'final' => false,
       'extends' => 'App\\Http\\Controllers\\Controller',
       'implements' => 
      array (
      ),
       'usedTraits' => 
      array (
      ),
       'traitUseAdaptations' => 
      array (
      ),
       'statements' => 
      array (
        0 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => '__construct',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => NULL,
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'patientService',
               'type' => 'App\\Services\\PatientService',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 4,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
        1 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'index',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => NULL,
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'request',
               'type' => 'Illuminate\\Http\\Request',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
        2 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'store',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => NULL,
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'request',
               'type' => 'App\\Http\\Requests\\Patient\\StorePatientRequest',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
        3 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'show',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => NULL,
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'patient',
               'type' => 'App\\Models\\Patient',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
        4 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'update',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => NULL,
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'request',
               'type' => 'App\\Http\\Requests\\Patient\\UpdatePatientRequest',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
            1 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'patient',
               'type' => 'App\\Models\\Patient',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
        5 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'destroy',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => NULL,
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'patient',
               'type' => 'App\\Models\\Patient',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
        6 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'auditLogs',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => NULL,
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'patient',
               'type' => 'App\\Models\\Patient',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
      ),
       'attributes' => 
      array (
      ),
    )),
  ),
  '/var/www/html/app/Http/Controllers/Api/UserController.php' => 
  array (
    0 => 
    \PHPStan\Dependency\ExportedNode\ExportedClassNode::__set_state(array(
       'name' => 'App\\Http\\Controllers\\Api\\UserController',
       'phpDoc' => NULL,
       'abstract' => false,
       'final' => false,
       'extends' => 'App\\Http\\Controllers\\Controller',
       'implements' => 
      array (
      ),
       'usedTraits' => 
      array (
      ),
       'traitUseAdaptations' => 
      array (
      ),
       'statements' => 
      array (
        0 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => '__construct',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => NULL,
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'userService',
               'type' => 'App\\Services\\UserService',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 4,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
        1 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'index',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => NULL,
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'request',
               'type' => 'Illuminate\\Http\\Request',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
        2 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'store',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => NULL,
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'request',
               'type' => 'App\\Http\\Requests\\User\\StoreUserRequest',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
        3 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'show',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => NULL,
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'user',
               'type' => 'App\\Models\\User',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
        4 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'update',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => NULL,
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'request',
               'type' => 'App\\Http\\Requests\\User\\UpdateUserRequest',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
            1 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'user',
               'type' => 'App\\Models\\User',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
        5 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'destroy',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => NULL,
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'user',
               'type' => 'App\\Models\\User',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
      ),
       'attributes' => 
      array (
      ),
    )),
  ),
  '/var/www/html/app/Http/Controllers/Controller.php' => 
  array (
    0 => 
    \PHPStan\Dependency\ExportedNode\ExportedClassNode::__set_state(array(
       'name' => 'App\\Http\\Controllers\\Controller',
       'phpDoc' => NULL,
       'abstract' => true,
       'final' => false,
       'extends' => NULL,
       'implements' => 
      array (
      ),
       'usedTraits' => 
      array (
        0 => 'Illuminate\\Foundation\\Auth\\Access\\AuthorizesRequests',
      ),
       'traitUseAdaptations' => 
      array (
      ),
       'statements' => 
      array (
      ),
       'attributes' => 
      array (
      ),
    )),
  ),
  '/var/www/html/app/Http/Requests/Appointment/AvailableSlotsRequest.php' => 
  array (
    0 => 
    \PHPStan\Dependency\ExportedNode\ExportedClassNode::__set_state(array(
       'name' => 'App\\Http\\Requests\\Appointment\\AvailableSlotsRequest',
       'phpDoc' => NULL,
       'abstract' => false,
       'final' => false,
       'extends' => 'Illuminate\\Foundation\\Http\\FormRequest',
       'implements' => 
      array (
      ),
       'usedTraits' => 
      array (
      ),
       'traitUseAdaptations' => 
      array (
      ),
       'statements' => 
      array (
        0 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'authorize',
           'phpDoc' => 
          \PHPStan\Dependency\ExportedNode\ExportedPhpDocNode::__set_state(array(
             'phpDocString' => '/**
     * Open to any authenticated role — reveals dentist schedule gaps only, not sensitive
     * (design doc §18).
     */',
             'namespace' => 'App\\Http\\Requests\\Appointment',
             'uses' => 
            array (
              'userrole' => 'App\\Enums\\UserRole',
              'builder' => 'Illuminate\\Database\\Query\\Builder',
              'formrequest' => 'Illuminate\\Foundation\\Http\\FormRequest',
              'rule' => 'Illuminate\\Validation\\Rule',
            ),
             'constUses' => 
            array (
            ),
          )),
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'bool',
           'parameters' => 
          array (
          ),
           'attributes' => 
          array (
          ),
        )),
        1 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'rules',
           'phpDoc' => 
          \PHPStan\Dependency\ExportedNode\ExportedPhpDocNode::__set_state(array(
             'phpDocString' => '/**
     * @return array<string, mixed>
     */',
             'namespace' => 'App\\Http\\Requests\\Appointment',
             'uses' => 
            array (
              'userrole' => 'App\\Enums\\UserRole',
              'builder' => 'Illuminate\\Database\\Query\\Builder',
              'formrequest' => 'Illuminate\\Foundation\\Http\\FormRequest',
              'rule' => 'Illuminate\\Validation\\Rule',
            ),
             'constUses' => 
            array (
            ),
          )),
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'array',
           'parameters' => 
          array (
          ),
           'attributes' => 
          array (
          ),
        )),
      ),
       'attributes' => 
      array (
      ),
    )),
  ),
  '/var/www/html/app/Http/Requests/Appointment/CancelAppointmentRequest.php' => 
  array (
    0 => 
    \PHPStan\Dependency\ExportedNode\ExportedClassNode::__set_state(array(
       'name' => 'App\\Http\\Requests\\Appointment\\CancelAppointmentRequest',
       'phpDoc' => NULL,
       'abstract' => false,
       'final' => false,
       'extends' => 'Illuminate\\Foundation\\Http\\FormRequest',
       'implements' => 
      array (
      ),
       'usedTraits' => 
      array (
      ),
       'traitUseAdaptations' => 
      array (
      ),
       'statements' => 
      array (
        0 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'authorize',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'bool',
           'parameters' => 
          array (
          ),
           'attributes' => 
          array (
          ),
        )),
        1 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'rules',
           'phpDoc' => 
          \PHPStan\Dependency\ExportedNode\ExportedPhpDocNode::__set_state(array(
             'phpDocString' => '/**
     * @return array<string, mixed>
     */',
             'namespace' => 'App\\Http\\Requests\\Appointment',
             'uses' => 
            array (
              'formrequest' => 'Illuminate\\Foundation\\Http\\FormRequest',
            ),
             'constUses' => 
            array (
            ),
          )),
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'array',
           'parameters' => 
          array (
          ),
           'attributes' => 
          array (
          ),
        )),
      ),
       'attributes' => 
      array (
      ),
    )),
  ),
  '/var/www/html/app/Http/Requests/Appointment/IndexAppointmentRequest.php' => 
  array (
    0 => 
    \PHPStan\Dependency\ExportedNode\ExportedClassNode::__set_state(array(
       'name' => 'App\\Http\\Requests\\Appointment\\IndexAppointmentRequest',
       'phpDoc' => NULL,
       'abstract' => false,
       'final' => false,
       'extends' => 'Illuminate\\Foundation\\Http\\FormRequest',
       'implements' => 
      array (
      ),
       'usedTraits' => 
      array (
      ),
       'traitUseAdaptations' => 
      array (
      ),
       'statements' => 
      array (
        0 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'authorize',
           'phpDoc' => 
          \PHPStan\Dependency\ExportedNode\ExportedPhpDocNode::__set_state(array(
             'phpDocString' => '/**
     * Open to any authenticated role (design doc §14 — clinic-wide read visibility); the
     * controller still calls `AppointmentPolicy::viewAny()` for consistency with the rest of
     * the API, per `api-guidelines.md`.
     */',
             'namespace' => 'App\\Http\\Requests\\Appointment',
             'uses' => 
            array (
              'appointmentstatus' => 'App\\Enums\\AppointmentStatus',
              'userrole' => 'App\\Enums\\UserRole',
              'builder' => 'Illuminate\\Database\\Query\\Builder',
              'formrequest' => 'Illuminate\\Foundation\\Http\\FormRequest',
              'rule' => 'Illuminate\\Validation\\Rule',
            ),
             'constUses' => 
            array (
            ),
          )),
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'bool',
           'parameters' => 
          array (
          ),
           'attributes' => 
          array (
          ),
        )),
        1 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'rules',
           'phpDoc' => 
          \PHPStan\Dependency\ExportedNode\ExportedPhpDocNode::__set_state(array(
             'phpDocString' => '/**
     * Bounded by a required date range instead of classic pagination (design doc §16/§19).
     *
     * @return array<string, mixed>
     */',
             'namespace' => 'App\\Http\\Requests\\Appointment',
             'uses' => 
            array (
              'appointmentstatus' => 'App\\Enums\\AppointmentStatus',
              'userrole' => 'App\\Enums\\UserRole',
              'builder' => 'Illuminate\\Database\\Query\\Builder',
              'formrequest' => 'Illuminate\\Foundation\\Http\\FormRequest',
              'rule' => 'Illuminate\\Validation\\Rule',
            ),
             'constUses' => 
            array (
            ),
          )),
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'array',
           'parameters' => 
          array (
          ),
           'attributes' => 
          array (
          ),
        )),
      ),
       'attributes' => 
      array (
      ),
    )),
  ),
  '/var/www/html/app/Http/Requests/Appointment/MarkNoShowAppointmentRequest.php' => 
  array (
    0 => 
    \PHPStan\Dependency\ExportedNode\ExportedClassNode::__set_state(array(
       'name' => 'App\\Http\\Requests\\Appointment\\MarkNoShowAppointmentRequest',
       'phpDoc' => NULL,
       'abstract' => false,
       'final' => false,
       'extends' => 'Illuminate\\Foundation\\Http\\FormRequest',
       'implements' => 
      array (
      ),
       'usedTraits' => 
      array (
      ),
       'traitUseAdaptations' => 
      array (
      ),
       'statements' => 
      array (
        0 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'authorize',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'bool',
           'parameters' => 
          array (
          ),
           'attributes' => 
          array (
          ),
        )),
        1 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'rules',
           'phpDoc' => 
          \PHPStan\Dependency\ExportedNode\ExportedPhpDocNode::__set_state(array(
             'phpDocString' => '/**
     * `no_show` is normally only settable after `start_at` has passed — a soft rule enforced
     * in AppointmentService, overridable via this flag for staff backfilling records (§5.9/§13).
     *
     * @return array<string, mixed>
     */',
             'namespace' => 'App\\Http\\Requests\\Appointment',
             'uses' => 
            array (
              'formrequest' => 'Illuminate\\Foundation\\Http\\FormRequest',
            ),
             'constUses' => 
            array (
            ),
          )),
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'array',
           'parameters' => 
          array (
          ),
           'attributes' => 
          array (
          ),
        )),
      ),
       'attributes' => 
      array (
      ),
    )),
  ),
  '/var/www/html/app/Http/Requests/Appointment/StoreAppointmentRequest.php' => 
  array (
    0 => 
    \PHPStan\Dependency\ExportedNode\ExportedClassNode::__set_state(array(
       'name' => 'App\\Http\\Requests\\Appointment\\StoreAppointmentRequest',
       'phpDoc' => NULL,
       'abstract' => false,
       'final' => false,
       'extends' => 'Illuminate\\Foundation\\Http\\FormRequest',
       'implements' => 
      array (
      ),
       'usedTraits' => 
      array (
      ),
       'traitUseAdaptations' => 
      array (
      ),
       'statements' => 
      array (
        0 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'authorize',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'bool',
           'parameters' => 
          array (
          ),
           'attributes' => 
          array (
          ),
        )),
        1 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'rules',
           'phpDoc' => 
          \PHPStan\Dependency\ExportedNode\ExportedPhpDocNode::__set_state(array(
             'phpDocString' => '/**
     * Shape/reference validation only. Availability, conflict detection, and slot
     * computation are AppointmentService concerns (design doc §17), not this layer.
     *
     * @return array<string, mixed>
     */',
             'namespace' => 'App\\Http\\Requests\\Appointment',
             'uses' => 
            array (
              'userrole' => 'App\\Enums\\UserRole',
              'appointment' => 'App\\Models\\Appointment',
              'builder' => 'Illuminate\\Database\\Query\\Builder',
              'formrequest' => 'Illuminate\\Foundation\\Http\\FormRequest',
              'rule' => 'Illuminate\\Validation\\Rule',
            ),
             'constUses' => 
            array (
            ),
          )),
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'array',
           'parameters' => 
          array (
          ),
           'attributes' => 
          array (
          ),
        )),
      ),
       'attributes' => 
      array (
      ),
    )),
  ),
  '/var/www/html/app/Http/Requests/Appointment/UpdateAppointmentRequest.php' => 
  array (
    0 => 
    \PHPStan\Dependency\ExportedNode\ExportedClassNode::__set_state(array(
       'name' => 'App\\Http\\Requests\\Appointment\\UpdateAppointmentRequest',
       'phpDoc' => NULL,
       'abstract' => false,
       'final' => false,
       'extends' => 'Illuminate\\Foundation\\Http\\FormRequest',
       'implements' => 
      array (
      ),
       'usedTraits' => 
      array (
      ),
       'traitUseAdaptations' => 
      array (
      ),
       'statements' => 
      array (
        0 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'authorize',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'bool',
           'parameters' => 
          array (
          ),
           'attributes' => 
          array (
          ),
        )),
        1 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'rules',
           'phpDoc' => 
          \PHPStan\Dependency\ExportedNode\ExportedPhpDocNode::__set_state(array(
             'phpDocString' => '/**
     * Covers the "full edit" and in-place reschedule flows (design doc §11). `patient_id` is
     * deliberately not editable here — booking the wrong patient entirely is a soft-delete
     * case (§4), not a correction via update. `status` is not editable here either — it only
     * moves through the dedicated transition endpoints (confirm/check-in/start/.../cancel).
     *
     * @return array<string, mixed>
     */',
             'namespace' => 'App\\Http\\Requests\\Appointment',
             'uses' => 
            array (
              'userrole' => 'App\\Enums\\UserRole',
              'builder' => 'Illuminate\\Database\\Query\\Builder',
              'formrequest' => 'Illuminate\\Foundation\\Http\\FormRequest',
              'rule' => 'Illuminate\\Validation\\Rule',
            ),
             'constUses' => 
            array (
            ),
          )),
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'array',
           'parameters' => 
          array (
          ),
           'attributes' => 
          array (
          ),
        )),
      ),
       'attributes' => 
      array (
      ),
    )),
  ),
  '/var/www/html/app/Http/Requests/AppointmentType/StoreAppointmentTypeRequest.php' => 
  array (
    0 => 
    \PHPStan\Dependency\ExportedNode\ExportedClassNode::__set_state(array(
       'name' => 'App\\Http\\Requests\\AppointmentType\\StoreAppointmentTypeRequest',
       'phpDoc' => NULL,
       'abstract' => false,
       'final' => false,
       'extends' => 'Illuminate\\Foundation\\Http\\FormRequest',
       'implements' => 
      array (
      ),
       'usedTraits' => 
      array (
      ),
       'traitUseAdaptations' => 
      array (
      ),
       'statements' => 
      array (
        0 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'authorize',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'bool',
           'parameters' => 
          array (
          ),
           'attributes' => 
          array (
          ),
        )),
        1 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'rules',
           'phpDoc' => 
          \PHPStan\Dependency\ExportedNode\ExportedPhpDocNode::__set_state(array(
             'phpDocString' => '/**
     * @return array<string, mixed>
     */',
             'namespace' => 'App\\Http\\Requests\\AppointmentType',
             'uses' => 
            array (
              'appointmenttype' => 'App\\Models\\AppointmentType',
              'formrequest' => 'Illuminate\\Foundation\\Http\\FormRequest',
            ),
             'constUses' => 
            array (
            ),
          )),
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'array',
           'parameters' => 
          array (
          ),
           'attributes' => 
          array (
          ),
        )),
      ),
       'attributes' => 
      array (
      ),
    )),
  ),
  '/var/www/html/app/Http/Requests/AppointmentType/UpdateAppointmentTypeRequest.php' => 
  array (
    0 => 
    \PHPStan\Dependency\ExportedNode\ExportedClassNode::__set_state(array(
       'name' => 'App\\Http\\Requests\\AppointmentType\\UpdateAppointmentTypeRequest',
       'phpDoc' => NULL,
       'abstract' => false,
       'final' => false,
       'extends' => 'Illuminate\\Foundation\\Http\\FormRequest',
       'implements' => 
      array (
      ),
       'usedTraits' => 
      array (
      ),
       'traitUseAdaptations' => 
      array (
      ),
       'statements' => 
      array (
        0 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'authorize',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'bool',
           'parameters' => 
          array (
          ),
           'attributes' => 
          array (
          ),
        )),
        1 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'rules',
           'phpDoc' => 
          \PHPStan\Dependency\ExportedNode\ExportedPhpDocNode::__set_state(array(
             'phpDocString' => '/**
     * @return array<string, mixed>
     */',
             'namespace' => 'App\\Http\\Requests\\AppointmentType',
             'uses' => 
            array (
              'formrequest' => 'Illuminate\\Foundation\\Http\\FormRequest',
            ),
             'constUses' => 
            array (
            ),
          )),
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'array',
           'parameters' => 
          array (
          ),
           'attributes' => 
          array (
          ),
        )),
      ),
       'attributes' => 
      array (
      ),
    )),
  ),
  '/var/www/html/app/Http/Requests/Auth/LoginRequest.php' => 
  array (
    0 => 
    \PHPStan\Dependency\ExportedNode\ExportedClassNode::__set_state(array(
       'name' => 'App\\Http\\Requests\\Auth\\LoginRequest',
       'phpDoc' => NULL,
       'abstract' => false,
       'final' => false,
       'extends' => 'Illuminate\\Foundation\\Http\\FormRequest',
       'implements' => 
      array (
      ),
       'usedTraits' => 
      array (
      ),
       'traitUseAdaptations' => 
      array (
      ),
       'statements' => 
      array (
        0 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'authorize',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'bool',
           'parameters' => 
          array (
          ),
           'attributes' => 
          array (
          ),
        )),
        1 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'rules',
           'phpDoc' => 
          \PHPStan\Dependency\ExportedNode\ExportedPhpDocNode::__set_state(array(
             'phpDocString' => '/**
     * @return array<string, mixed>
     */',
             'namespace' => 'App\\Http\\Requests\\Auth',
             'uses' => 
            array (
              'formrequest' => 'Illuminate\\Foundation\\Http\\FormRequest',
            ),
             'constUses' => 
            array (
            ),
          )),
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'array',
           'parameters' => 
          array (
          ),
           'attributes' => 
          array (
          ),
        )),
        2 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'throttleKey',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'string',
           'parameters' => 
          array (
          ),
           'attributes' => 
          array (
          ),
        )),
      ),
       'attributes' => 
      array (
      ),
    )),
  ),
  '/var/www/html/app/Http/Requests/DentistTimeOff/StoreDentistTimeOffRequest.php' => 
  array (
    0 => 
    \PHPStan\Dependency\ExportedNode\ExportedClassNode::__set_state(array(
       'name' => 'App\\Http\\Requests\\DentistTimeOff\\StoreDentistTimeOffRequest',
       'phpDoc' => NULL,
       'abstract' => false,
       'final' => false,
       'extends' => 'Illuminate\\Foundation\\Http\\FormRequest',
       'implements' => 
      array (
      ),
       'usedTraits' => 
      array (
      ),
       'traitUseAdaptations' => 
      array (
      ),
       'statements' => 
      array (
        0 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'authorize',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'bool',
           'parameters' => 
          array (
          ),
           'attributes' => 
          array (
          ),
        )),
        1 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'rules',
           'phpDoc' => 
          \PHPStan\Dependency\ExportedNode\ExportedPhpDocNode::__set_state(array(
             'phpDocString' => '/**
     * @return array<string, mixed>
     */',
             'namespace' => 'App\\Http\\Requests\\DentistTimeOff',
             'uses' => 
            array (
              'dentisttimeoff' => 'App\\Models\\DentistTimeOff',
              'formrequest' => 'Illuminate\\Foundation\\Http\\FormRequest',
            ),
             'constUses' => 
            array (
            ),
          )),
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'array',
           'parameters' => 
          array (
          ),
           'attributes' => 
          array (
          ),
        )),
      ),
       'attributes' => 
      array (
      ),
    )),
  ),
  '/var/www/html/app/Http/Requests/DentistWorkingHour/StoreDentistWorkingHourRequest.php' => 
  array (
    0 => 
    \PHPStan\Dependency\ExportedNode\ExportedClassNode::__set_state(array(
       'name' => 'App\\Http\\Requests\\DentistWorkingHour\\StoreDentistWorkingHourRequest',
       'phpDoc' => NULL,
       'abstract' => false,
       'final' => false,
       'extends' => 'Illuminate\\Foundation\\Http\\FormRequest',
       'implements' => 
      array (
      ),
       'usedTraits' => 
      array (
      ),
       'traitUseAdaptations' => 
      array (
      ),
       'statements' => 
      array (
        0 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'authorize',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'bool',
           'parameters' => 
          array (
          ),
           'attributes' => 
          array (
          ),
        )),
        1 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'rules',
           'phpDoc' => 
          \PHPStan\Dependency\ExportedNode\ExportedPhpDocNode::__set_state(array(
             'phpDocString' => '/**
     * Multiple rows per `day_of_week` are valid (lunch-break split shifts) — no uniqueness
     * rule here (design doc §6). Overlap-within-the-same-day sanity checking, if any, belongs
     * to AppointmentService, not this layer.
     *
     * @return array<string, mixed>
     */',
             'namespace' => 'App\\Http\\Requests\\DentistWorkingHour',
             'uses' => 
            array (
              'dentistworkinghour' => 'App\\Models\\DentistWorkingHour',
              'formrequest' => 'Illuminate\\Foundation\\Http\\FormRequest',
            ),
             'constUses' => 
            array (
            ),
          )),
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'array',
           'parameters' => 
          array (
          ),
           'attributes' => 
          array (
          ),
        )),
      ),
       'attributes' => 
      array (
      ),
    )),
  ),
  '/var/www/html/app/Http/Requests/Patient/StorePatientRequest.php' => 
  array (
    0 => 
    \PHPStan\Dependency\ExportedNode\ExportedClassNode::__set_state(array(
       'name' => 'App\\Http\\Requests\\Patient\\StorePatientRequest',
       'phpDoc' => NULL,
       'abstract' => false,
       'final' => false,
       'extends' => 'Illuminate\\Foundation\\Http\\FormRequest',
       'implements' => 
      array (
      ),
       'usedTraits' => 
      array (
      ),
       'traitUseAdaptations' => 
      array (
      ),
       'statements' => 
      array (
        0 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'authorize',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'bool',
           'parameters' => 
          array (
          ),
           'attributes' => 
          array (
          ),
        )),
        1 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'rules',
           'phpDoc' => 
          \PHPStan\Dependency\ExportedNode\ExportedPhpDocNode::__set_state(array(
             'phpDocString' => '/**
     * @return array<string, mixed>
     */',
             'namespace' => 'App\\Http\\Requests\\Patient',
             'uses' => 
            array (
              'bloodtype' => 'App\\Enums\\BloodType',
              'patientgender' => 'App\\Enums\\PatientGender',
              'patient' => 'App\\Models\\Patient',
              'formrequest' => 'Illuminate\\Foundation\\Http\\FormRequest',
              'rule' => 'Illuminate\\Validation\\Rule',
            ),
             'constUses' => 
            array (
            ),
          )),
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'array',
           'parameters' => 
          array (
          ),
           'attributes' => 
          array (
          ),
        )),
      ),
       'attributes' => 
      array (
      ),
    )),
  ),
  '/var/www/html/app/Http/Requests/Patient/UpdatePatientRequest.php' => 
  array (
    0 => 
    \PHPStan\Dependency\ExportedNode\ExportedClassNode::__set_state(array(
       'name' => 'App\\Http\\Requests\\Patient\\UpdatePatientRequest',
       'phpDoc' => NULL,
       'abstract' => false,
       'final' => false,
       'extends' => 'Illuminate\\Foundation\\Http\\FormRequest',
       'implements' => 
      array (
      ),
       'usedTraits' => 
      array (
      ),
       'traitUseAdaptations' => 
      array (
      ),
       'statements' => 
      array (
        0 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'authorize',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'bool',
           'parameters' => 
          array (
          ),
           'attributes' => 
          array (
          ),
        )),
        1 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'rules',
           'phpDoc' => 
          \PHPStan\Dependency\ExportedNode\ExportedPhpDocNode::__set_state(array(
             'phpDocString' => '/**
     * @return array<string, mixed>
     */',
             'namespace' => 'App\\Http\\Requests\\Patient',
             'uses' => 
            array (
              'bloodtype' => 'App\\Enums\\BloodType',
              'patientgender' => 'App\\Enums\\PatientGender',
              'formrequest' => 'Illuminate\\Foundation\\Http\\FormRequest',
              'rule' => 'Illuminate\\Validation\\Rule',
            ),
             'constUses' => 
            array (
            ),
          )),
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'array',
           'parameters' => 
          array (
          ),
           'attributes' => 
          array (
          ),
        )),
      ),
       'attributes' => 
      array (
      ),
    )),
  ),
  '/var/www/html/app/Http/Requests/User/StoreUserRequest.php' => 
  array (
    0 => 
    \PHPStan\Dependency\ExportedNode\ExportedClassNode::__set_state(array(
       'name' => 'App\\Http\\Requests\\User\\StoreUserRequest',
       'phpDoc' => NULL,
       'abstract' => false,
       'final' => false,
       'extends' => 'Illuminate\\Foundation\\Http\\FormRequest',
       'implements' => 
      array (
      ),
       'usedTraits' => 
      array (
      ),
       'traitUseAdaptations' => 
      array (
      ),
       'statements' => 
      array (
        0 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'authorize',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'bool',
           'parameters' => 
          array (
          ),
           'attributes' => 
          array (
          ),
        )),
        1 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'rules',
           'phpDoc' => 
          \PHPStan\Dependency\ExportedNode\ExportedPhpDocNode::__set_state(array(
             'phpDocString' => '/**
     * @return array<string, mixed>
     */',
             'namespace' => 'App\\Http\\Requests\\User',
             'uses' => 
            array (
              'userrole' => 'App\\Enums\\UserRole',
              'user' => 'App\\Models\\User',
              'formrequest' => 'Illuminate\\Foundation\\Http\\FormRequest',
              'enum' => 'Illuminate\\Validation\\Rules\\Enum',
              'password' => 'Illuminate\\Validation\\Rules\\Password',
            ),
             'constUses' => 
            array (
            ),
          )),
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'array',
           'parameters' => 
          array (
          ),
           'attributes' => 
          array (
          ),
        )),
      ),
       'attributes' => 
      array (
      ),
    )),
  ),
  '/var/www/html/app/Http/Requests/User/UpdateUserRequest.php' => 
  array (
    0 => 
    \PHPStan\Dependency\ExportedNode\ExportedClassNode::__set_state(array(
       'name' => 'App\\Http\\Requests\\User\\UpdateUserRequest',
       'phpDoc' => NULL,
       'abstract' => false,
       'final' => false,
       'extends' => 'Illuminate\\Foundation\\Http\\FormRequest',
       'implements' => 
      array (
      ),
       'usedTraits' => 
      array (
      ),
       'traitUseAdaptations' => 
      array (
      ),
       'statements' => 
      array (
        0 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'authorize',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'bool',
           'parameters' => 
          array (
          ),
           'attributes' => 
          array (
          ),
        )),
        1 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'rules',
           'phpDoc' => 
          \PHPStan\Dependency\ExportedNode\ExportedPhpDocNode::__set_state(array(
             'phpDocString' => '/**
     * @return array<string, mixed>
     */',
             'namespace' => 'App\\Http\\Requests\\User',
             'uses' => 
            array (
              'userrole' => 'App\\Enums\\UserRole',
              'formrequest' => 'Illuminate\\Foundation\\Http\\FormRequest',
              'rule' => 'Illuminate\\Validation\\Rule',
              'enum' => 'Illuminate\\Validation\\Rules\\Enum',
              'password' => 'Illuminate\\Validation\\Rules\\Password',
            ),
             'constUses' => 
            array (
            ),
          )),
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'array',
           'parameters' => 
          array (
          ),
           'attributes' => 
          array (
          ),
        )),
      ),
       'attributes' => 
      array (
      ),
    )),
  ),
  '/var/www/html/app/Http/Resources/AppointmentResource.php' => 
  array (
    0 => 
    \PHPStan\Dependency\ExportedNode\ExportedClassNode::__set_state(array(
       'name' => 'App\\Http\\Resources\\AppointmentResource',
       'phpDoc' => 
      \PHPStan\Dependency\ExportedNode\ExportedPhpDocNode::__set_state(array(
         'phpDocString' => '/** @mixin Appointment */',
         'namespace' => 'App\\Http\\Resources',
         'uses' => 
        array (
          'appointment' => 'App\\Models\\Appointment',
          'request' => 'Illuminate\\Http\\Request',
          'jsonresource' => 'Illuminate\\Http\\Resources\\Json\\JsonResource',
        ),
         'constUses' => 
        array (
        ),
      )),
       'abstract' => false,
       'final' => false,
       'extends' => 'Illuminate\\Http\\Resources\\Json\\JsonResource',
       'implements' => 
      array (
      ),
       'usedTraits' => 
      array (
      ),
       'traitUseAdaptations' => 
      array (
      ),
       'statements' => 
      array (
        0 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'toArray',
           'phpDoc' => 
          \PHPStan\Dependency\ExportedNode\ExportedPhpDocNode::__set_state(array(
             'phpDocString' => '/**
     * @return array<string, mixed>
     */',
             'namespace' => 'App\\Http\\Resources',
             'uses' => 
            array (
              'appointment' => 'App\\Models\\Appointment',
              'request' => 'Illuminate\\Http\\Request',
              'jsonresource' => 'Illuminate\\Http\\Resources\\Json\\JsonResource',
            ),
             'constUses' => 
            array (
            ),
          )),
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'array',
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'request',
               'type' => 'Illuminate\\Http\\Request',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
      ),
       'attributes' => 
      array (
      ),
    )),
  ),
  '/var/www/html/app/Http/Resources/AppointmentTypeResource.php' => 
  array (
    0 => 
    \PHPStan\Dependency\ExportedNode\ExportedClassNode::__set_state(array(
       'name' => 'App\\Http\\Resources\\AppointmentTypeResource',
       'phpDoc' => 
      \PHPStan\Dependency\ExportedNode\ExportedPhpDocNode::__set_state(array(
         'phpDocString' => '/** @mixin AppointmentType */',
         'namespace' => 'App\\Http\\Resources',
         'uses' => 
        array (
          'appointmenttype' => 'App\\Models\\AppointmentType',
          'request' => 'Illuminate\\Http\\Request',
          'jsonresource' => 'Illuminate\\Http\\Resources\\Json\\JsonResource',
        ),
         'constUses' => 
        array (
        ),
      )),
       'abstract' => false,
       'final' => false,
       'extends' => 'Illuminate\\Http\\Resources\\Json\\JsonResource',
       'implements' => 
      array (
      ),
       'usedTraits' => 
      array (
      ),
       'traitUseAdaptations' => 
      array (
      ),
       'statements' => 
      array (
        0 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'toArray',
           'phpDoc' => 
          \PHPStan\Dependency\ExportedNode\ExportedPhpDocNode::__set_state(array(
             'phpDocString' => '/**
     * @return array<string, mixed>
     */',
             'namespace' => 'App\\Http\\Resources',
             'uses' => 
            array (
              'appointmenttype' => 'App\\Models\\AppointmentType',
              'request' => 'Illuminate\\Http\\Request',
              'jsonresource' => 'Illuminate\\Http\\Resources\\Json\\JsonResource',
            ),
             'constUses' => 
            array (
            ),
          )),
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'array',
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'request',
               'type' => 'Illuminate\\Http\\Request',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
      ),
       'attributes' => 
      array (
      ),
    )),
  ),
  '/var/www/html/app/Http/Resources/AuditLogResource.php' => 
  array (
    0 => 
    \PHPStan\Dependency\ExportedNode\ExportedClassNode::__set_state(array(
       'name' => 'App\\Http\\Resources\\AuditLogResource',
       'phpDoc' => 
      \PHPStan\Dependency\ExportedNode\ExportedPhpDocNode::__set_state(array(
         'phpDocString' => '/** @mixin AuditLog */',
         'namespace' => 'App\\Http\\Resources',
         'uses' => 
        array (
          'auditlog' => 'App\\Models\\AuditLog',
          'request' => 'Illuminate\\Http\\Request',
          'jsonresource' => 'Illuminate\\Http\\Resources\\Json\\JsonResource',
        ),
         'constUses' => 
        array (
        ),
      )),
       'abstract' => false,
       'final' => false,
       'extends' => 'Illuminate\\Http\\Resources\\Json\\JsonResource',
       'implements' => 
      array (
      ),
       'usedTraits' => 
      array (
      ),
       'traitUseAdaptations' => 
      array (
      ),
       'statements' => 
      array (
        0 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'toArray',
           'phpDoc' => 
          \PHPStan\Dependency\ExportedNode\ExportedPhpDocNode::__set_state(array(
             'phpDocString' => '/**
     * @return array<string, mixed>
     */',
             'namespace' => 'App\\Http\\Resources',
             'uses' => 
            array (
              'auditlog' => 'App\\Models\\AuditLog',
              'request' => 'Illuminate\\Http\\Request',
              'jsonresource' => 'Illuminate\\Http\\Resources\\Json\\JsonResource',
            ),
             'constUses' => 
            array (
            ),
          )),
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'array',
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'request',
               'type' => 'Illuminate\\Http\\Request',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
      ),
       'attributes' => 
      array (
      ),
    )),
  ),
  '/var/www/html/app/Http/Resources/DentistTimeOffResource.php' => 
  array (
    0 => 
    \PHPStan\Dependency\ExportedNode\ExportedClassNode::__set_state(array(
       'name' => 'App\\Http\\Resources\\DentistTimeOffResource',
       'phpDoc' => 
      \PHPStan\Dependency\ExportedNode\ExportedPhpDocNode::__set_state(array(
         'phpDocString' => '/** @mixin DentistTimeOff */',
         'namespace' => 'App\\Http\\Resources',
         'uses' => 
        array (
          'dentisttimeoff' => 'App\\Models\\DentistTimeOff',
          'request' => 'Illuminate\\Http\\Request',
          'jsonresource' => 'Illuminate\\Http\\Resources\\Json\\JsonResource',
        ),
         'constUses' => 
        array (
        ),
      )),
       'abstract' => false,
       'final' => false,
       'extends' => 'Illuminate\\Http\\Resources\\Json\\JsonResource',
       'implements' => 
      array (
      ),
       'usedTraits' => 
      array (
      ),
       'traitUseAdaptations' => 
      array (
      ),
       'statements' => 
      array (
        0 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'toArray',
           'phpDoc' => 
          \PHPStan\Dependency\ExportedNode\ExportedPhpDocNode::__set_state(array(
             'phpDocString' => '/**
     * @return array<string, mixed>
     */',
             'namespace' => 'App\\Http\\Resources',
             'uses' => 
            array (
              'dentisttimeoff' => 'App\\Models\\DentistTimeOff',
              'request' => 'Illuminate\\Http\\Request',
              'jsonresource' => 'Illuminate\\Http\\Resources\\Json\\JsonResource',
            ),
             'constUses' => 
            array (
            ),
          )),
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'array',
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'request',
               'type' => 'Illuminate\\Http\\Request',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
      ),
       'attributes' => 
      array (
      ),
    )),
  ),
  '/var/www/html/app/Http/Resources/DentistWorkingHourResource.php' => 
  array (
    0 => 
    \PHPStan\Dependency\ExportedNode\ExportedClassNode::__set_state(array(
       'name' => 'App\\Http\\Resources\\DentistWorkingHourResource',
       'phpDoc' => 
      \PHPStan\Dependency\ExportedNode\ExportedPhpDocNode::__set_state(array(
         'phpDocString' => '/** @mixin DentistWorkingHour */',
         'namespace' => 'App\\Http\\Resources',
         'uses' => 
        array (
          'dentistworkinghour' => 'App\\Models\\DentistWorkingHour',
          'request' => 'Illuminate\\Http\\Request',
          'jsonresource' => 'Illuminate\\Http\\Resources\\Json\\JsonResource',
        ),
         'constUses' => 
        array (
        ),
      )),
       'abstract' => false,
       'final' => false,
       'extends' => 'Illuminate\\Http\\Resources\\Json\\JsonResource',
       'implements' => 
      array (
      ),
       'usedTraits' => 
      array (
      ),
       'traitUseAdaptations' => 
      array (
      ),
       'statements' => 
      array (
        0 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'toArray',
           'phpDoc' => 
          \PHPStan\Dependency\ExportedNode\ExportedPhpDocNode::__set_state(array(
             'phpDocString' => '/**
     * @return array<string, mixed>
     */',
             'namespace' => 'App\\Http\\Resources',
             'uses' => 
            array (
              'dentistworkinghour' => 'App\\Models\\DentistWorkingHour',
              'request' => 'Illuminate\\Http\\Request',
              'jsonresource' => 'Illuminate\\Http\\Resources\\Json\\JsonResource',
            ),
             'constUses' => 
            array (
            ),
          )),
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'array',
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'request',
               'type' => 'Illuminate\\Http\\Request',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
      ),
       'attributes' => 
      array (
      ),
    )),
  ),
  '/var/www/html/app/Http/Resources/PatientResource.php' => 
  array (
    0 => 
    \PHPStan\Dependency\ExportedNode\ExportedClassNode::__set_state(array(
       'name' => 'App\\Http\\Resources\\PatientResource',
       'phpDoc' => 
      \PHPStan\Dependency\ExportedNode\ExportedPhpDocNode::__set_state(array(
         'phpDocString' => '/** @mixin Patient */',
         'namespace' => 'App\\Http\\Resources',
         'uses' => 
        array (
          'patient' => 'App\\Models\\Patient',
          'request' => 'Illuminate\\Http\\Request',
          'jsonresource' => 'Illuminate\\Http\\Resources\\Json\\JsonResource',
        ),
         'constUses' => 
        array (
        ),
      )),
       'abstract' => false,
       'final' => false,
       'extends' => 'Illuminate\\Http\\Resources\\Json\\JsonResource',
       'implements' => 
      array (
      ),
       'usedTraits' => 
      array (
      ),
       'traitUseAdaptations' => 
      array (
      ),
       'statements' => 
      array (
        0 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'toArray',
           'phpDoc' => 
          \PHPStan\Dependency\ExportedNode\ExportedPhpDocNode::__set_state(array(
             'phpDocString' => '/**
     * @return array<string, mixed>
     */',
             'namespace' => 'App\\Http\\Resources',
             'uses' => 
            array (
              'patient' => 'App\\Models\\Patient',
              'request' => 'Illuminate\\Http\\Request',
              'jsonresource' => 'Illuminate\\Http\\Resources\\Json\\JsonResource',
            ),
             'constUses' => 
            array (
            ),
          )),
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'array',
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'request',
               'type' => 'Illuminate\\Http\\Request',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
      ),
       'attributes' => 
      array (
      ),
    )),
  ),
  '/var/www/html/app/Http/Resources/UserResource.php' => 
  array (
    0 => 
    \PHPStan\Dependency\ExportedNode\ExportedClassNode::__set_state(array(
       'name' => 'App\\Http\\Resources\\UserResource',
       'phpDoc' => 
      \PHPStan\Dependency\ExportedNode\ExportedPhpDocNode::__set_state(array(
         'phpDocString' => '/** @mixin User */',
         'namespace' => 'App\\Http\\Resources',
         'uses' => 
        array (
          'user' => 'App\\Models\\User',
          'request' => 'Illuminate\\Http\\Request',
          'jsonresource' => 'Illuminate\\Http\\Resources\\Json\\JsonResource',
        ),
         'constUses' => 
        array (
        ),
      )),
       'abstract' => false,
       'final' => false,
       'extends' => 'Illuminate\\Http\\Resources\\Json\\JsonResource',
       'implements' => 
      array (
      ),
       'usedTraits' => 
      array (
      ),
       'traitUseAdaptations' => 
      array (
      ),
       'statements' => 
      array (
        0 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'toArray',
           'phpDoc' => 
          \PHPStan\Dependency\ExportedNode\ExportedPhpDocNode::__set_state(array(
             'phpDocString' => '/**
     * @return array<string, mixed>
     */',
             'namespace' => 'App\\Http\\Resources',
             'uses' => 
            array (
              'user' => 'App\\Models\\User',
              'request' => 'Illuminate\\Http\\Request',
              'jsonresource' => 'Illuminate\\Http\\Resources\\Json\\JsonResource',
            ),
             'constUses' => 
            array (
            ),
          )),
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'array',
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'request',
               'type' => 'Illuminate\\Http\\Request',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
      ),
       'attributes' => 
      array (
      ),
    )),
  ),
  '/var/www/html/app/Models/Appointment.php' => 
  array (
    0 => 
    \PHPStan\Dependency\ExportedNode\ExportedClassNode::__set_state(array(
       'name' => 'App\\Models\\Appointment',
       'phpDoc' => NULL,
       'abstract' => false,
       'final' => false,
       'extends' => 'Illuminate\\Database\\Eloquent\\Model',
       'implements' => 
      array (
      ),
       'usedTraits' => 
      array (
        0 => 'App\\Models\\Concerns\\Auditable',
        1 => 'Illuminate\\Database\\Eloquent\\Factories\\HasFactory',
        2 => 'Illuminate\\Database\\Eloquent\\Concerns\\HasUuids',
        3 => 'Illuminate\\Database\\Eloquent\\SoftDeletes',
      ),
       'traitUseAdaptations' => 
      array (
      ),
       'statements' => 
      array (
        0 => 
        \PHPStan\Dependency\ExportedNode\ExportedPropertiesNode::__set_state(array(
           'names' => 
          array (
            0 => 'fillable',
          ),
           'phpDoc' => NULL,
           'type' => NULL,
           'public' => false,
           'private' => false,
           'static' => false,
           'readonly' => false,
           'abstract' => false,
           'final' => false,
           'publicSet' => false,
           'protectedSet' => false,
           'privateSet' => false,
           'virtual' => false,
           'attributes' => 
          array (
          ),
           'hooks' => 
          array (
          ),
        )),
        1 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'casts',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => false,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'array',
           'parameters' => 
          array (
          ),
           'attributes' => 
          array (
          ),
        )),
        2 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'patient',
           'phpDoc' => 
          \PHPStan\Dependency\ExportedNode\ExportedPhpDocNode::__set_state(array(
             'phpDocString' => '/**
     * @return BelongsTo<Patient, $this>
     */',
             'namespace' => 'App\\Models',
             'uses' => 
            array (
              'appointmentstatus' => 'App\\Enums\\AppointmentStatus',
              'auditable' => 'App\\Models\\Concerns\\Auditable',
              'appointmentfactory' => 'Database\\Factories\\AppointmentFactory',
              'builder' => 'Illuminate\\Database\\Eloquent\\Builder',
              'hasuuids' => 'Illuminate\\Database\\Eloquent\\Concerns\\HasUuids',
              'hasfactory' => 'Illuminate\\Database\\Eloquent\\Factories\\HasFactory',
              'model' => 'Illuminate\\Database\\Eloquent\\Model',
              'belongsto' => 'Illuminate\\Database\\Eloquent\\Relations\\BelongsTo',
              'hasmany' => 'Illuminate\\Database\\Eloquent\\Relations\\HasMany',
              'softdeletes' => 'Illuminate\\Database\\Eloquent\\SoftDeletes',
            ),
             'constUses' => 
            array (
            ),
          )),
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'Illuminate\\Database\\Eloquent\\Relations\\BelongsTo',
           'parameters' => 
          array (
          ),
           'attributes' => 
          array (
          ),
        )),
        3 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'dentist',
           'phpDoc' => 
          \PHPStan\Dependency\ExportedNode\ExportedPhpDocNode::__set_state(array(
             'phpDocString' => '/**
     * @return BelongsTo<User, $this>
     */',
             'namespace' => 'App\\Models',
             'uses' => 
            array (
              'appointmentstatus' => 'App\\Enums\\AppointmentStatus',
              'auditable' => 'App\\Models\\Concerns\\Auditable',
              'appointmentfactory' => 'Database\\Factories\\AppointmentFactory',
              'builder' => 'Illuminate\\Database\\Eloquent\\Builder',
              'hasuuids' => 'Illuminate\\Database\\Eloquent\\Concerns\\HasUuids',
              'hasfactory' => 'Illuminate\\Database\\Eloquent\\Factories\\HasFactory',
              'model' => 'Illuminate\\Database\\Eloquent\\Model',
              'belongsto' => 'Illuminate\\Database\\Eloquent\\Relations\\BelongsTo',
              'hasmany' => 'Illuminate\\Database\\Eloquent\\Relations\\HasMany',
              'softdeletes' => 'Illuminate\\Database\\Eloquent\\SoftDeletes',
            ),
             'constUses' => 
            array (
            ),
          )),
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'Illuminate\\Database\\Eloquent\\Relations\\BelongsTo',
           'parameters' => 
          array (
          ),
           'attributes' => 
          array (
          ),
        )),
        4 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'appointmentType',
           'phpDoc' => 
          \PHPStan\Dependency\ExportedNode\ExportedPhpDocNode::__set_state(array(
             'phpDocString' => '/**
     * @return BelongsTo<AppointmentType, $this>
     */',
             'namespace' => 'App\\Models',
             'uses' => 
            array (
              'appointmentstatus' => 'App\\Enums\\AppointmentStatus',
              'auditable' => 'App\\Models\\Concerns\\Auditable',
              'appointmentfactory' => 'Database\\Factories\\AppointmentFactory',
              'builder' => 'Illuminate\\Database\\Eloquent\\Builder',
              'hasuuids' => 'Illuminate\\Database\\Eloquent\\Concerns\\HasUuids',
              'hasfactory' => 'Illuminate\\Database\\Eloquent\\Factories\\HasFactory',
              'model' => 'Illuminate\\Database\\Eloquent\\Model',
              'belongsto' => 'Illuminate\\Database\\Eloquent\\Relations\\BelongsTo',
              'hasmany' => 'Illuminate\\Database\\Eloquent\\Relations\\HasMany',
              'softdeletes' => 'Illuminate\\Database\\Eloquent\\SoftDeletes',
            ),
             'constUses' => 
            array (
            ),
          )),
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'Illuminate\\Database\\Eloquent\\Relations\\BelongsTo',
           'parameters' => 
          array (
          ),
           'attributes' => 
          array (
          ),
        )),
        5 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'cancelledBy',
           'phpDoc' => 
          \PHPStan\Dependency\ExportedNode\ExportedPhpDocNode::__set_state(array(
             'phpDocString' => '/**
     * @return BelongsTo<User, $this>
     */',
             'namespace' => 'App\\Models',
             'uses' => 
            array (
              'appointmentstatus' => 'App\\Enums\\AppointmentStatus',
              'auditable' => 'App\\Models\\Concerns\\Auditable',
              'appointmentfactory' => 'Database\\Factories\\AppointmentFactory',
              'builder' => 'Illuminate\\Database\\Eloquent\\Builder',
              'hasuuids' => 'Illuminate\\Database\\Eloquent\\Concerns\\HasUuids',
              'hasfactory' => 'Illuminate\\Database\\Eloquent\\Factories\\HasFactory',
              'model' => 'Illuminate\\Database\\Eloquent\\Model',
              'belongsto' => 'Illuminate\\Database\\Eloquent\\Relations\\BelongsTo',
              'hasmany' => 'Illuminate\\Database\\Eloquent\\Relations\\HasMany',
              'softdeletes' => 'Illuminate\\Database\\Eloquent\\SoftDeletes',
            ),
             'constUses' => 
            array (
            ),
          )),
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'Illuminate\\Database\\Eloquent\\Relations\\BelongsTo',
           'parameters' => 
          array (
          ),
           'attributes' => 
          array (
          ),
        )),
        6 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'reminders',
           'phpDoc' => 
          \PHPStan\Dependency\ExportedNode\ExportedPhpDocNode::__set_state(array(
             'phpDocString' => '/**
     * @return HasMany<AppointmentReminder, $this>
     */',
             'namespace' => 'App\\Models',
             'uses' => 
            array (
              'appointmentstatus' => 'App\\Enums\\AppointmentStatus',
              'auditable' => 'App\\Models\\Concerns\\Auditable',
              'appointmentfactory' => 'Database\\Factories\\AppointmentFactory',
              'builder' => 'Illuminate\\Database\\Eloquent\\Builder',
              'hasuuids' => 'Illuminate\\Database\\Eloquent\\Concerns\\HasUuids',
              'hasfactory' => 'Illuminate\\Database\\Eloquent\\Factories\\HasFactory',
              'model' => 'Illuminate\\Database\\Eloquent\\Model',
              'belongsto' => 'Illuminate\\Database\\Eloquent\\Relations\\BelongsTo',
              'hasmany' => 'Illuminate\\Database\\Eloquent\\Relations\\HasMany',
              'softdeletes' => 'Illuminate\\Database\\Eloquent\\SoftDeletes',
            ),
             'constUses' => 
            array (
            ),
          )),
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'Illuminate\\Database\\Eloquent\\Relations\\HasMany',
           'parameters' => 
          array (
          ),
           'attributes' => 
          array (
          ),
        )),
        7 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'scopeActive',
           'phpDoc' => 
          \PHPStan\Dependency\ExportedNode\ExportedPhpDocNode::__set_state(array(
             'phpDocString' => '/**
     * Appointments in a status that occupies the dentist\'s time slot.
     * Mirrors the status set enforced by the Postgres EXCLUDE constraint (§10 of the design doc).
     */',
             'namespace' => 'App\\Models',
             'uses' => 
            array (
              'appointmentstatus' => 'App\\Enums\\AppointmentStatus',
              'auditable' => 'App\\Models\\Concerns\\Auditable',
              'appointmentfactory' => 'Database\\Factories\\AppointmentFactory',
              'builder' => 'Illuminate\\Database\\Eloquent\\Builder',
              'hasuuids' => 'Illuminate\\Database\\Eloquent\\Concerns\\HasUuids',
              'hasfactory' => 'Illuminate\\Database\\Eloquent\\Factories\\HasFactory',
              'model' => 'Illuminate\\Database\\Eloquent\\Model',
              'belongsto' => 'Illuminate\\Database\\Eloquent\\Relations\\BelongsTo',
              'hasmany' => 'Illuminate\\Database\\Eloquent\\Relations\\HasMany',
              'softdeletes' => 'Illuminate\\Database\\Eloquent\\SoftDeletes',
            ),
             'constUses' => 
            array (
            ),
          )),
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'Illuminate\\Database\\Eloquent\\Builder',
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'query',
               'type' => 'Illuminate\\Database\\Eloquent\\Builder',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
        8 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'scopeForDentist',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'Illuminate\\Database\\Eloquent\\Builder',
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'query',
               'type' => 'Illuminate\\Database\\Eloquent\\Builder',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
            1 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'dentistId',
               'type' => 'string',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
        9 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'scopeForPatient',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'Illuminate\\Database\\Eloquent\\Builder',
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'query',
               'type' => 'Illuminate\\Database\\Eloquent\\Builder',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
            1 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'patientId',
               'type' => 'string',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
      ),
       'attributes' => 
      array (
      ),
    )),
  ),
  '/var/www/html/app/Models/AppointmentReminder.php' => 
  array (
    0 => 
    \PHPStan\Dependency\ExportedNode\ExportedClassNode::__set_state(array(
       'name' => 'App\\Models\\AppointmentReminder',
       'phpDoc' => 
      \PHPStan\Dependency\ExportedNode\ExportedPhpDocNode::__set_state(array(
         'phpDocString' => '/**
 * Database foundation only — not yet wired to a Service, Job, or API endpoint.
 * See docs/modules/appointments-design-draft.md and the appointment_reminders migration.
 */',
         'namespace' => 'App\\Models',
         'uses' => 
        array (
          'appointmentreminderfactory' => 'Database\\Factories\\AppointmentReminderFactory',
          'builder' => 'Illuminate\\Database\\Eloquent\\Builder',
          'hasuuids' => 'Illuminate\\Database\\Eloquent\\Concerns\\HasUuids',
          'hasfactory' => 'Illuminate\\Database\\Eloquent\\Factories\\HasFactory',
          'model' => 'Illuminate\\Database\\Eloquent\\Model',
          'belongsto' => 'Illuminate\\Database\\Eloquent\\Relations\\BelongsTo',
        ),
         'constUses' => 
        array (
        ),
      )),
       'abstract' => false,
       'final' => false,
       'extends' => 'Illuminate\\Database\\Eloquent\\Model',
       'implements' => 
      array (
      ),
       'usedTraits' => 
      array (
        0 => 'Illuminate\\Database\\Eloquent\\Factories\\HasFactory',
        1 => 'Illuminate\\Database\\Eloquent\\Concerns\\HasUuids',
      ),
       'traitUseAdaptations' => 
      array (
      ),
       'statements' => 
      array (
        0 => 
        \PHPStan\Dependency\ExportedNode\ExportedPropertiesNode::__set_state(array(
           'names' => 
          array (
            0 => 'fillable',
          ),
           'phpDoc' => NULL,
           'type' => NULL,
           'public' => false,
           'private' => false,
           'static' => false,
           'readonly' => false,
           'abstract' => false,
           'final' => false,
           'publicSet' => false,
           'protectedSet' => false,
           'privateSet' => false,
           'virtual' => false,
           'attributes' => 
          array (
          ),
           'hooks' => 
          array (
          ),
        )),
        1 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'casts',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => false,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'array',
           'parameters' => 
          array (
          ),
           'attributes' => 
          array (
          ),
        )),
        2 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'appointment',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'Illuminate\\Database\\Eloquent\\Relations\\BelongsTo',
           'parameters' => 
          array (
          ),
           'attributes' => 
          array (
          ),
        )),
        3 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'scopePending',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'Illuminate\\Database\\Eloquent\\Builder',
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'query',
               'type' => 'Illuminate\\Database\\Eloquent\\Builder',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
      ),
       'attributes' => 
      array (
      ),
    )),
  ),
  '/var/www/html/app/Models/AppointmentType.php' => 
  array (
    0 => 
    \PHPStan\Dependency\ExportedNode\ExportedClassNode::__set_state(array(
       'name' => 'App\\Models\\AppointmentType',
       'phpDoc' => NULL,
       'abstract' => false,
       'final' => false,
       'extends' => 'Illuminate\\Database\\Eloquent\\Model',
       'implements' => 
      array (
      ),
       'usedTraits' => 
      array (
        0 => 'Illuminate\\Database\\Eloquent\\Factories\\HasFactory',
        1 => 'Illuminate\\Database\\Eloquent\\Concerns\\HasUuids',
      ),
       'traitUseAdaptations' => 
      array (
      ),
       'statements' => 
      array (
        0 => 
        \PHPStan\Dependency\ExportedNode\ExportedPropertiesNode::__set_state(array(
           'names' => 
          array (
            0 => 'fillable',
          ),
           'phpDoc' => NULL,
           'type' => NULL,
           'public' => false,
           'private' => false,
           'static' => false,
           'readonly' => false,
           'abstract' => false,
           'final' => false,
           'publicSet' => false,
           'protectedSet' => false,
           'privateSet' => false,
           'virtual' => false,
           'attributes' => 
          array (
          ),
           'hooks' => 
          array (
          ),
        )),
        1 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'casts',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => false,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'array',
           'parameters' => 
          array (
          ),
           'attributes' => 
          array (
          ),
        )),
        2 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'appointments',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'Illuminate\\Database\\Eloquent\\Relations\\HasMany',
           'parameters' => 
          array (
          ),
           'attributes' => 
          array (
          ),
        )),
        3 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'scopeActive',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'Illuminate\\Database\\Eloquent\\Builder',
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'query',
               'type' => 'Illuminate\\Database\\Eloquent\\Builder',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
      ),
       'attributes' => 
      array (
      ),
    )),
  ),
  '/var/www/html/app/Models/AuditLog.php' => 
  array (
    0 => 
    \PHPStan\Dependency\ExportedNode\ExportedClassNode::__set_state(array(
       'name' => 'App\\Models\\AuditLog',
       'phpDoc' => NULL,
       'abstract' => false,
       'final' => false,
       'extends' => 'Illuminate\\Database\\Eloquent\\Model',
       'implements' => 
      array (
      ),
       'usedTraits' => 
      array (
        0 => 'Illuminate\\Database\\Eloquent\\Concerns\\HasUuids',
      ),
       'traitUseAdaptations' => 
      array (
      ),
       'statements' => 
      array (
        0 => 
        \PHPStan\Dependency\ExportedNode\ExportedPropertiesNode::__set_state(array(
           'names' => 
          array (
            0 => 'timestamps',
          ),
           'phpDoc' => NULL,
           'type' => NULL,
           'public' => true,
           'private' => false,
           'static' => false,
           'readonly' => false,
           'abstract' => false,
           'final' => false,
           'publicSet' => false,
           'protectedSet' => false,
           'privateSet' => false,
           'virtual' => false,
           'attributes' => 
          array (
          ),
           'hooks' => 
          array (
          ),
        )),
        1 => 
        \PHPStan\Dependency\ExportedNode\ExportedPropertiesNode::__set_state(array(
           'names' => 
          array (
            0 => 'fillable',
          ),
           'phpDoc' => NULL,
           'type' => NULL,
           'public' => false,
           'private' => false,
           'static' => false,
           'readonly' => false,
           'abstract' => false,
           'final' => false,
           'publicSet' => false,
           'protectedSet' => false,
           'privateSet' => false,
           'virtual' => false,
           'attributes' => 
          array (
          ),
           'hooks' => 
          array (
          ),
        )),
        2 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'casts',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => false,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'array',
           'parameters' => 
          array (
          ),
           'attributes' => 
          array (
          ),
        )),
        3 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'user',
           'phpDoc' => 
          \PHPStan\Dependency\ExportedNode\ExportedPhpDocNode::__set_state(array(
             'phpDocString' => '/**
     * @return BelongsTo<User, $this>
     */',
             'namespace' => 'App\\Models',
             'uses' => 
            array (
              'hasuuids' => 'Illuminate\\Database\\Eloquent\\Concerns\\HasUuids',
              'model' => 'Illuminate\\Database\\Eloquent\\Model',
              'belongsto' => 'Illuminate\\Database\\Eloquent\\Relations\\BelongsTo',
              'morphto' => 'Illuminate\\Database\\Eloquent\\Relations\\MorphTo',
            ),
             'constUses' => 
            array (
            ),
          )),
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'Illuminate\\Database\\Eloquent\\Relations\\BelongsTo',
           'parameters' => 
          array (
          ),
           'attributes' => 
          array (
          ),
        )),
        4 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'auditable',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'Illuminate\\Database\\Eloquent\\Relations\\MorphTo',
           'parameters' => 
          array (
          ),
           'attributes' => 
          array (
          ),
        )),
      ),
       'attributes' => 
      array (
      ),
    )),
  ),
  '/var/www/html/app/Models/Concerns/Auditable.php' => 
  array (
    0 => 
    \PHPStan\Dependency\ExportedNode\ExportedTraitNode::__set_state(array(
       'name' => 'App\\Models\\Concerns\\Auditable',
       'phpDoc' => 
      \PHPStan\Dependency\ExportedNode\ExportedPhpDocNode::__set_state(array(
         'phpDocString' => '/**
 * Opt-in trait: records a row in audit_logs on create/update/delete.
 * Add `use Auditable;` to any model that handles sensitive data.
 */',
         'namespace' => 'App\\Models\\Concerns',
         'uses' => 
        array (
          'auditlog' => 'App\\Models\\AuditLog',
          'auditobserver' => 'App\\Observers\\AuditObserver',
          'morphmany' => 'Illuminate\\Database\\Eloquent\\Relations\\MorphMany',
        ),
         'constUses' => 
        array (
        ),
      )),
       'usedTraits' => 
      array (
      ),
       'traitUseAdaptations' => 
      array (
      ),
       'statements' => 
      array (
        0 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'bootAuditable',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => true,
           'returnType' => 'void',
           'parameters' => 
          array (
          ),
           'attributes' => 
          array (
          ),
        )),
        1 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'auditLogs',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'Illuminate\\Database\\Eloquent\\Relations\\MorphMany',
           'parameters' => 
          array (
          ),
           'attributes' => 
          array (
          ),
        )),
      ),
       'attributes' => 
      array (
      ),
    )),
  ),
  '/var/www/html/app/Models/DentistTimeOff.php' => 
  array (
    0 => 
    \PHPStan\Dependency\ExportedNode\ExportedClassNode::__set_state(array(
       'name' => 'App\\Models\\DentistTimeOff',
       'phpDoc' => NULL,
       'abstract' => false,
       'final' => false,
       'extends' => 'Illuminate\\Database\\Eloquent\\Model',
       'implements' => 
      array (
      ),
       'usedTraits' => 
      array (
        0 => 'Illuminate\\Database\\Eloquent\\Factories\\HasFactory',
        1 => 'Illuminate\\Database\\Eloquent\\Concerns\\HasUuids',
      ),
       'traitUseAdaptations' => 
      array (
      ),
       'statements' => 
      array (
        0 => 
        \PHPStan\Dependency\ExportedNode\ExportedPropertiesNode::__set_state(array(
           'names' => 
          array (
            0 => 'table',
          ),
           'phpDoc' => NULL,
           'type' => NULL,
           'public' => false,
           'private' => false,
           'static' => false,
           'readonly' => false,
           'abstract' => false,
           'final' => false,
           'publicSet' => false,
           'protectedSet' => false,
           'privateSet' => false,
           'virtual' => false,
           'attributes' => 
          array (
          ),
           'hooks' => 
          array (
          ),
        )),
        1 => 
        \PHPStan\Dependency\ExportedNode\ExportedPropertiesNode::__set_state(array(
           'names' => 
          array (
            0 => 'fillable',
          ),
           'phpDoc' => NULL,
           'type' => NULL,
           'public' => false,
           'private' => false,
           'static' => false,
           'readonly' => false,
           'abstract' => false,
           'final' => false,
           'publicSet' => false,
           'protectedSet' => false,
           'privateSet' => false,
           'virtual' => false,
           'attributes' => 
          array (
          ),
           'hooks' => 
          array (
          ),
        )),
        2 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'casts',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => false,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'array',
           'parameters' => 
          array (
          ),
           'attributes' => 
          array (
          ),
        )),
        3 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'dentist',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'Illuminate\\Database\\Eloquent\\Relations\\BelongsTo',
           'parameters' => 
          array (
          ),
           'attributes' => 
          array (
          ),
        )),
        4 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'scopeForDentist',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'Illuminate\\Database\\Eloquent\\Builder',
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'query',
               'type' => 'Illuminate\\Database\\Eloquent\\Builder',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
            1 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'dentistId',
               'type' => 'string',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
      ),
       'attributes' => 
      array (
      ),
    )),
  ),
  '/var/www/html/app/Models/DentistWorkingHour.php' => 
  array (
    0 => 
    \PHPStan\Dependency\ExportedNode\ExportedClassNode::__set_state(array(
       'name' => 'App\\Models\\DentistWorkingHour',
       'phpDoc' => NULL,
       'abstract' => false,
       'final' => false,
       'extends' => 'Illuminate\\Database\\Eloquent\\Model',
       'implements' => 
      array (
      ),
       'usedTraits' => 
      array (
        0 => 'Illuminate\\Database\\Eloquent\\Factories\\HasFactory',
        1 => 'Illuminate\\Database\\Eloquent\\Concerns\\HasUuids',
      ),
       'traitUseAdaptations' => 
      array (
      ),
       'statements' => 
      array (
        0 => 
        \PHPStan\Dependency\ExportedNode\ExportedPropertiesNode::__set_state(array(
           'names' => 
          array (
            0 => 'fillable',
          ),
           'phpDoc' => NULL,
           'type' => NULL,
           'public' => false,
           'private' => false,
           'static' => false,
           'readonly' => false,
           'abstract' => false,
           'final' => false,
           'publicSet' => false,
           'protectedSet' => false,
           'privateSet' => false,
           'virtual' => false,
           'attributes' => 
          array (
          ),
           'hooks' => 
          array (
          ),
        )),
        1 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'casts',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => false,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'array',
           'parameters' => 
          array (
          ),
           'attributes' => 
          array (
          ),
        )),
        2 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'dentist',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'Illuminate\\Database\\Eloquent\\Relations\\BelongsTo',
           'parameters' => 
          array (
          ),
           'attributes' => 
          array (
          ),
        )),
        3 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'scopeActive',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'Illuminate\\Database\\Eloquent\\Builder',
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'query',
               'type' => 'Illuminate\\Database\\Eloquent\\Builder',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
        4 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'scopeForDentist',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'Illuminate\\Database\\Eloquent\\Builder',
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'query',
               'type' => 'Illuminate\\Database\\Eloquent\\Builder',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
            1 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'dentistId',
               'type' => 'string',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
        5 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'scopeForDayOfWeek',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'Illuminate\\Database\\Eloquent\\Builder',
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'query',
               'type' => 'Illuminate\\Database\\Eloquent\\Builder',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
            1 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'dayOfWeek',
               'type' => 'int',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
      ),
       'attributes' => 
      array (
      ),
    )),
  ),
  '/var/www/html/app/Models/Patient.php' => 
  array (
    0 => 
    \PHPStan\Dependency\ExportedNode\ExportedClassNode::__set_state(array(
       'name' => 'App\\Models\\Patient',
       'phpDoc' => NULL,
       'abstract' => false,
       'final' => false,
       'extends' => 'Illuminate\\Database\\Eloquent\\Model',
       'implements' => 
      array (
      ),
       'usedTraits' => 
      array (
        0 => 'App\\Models\\Concerns\\Auditable',
        1 => 'Illuminate\\Database\\Eloquent\\Factories\\HasFactory',
        2 => 'Illuminate\\Database\\Eloquent\\Concerns\\HasUuids',
        3 => 'Illuminate\\Database\\Eloquent\\SoftDeletes',
      ),
       'traitUseAdaptations' => 
      array (
      ),
       'statements' => 
      array (
        0 => 
        \PHPStan\Dependency\ExportedNode\ExportedPropertiesNode::__set_state(array(
           'names' => 
          array (
            0 => 'fillable',
          ),
           'phpDoc' => NULL,
           'type' => NULL,
           'public' => false,
           'private' => false,
           'static' => false,
           'readonly' => false,
           'abstract' => false,
           'final' => false,
           'publicSet' => false,
           'protectedSet' => false,
           'privateSet' => false,
           'virtual' => false,
           'attributes' => 
          array (
          ),
           'hooks' => 
          array (
          ),
        )),
        1 => 
        \PHPStan\Dependency\ExportedNode\ExportedPropertiesNode::__set_state(array(
           'names' => 
          array (
            0 => 'appends',
          ),
           'phpDoc' => NULL,
           'type' => NULL,
           'public' => false,
           'private' => false,
           'static' => false,
           'readonly' => false,
           'abstract' => false,
           'final' => false,
           'publicSet' => false,
           'protectedSet' => false,
           'privateSet' => false,
           'virtual' => false,
           'attributes' => 
          array (
          ),
           'hooks' => 
          array (
          ),
        )),
        2 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'casts',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => false,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'array',
           'parameters' => 
          array (
          ),
           'attributes' => 
          array (
          ),
        )),
        3 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'getPatientCodeAttribute',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'string',
           'parameters' => 
          array (
          ),
           'attributes' => 
          array (
          ),
        )),
        4 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'getFullNameAttribute',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'string',
           'parameters' => 
          array (
          ),
           'attributes' => 
          array (
          ),
        )),
        5 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'appointments',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'Illuminate\\Database\\Eloquent\\Relations\\HasMany',
           'parameters' => 
          array (
          ),
           'attributes' => 
          array (
          ),
        )),
      ),
       'attributes' => 
      array (
      ),
    )),
  ),
  '/var/www/html/app/Models/User.php' => 
  array (
    0 => 
    \PHPStan\Dependency\ExportedNode\ExportedClassNode::__set_state(array(
       'name' => 'App\\Models\\User',
       'phpDoc' => NULL,
       'abstract' => false,
       'final' => false,
       'extends' => 'Illuminate\\Foundation\\Auth\\User',
       'implements' => 
      array (
      ),
       'usedTraits' => 
      array (
        0 => 'Illuminate\\Database\\Eloquent\\Factories\\HasFactory',
        1 => 'Illuminate\\Database\\Eloquent\\Concerns\\HasUuids',
        2 => 'Illuminate\\Notifications\\Notifiable',
        3 => 'Illuminate\\Database\\Eloquent\\SoftDeletes',
      ),
       'traitUseAdaptations' => 
      array (
      ),
       'statements' => 
      array (
        0 => 
        \PHPStan\Dependency\ExportedNode\ExportedPropertiesNode::__set_state(array(
           'names' => 
          array (
            0 => 'fillable',
          ),
           'phpDoc' => 
          \PHPStan\Dependency\ExportedNode\ExportedPhpDocNode::__set_state(array(
             'phpDocString' => '/**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */',
             'namespace' => 'App\\Models',
             'uses' => 
            array (
              'userrole' => 'App\\Enums\\UserRole',
              'userfactory' => 'Database\\Factories\\UserFactory',
              'hasuuids' => 'Illuminate\\Database\\Eloquent\\Concerns\\HasUuids',
              'hasfactory' => 'Illuminate\\Database\\Eloquent\\Factories\\HasFactory',
              'hasmany' => 'Illuminate\\Database\\Eloquent\\Relations\\HasMany',
              'softdeletes' => 'Illuminate\\Database\\Eloquent\\SoftDeletes',
              'authenticatable' => 'Illuminate\\Foundation\\Auth\\User',
              'notifiable' => 'Illuminate\\Notifications\\Notifiable',
            ),
             'constUses' => 
            array (
            ),
          )),
           'type' => NULL,
           'public' => false,
           'private' => false,
           'static' => false,
           'readonly' => false,
           'abstract' => false,
           'final' => false,
           'publicSet' => false,
           'protectedSet' => false,
           'privateSet' => false,
           'virtual' => false,
           'attributes' => 
          array (
          ),
           'hooks' => 
          array (
          ),
        )),
        1 => 
        \PHPStan\Dependency\ExportedNode\ExportedPropertiesNode::__set_state(array(
           'names' => 
          array (
            0 => 'hidden',
          ),
           'phpDoc' => 
          \PHPStan\Dependency\ExportedNode\ExportedPhpDocNode::__set_state(array(
             'phpDocString' => '/**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */',
             'namespace' => 'App\\Models',
             'uses' => 
            array (
              'userrole' => 'App\\Enums\\UserRole',
              'userfactory' => 'Database\\Factories\\UserFactory',
              'hasuuids' => 'Illuminate\\Database\\Eloquent\\Concerns\\HasUuids',
              'hasfactory' => 'Illuminate\\Database\\Eloquent\\Factories\\HasFactory',
              'hasmany' => 'Illuminate\\Database\\Eloquent\\Relations\\HasMany',
              'softdeletes' => 'Illuminate\\Database\\Eloquent\\SoftDeletes',
              'authenticatable' => 'Illuminate\\Foundation\\Auth\\User',
              'notifiable' => 'Illuminate\\Notifications\\Notifiable',
            ),
             'constUses' => 
            array (
            ),
          )),
           'type' => NULL,
           'public' => false,
           'private' => false,
           'static' => false,
           'readonly' => false,
           'abstract' => false,
           'final' => false,
           'publicSet' => false,
           'protectedSet' => false,
           'privateSet' => false,
           'virtual' => false,
           'attributes' => 
          array (
          ),
           'hooks' => 
          array (
          ),
        )),
        2 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'casts',
           'phpDoc' => 
          \PHPStan\Dependency\ExportedNode\ExportedPhpDocNode::__set_state(array(
             'phpDocString' => '/**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */',
             'namespace' => 'App\\Models',
             'uses' => 
            array (
              'userrole' => 'App\\Enums\\UserRole',
              'userfactory' => 'Database\\Factories\\UserFactory',
              'hasuuids' => 'Illuminate\\Database\\Eloquent\\Concerns\\HasUuids',
              'hasfactory' => 'Illuminate\\Database\\Eloquent\\Factories\\HasFactory',
              'hasmany' => 'Illuminate\\Database\\Eloquent\\Relations\\HasMany',
              'softdeletes' => 'Illuminate\\Database\\Eloquent\\SoftDeletes',
              'authenticatable' => 'Illuminate\\Foundation\\Auth\\User',
              'notifiable' => 'Illuminate\\Notifications\\Notifiable',
            ),
             'constUses' => 
            array (
            ),
          )),
           'byRef' => false,
           'public' => false,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'array',
           'parameters' => 
          array (
          ),
           'attributes' => 
          array (
          ),
        )),
        3 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'isAdmin',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'bool',
           'parameters' => 
          array (
          ),
           'attributes' => 
          array (
          ),
        )),
        4 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'appointmentsAsDentist',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'Illuminate\\Database\\Eloquent\\Relations\\HasMany',
           'parameters' => 
          array (
          ),
           'attributes' => 
          array (
          ),
        )),
        5 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'workingHours',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'Illuminate\\Database\\Eloquent\\Relations\\HasMany',
           'parameters' => 
          array (
          ),
           'attributes' => 
          array (
          ),
        )),
        6 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'timeOff',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'Illuminate\\Database\\Eloquent\\Relations\\HasMany',
           'parameters' => 
          array (
          ),
           'attributes' => 
          array (
          ),
        )),
      ),
       'attributes' => 
      array (
      ),
    )),
  ),
  '/var/www/html/app/Observers/AuditObserver.php' => 
  array (
    0 => 
    \PHPStan\Dependency\ExportedNode\ExportedClassNode::__set_state(array(
       'name' => 'App\\Observers\\AuditObserver',
       'phpDoc' => NULL,
       'abstract' => false,
       'final' => false,
       'extends' => NULL,
       'implements' => 
      array (
      ),
       'usedTraits' => 
      array (
      ),
       'traitUseAdaptations' => 
      array (
      ),
       'statements' => 
      array (
        0 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => '__construct',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => NULL,
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'auditLogService',
               'type' => 'App\\Services\\AuditLogService',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 4,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
        1 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'created',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'void',
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'model',
               'type' => 'Illuminate\\Database\\Eloquent\\Model',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
        2 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'updated',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'void',
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'model',
               'type' => 'Illuminate\\Database\\Eloquent\\Model',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
        3 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'deleted',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'void',
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'model',
               'type' => 'Illuminate\\Database\\Eloquent\\Model',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
      ),
       'attributes' => 
      array (
      ),
    )),
  ),
  '/var/www/html/app/Policies/AppointmentPolicy.php' => 
  array (
    0 => 
    \PHPStan\Dependency\ExportedNode\ExportedClassNode::__set_state(array(
       'name' => 'App\\Policies\\AppointmentPolicy',
       'phpDoc' => NULL,
       'abstract' => false,
       'final' => false,
       'extends' => NULL,
       'implements' => 
      array (
      ),
       'usedTraits' => 
      array (
      ),
       'traitUseAdaptations' => 
      array (
      ),
       'statements' => 
      array (
        0 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'viewAny',
           'phpDoc' => 
          \PHPStan\Dependency\ExportedNode\ExportedPhpDocNode::__set_state(array(
             'phpDocString' => '/**
     * Clinic-wide read visibility for all staff, including dentists (design doc §14).
     */',
             'namespace' => 'App\\Policies',
             'uses' => 
            array (
              'userrole' => 'App\\Enums\\UserRole',
              'appointment' => 'App\\Models\\Appointment',
              'user' => 'App\\Models\\User',
            ),
             'constUses' => 
            array (
            ),
          )),
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'bool',
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'actor',
               'type' => 'App\\Models\\User',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
        1 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'view',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'bool',
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'actor',
               'type' => 'App\\Models\\User',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
            1 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'appointment',
               'type' => 'App\\Models\\Appointment',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
        2 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'create',
           'phpDoc' => 
          \PHPStan\Dependency\ExportedNode\ExportedPhpDocNode::__set_state(array(
             'phpDocString' => '/**
     * Booking, editing, and rescheduling is front-desk work (admin, receptionist).
     */',
             'namespace' => 'App\\Policies',
             'uses' => 
            array (
              'userrole' => 'App\\Enums\\UserRole',
              'appointment' => 'App\\Models\\Appointment',
              'user' => 'App\\Models\\User',
            ),
             'constUses' => 
            array (
            ),
          )),
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'bool',
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'actor',
               'type' => 'App\\Models\\User',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
        3 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'update',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'bool',
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'actor',
               'type' => 'App\\Models\\User',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
            1 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'appointment',
               'type' => 'App\\Models\\Appointment',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
        4 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'cancel',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'bool',
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'actor',
               'type' => 'App\\Models\\User',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
            1 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'appointment',
               'type' => 'App\\Models\\Appointment',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
        5 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'markNoShow',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'bool',
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'actor',
               'type' => 'App\\Models\\User',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
            1 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'appointment',
               'type' => 'App\\Models\\Appointment',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
        6 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'confirm',
           'phpDoc' => 
          \PHPStan\Dependency\ExportedNode\ExportedPhpDocNode::__set_state(array(
             'phpDocString' => '/**
     * Optional confirmation-call step is front-desk work (design doc §14).
     */',
             'namespace' => 'App\\Policies',
             'uses' => 
            array (
              'userrole' => 'App\\Enums\\UserRole',
              'appointment' => 'App\\Models\\Appointment',
              'user' => 'App\\Models\\User',
            ),
             'constUses' => 
            array (
            ),
          )),
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'bool',
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'actor',
               'type' => 'App\\Models\\User',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
            1 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'appointment',
               'type' => 'App\\Models\\Appointment',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
        7 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'checkIn',
           'phpDoc' => 
          \PHPStan\Dependency\ExportedNode\ExportedPhpDocNode::__set_state(array(
             'phpDocString' => '/**
     * Check-in is front-desk work (design doc §14).
     */',
             'namespace' => 'App\\Policies',
             'uses' => 
            array (
              'userrole' => 'App\\Enums\\UserRole',
              'appointment' => 'App\\Models\\Appointment',
              'user' => 'App\\Models\\User',
            ),
             'constUses' => 
            array (
            ),
          )),
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'bool',
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'actor',
               'type' => 'App\\Models\\User',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
            1 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'appointment',
               'type' => 'App\\Models\\Appointment',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
        8 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'start',
           'phpDoc' => 
          \PHPStan\Dependency\ExportedNode\ExportedPhpDocNode::__set_state(array(
             'phpDocString' => '/**
     * Progressing an appointment into the chair is open to front desk or the treating dentist
     * for their own appointment — IDOR check via `$actor->is($appointment->dentist)` prevents
     * one dentist from marking another dentist\'s patient as seen (design doc §14/§18).
     */',
             'namespace' => 'App\\Policies',
             'uses' => 
            array (
              'userrole' => 'App\\Enums\\UserRole',
              'appointment' => 'App\\Models\\Appointment',
              'user' => 'App\\Models\\User',
            ),
             'constUses' => 
            array (
            ),
          )),
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'bool',
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'actor',
               'type' => 'App\\Models\\User',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
            1 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'appointment',
               'type' => 'App\\Models\\Appointment',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
        9 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'complete',
           'phpDoc' => 
          \PHPStan\Dependency\ExportedNode\ExportedPhpDocNode::__set_state(array(
             'phpDocString' => '/**
     * Same rule as `start` (design doc §14/§18).
     */',
             'namespace' => 'App\\Policies',
             'uses' => 
            array (
              'userrole' => 'App\\Enums\\UserRole',
              'appointment' => 'App\\Models\\Appointment',
              'user' => 'App\\Models\\User',
            ),
             'constUses' => 
            array (
            ),
          )),
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'bool',
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'actor',
               'type' => 'App\\Models\\User',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
            1 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'appointment',
               'type' => 'App\\Models\\Appointment',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
        10 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'delete',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'bool',
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'actor',
               'type' => 'App\\Models\\User',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
            1 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'appointment',
               'type' => 'App\\Models\\Appointment',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
      ),
       'attributes' => 
      array (
      ),
    )),
  ),
  '/var/www/html/app/Policies/AppointmentTypePolicy.php' => 
  array (
    0 => 
    \PHPStan\Dependency\ExportedNode\ExportedClassNode::__set_state(array(
       'name' => 'App\\Policies\\AppointmentTypePolicy',
       'phpDoc' => NULL,
       'abstract' => false,
       'final' => false,
       'extends' => NULL,
       'implements' => 
      array (
      ),
       'usedTraits' => 
      array (
      ),
       'traitUseAdaptations' => 
      array (
      ),
       'statements' => 
      array (
        0 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'viewAny',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'bool',
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'actor',
               'type' => 'App\\Models\\User',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
        1 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'view',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'bool',
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'actor',
               'type' => 'App\\Models\\User',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
            1 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'appointmentType',
               'type' => 'App\\Models\\AppointmentType',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
        2 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'create',
           'phpDoc' => 
          \PHPStan\Dependency\ExportedNode\ExportedPhpDocNode::__set_state(array(
             'phpDocString' => '/**
     * Appointment types are clinic configuration — admin only (design doc §14).
     */',
             'namespace' => 'App\\Policies',
             'uses' => 
            array (
              'appointmenttype' => 'App\\Models\\AppointmentType',
              'user' => 'App\\Models\\User',
            ),
             'constUses' => 
            array (
            ),
          )),
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'bool',
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'actor',
               'type' => 'App\\Models\\User',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
        3 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'update',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'bool',
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'actor',
               'type' => 'App\\Models\\User',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
            1 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'appointmentType',
               'type' => 'App\\Models\\AppointmentType',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
        4 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'delete',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'bool',
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'actor',
               'type' => 'App\\Models\\User',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
            1 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'appointmentType',
               'type' => 'App\\Models\\AppointmentType',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
      ),
       'attributes' => 
      array (
      ),
    )),
  ),
  '/var/www/html/app/Policies/DentistTimeOffPolicy.php' => 
  array (
    0 => 
    \PHPStan\Dependency\ExportedNode\ExportedClassNode::__set_state(array(
       'name' => 'App\\Policies\\DentistTimeOffPolicy',
       'phpDoc' => NULL,
       'abstract' => false,
       'final' => false,
       'extends' => NULL,
       'implements' => 
      array (
      ),
       'usedTraits' => 
      array (
      ),
       'traitUseAdaptations' => 
      array (
      ),
       'statements' => 
      array (
        0 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'viewAny',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'bool',
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'actor',
               'type' => 'App\\Models\\User',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
        1 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'view',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'bool',
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'actor',
               'type' => 'App\\Models\\User',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
            1 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'timeOff',
               'type' => 'App\\Models\\DentistTimeOff',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
        2 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'create',
           'phpDoc' => 
          \PHPStan\Dependency\ExportedNode\ExportedPhpDocNode::__set_state(array(
             'phpDocString' => '/**
     * Time-off is self-service: a dentist can manage their own; admin can manage anyone\'s
     * (design doc §6) — routine requests shouldn\'t need an admin as a bottleneck.
     */',
             'namespace' => 'App\\Policies',
             'uses' => 
            array (
              'userrole' => 'App\\Enums\\UserRole',
              'dentisttimeoff' => 'App\\Models\\DentistTimeOff',
              'user' => 'App\\Models\\User',
            ),
             'constUses' => 
            array (
            ),
          )),
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'bool',
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'actor',
               'type' => 'App\\Models\\User',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
            1 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'dentist',
               'type' => 'App\\Models\\User',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
        3 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'delete',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'bool',
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'actor',
               'type' => 'App\\Models\\User',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
            1 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'timeOff',
               'type' => 'App\\Models\\DentistTimeOff',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
      ),
       'attributes' => 
      array (
      ),
    )),
  ),
  '/var/www/html/app/Policies/DentistWorkingHourPolicy.php' => 
  array (
    0 => 
    \PHPStan\Dependency\ExportedNode\ExportedClassNode::__set_state(array(
       'name' => 'App\\Policies\\DentistWorkingHourPolicy',
       'phpDoc' => NULL,
       'abstract' => false,
       'final' => false,
       'extends' => NULL,
       'implements' => 
      array (
      ),
       'usedTraits' => 
      array (
      ),
       'traitUseAdaptations' => 
      array (
      ),
       'statements' => 
      array (
        0 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'viewAny',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'bool',
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'actor',
               'type' => 'App\\Models\\User',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
        1 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'view',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'bool',
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'actor',
               'type' => 'App\\Models\\User',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
            1 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'workingHour',
               'type' => 'App\\Models\\DentistWorkingHour',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
        2 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'create',
           'phpDoc' => 
          \PHPStan\Dependency\ExportedNode\ExportedPhpDocNode::__set_state(array(
             'phpDocString' => '/**
     * The recurring weekly template is admin-only, for any dentist (design doc §6) —
     * prevents a dentist from silently expanding their own booking window.
     */',
             'namespace' => 'App\\Policies',
             'uses' => 
            array (
              'userrole' => 'App\\Enums\\UserRole',
              'dentistworkinghour' => 'App\\Models\\DentistWorkingHour',
              'user' => 'App\\Models\\User',
            ),
             'constUses' => 
            array (
            ),
          )),
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'bool',
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'actor',
               'type' => 'App\\Models\\User',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
            1 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'dentist',
               'type' => 'App\\Models\\User',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
        3 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'delete',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'bool',
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'actor',
               'type' => 'App\\Models\\User',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
            1 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'workingHour',
               'type' => 'App\\Models\\DentistWorkingHour',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
      ),
       'attributes' => 
      array (
      ),
    )),
  ),
  '/var/www/html/app/Policies/PatientPolicy.php' => 
  array (
    0 => 
    \PHPStan\Dependency\ExportedNode\ExportedClassNode::__set_state(array(
       'name' => 'App\\Policies\\PatientPolicy',
       'phpDoc' => NULL,
       'abstract' => false,
       'final' => false,
       'extends' => NULL,
       'implements' => 
      array (
      ),
       'usedTraits' => 
      array (
      ),
       'traitUseAdaptations' => 
      array (
      ),
       'statements' => 
      array (
        0 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'viewAny',
           'phpDoc' => 
          \PHPStan\Dependency\ExportedNode\ExportedPhpDocNode::__set_state(array(
             'phpDocString' => '/**
     * Any authenticated staff member can look up patients.
     */',
             'namespace' => 'App\\Policies',
             'uses' => 
            array (
              'userrole' => 'App\\Enums\\UserRole',
              'patient' => 'App\\Models\\Patient',
              'user' => 'App\\Models\\User',
            ),
             'constUses' => 
            array (
            ),
          )),
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'bool',
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'actor',
               'type' => 'App\\Models\\User',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
        1 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'view',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'bool',
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'actor',
               'type' => 'App\\Models\\User',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
            1 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'patient',
               'type' => 'App\\Models\\Patient',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
        2 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'create',
           'phpDoc' => 
          \PHPStan\Dependency\ExportedNode\ExportedPhpDocNode::__set_state(array(
             'phpDocString' => '/**
     * Registering/editing patient records is front-desk work (admin, receptionist).
     * Dentists get read access here; clinical write access belongs to the future
     * Clinical Notes module rather than this one.
     */',
             'namespace' => 'App\\Policies',
             'uses' => 
            array (
              'userrole' => 'App\\Enums\\UserRole',
              'patient' => 'App\\Models\\Patient',
              'user' => 'App\\Models\\User',
            ),
             'constUses' => 
            array (
            ),
          )),
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'bool',
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'actor',
               'type' => 'App\\Models\\User',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
        3 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'update',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'bool',
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'actor',
               'type' => 'App\\Models\\User',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
            1 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'patient',
               'type' => 'App\\Models\\Patient',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
        4 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'delete',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'bool',
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'actor',
               'type' => 'App\\Models\\User',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
            1 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'patient',
               'type' => 'App\\Models\\Patient',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
        5 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'viewAuditLogs',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'bool',
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'actor',
               'type' => 'App\\Models\\User',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
      ),
       'attributes' => 
      array (
      ),
    )),
  ),
  '/var/www/html/app/Policies/UserPolicy.php' => 
  array (
    0 => 
    \PHPStan\Dependency\ExportedNode\ExportedClassNode::__set_state(array(
       'name' => 'App\\Policies\\UserPolicy',
       'phpDoc' => NULL,
       'abstract' => false,
       'final' => false,
       'extends' => NULL,
       'implements' => 
      array (
      ),
       'usedTraits' => 
      array (
      ),
       'traitUseAdaptations' => 
      array (
      ),
       'statements' => 
      array (
        0 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'viewAny',
           'phpDoc' => 
          \PHPStan\Dependency\ExportedNode\ExportedPhpDocNode::__set_state(array(
             'phpDocString' => '/**
     * Any authenticated staff member can see who else works at the clinic.
     */',
             'namespace' => 'App\\Policies',
             'uses' => 
            array (
              'user' => 'App\\Models\\User',
            ),
             'constUses' => 
            array (
            ),
          )),
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'bool',
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'actor',
               'type' => 'App\\Models\\User',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
        1 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'view',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'bool',
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'actor',
               'type' => 'App\\Models\\User',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
            1 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'target',
               'type' => 'App\\Models\\User',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
        2 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'create',
           'phpDoc' => 
          \PHPStan\Dependency\ExportedNode\ExportedPhpDocNode::__set_state(array(
             'phpDocString' => '/**
     * Managing accounts (creating, editing, deleting) is restricted to admins.
     */',
             'namespace' => 'App\\Policies',
             'uses' => 
            array (
              'user' => 'App\\Models\\User',
            ),
             'constUses' => 
            array (
            ),
          )),
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'bool',
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'actor',
               'type' => 'App\\Models\\User',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
        3 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'update',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'bool',
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'actor',
               'type' => 'App\\Models\\User',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
            1 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'target',
               'type' => 'App\\Models\\User',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
        4 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'delete',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'bool',
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'actor',
               'type' => 'App\\Models\\User',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
            1 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'target',
               'type' => 'App\\Models\\User',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
      ),
       'attributes' => 
      array (
      ),
    )),
  ),
  '/var/www/html/app/Providers/AppServiceProvider.php' => 
  array (
    0 => 
    \PHPStan\Dependency\ExportedNode\ExportedClassNode::__set_state(array(
       'name' => 'App\\Providers\\AppServiceProvider',
       'phpDoc' => NULL,
       'abstract' => false,
       'final' => false,
       'extends' => 'Illuminate\\Support\\ServiceProvider',
       'implements' => 
      array (
      ),
       'usedTraits' => 
      array (
      ),
       'traitUseAdaptations' => 
      array (
      ),
       'statements' => 
      array (
        0 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'register',
           'phpDoc' => 
          \PHPStan\Dependency\ExportedNode\ExportedPhpDocNode::__set_state(array(
             'phpDocString' => '/**
     * Register any application services.
     */',
             'namespace' => 'App\\Providers',
             'uses' => 
            array (
              'jsonresource' => 'Illuminate\\Http\\Resources\\Json\\JsonResource',
              'serviceprovider' => 'Illuminate\\Support\\ServiceProvider',
            ),
             'constUses' => 
            array (
            ),
          )),
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'void',
           'parameters' => 
          array (
          ),
           'attributes' => 
          array (
          ),
        )),
        1 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'boot',
           'phpDoc' => 
          \PHPStan\Dependency\ExportedNode\ExportedPhpDocNode::__set_state(array(
             'phpDocString' => '/**
     * Bootstrap any application services.
     */',
             'namespace' => 'App\\Providers',
             'uses' => 
            array (
              'jsonresource' => 'Illuminate\\Http\\Resources\\Json\\JsonResource',
              'serviceprovider' => 'Illuminate\\Support\\ServiceProvider',
            ),
             'constUses' => 
            array (
            ),
          )),
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'void',
           'parameters' => 
          array (
          ),
           'attributes' => 
          array (
          ),
        )),
      ),
       'attributes' => 
      array (
      ),
    )),
  ),
  '/var/www/html/app/Services/AppointmentService.php' => 
  array (
    0 => 
    \PHPStan\Dependency\ExportedNode\ExportedClassNode::__set_state(array(
       'name' => 'App\\Services\\AppointmentService',
       'phpDoc' => NULL,
       'abstract' => false,
       'final' => false,
       'extends' => NULL,
       'implements' => 
      array (
      ),
       'usedTraits' => 
      array (
      ),
       'traitUseAdaptations' => 
      array (
      ),
       'statements' => 
      array (
        0 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'availableSlots',
           'phpDoc' => 
          \PHPStan\Dependency\ExportedNode\ExportedPhpDocNode::__set_state(array(
             'phpDocString' => '/**
     * Computes candidate appointment start times for one dentist on one calendar day: working
     * hours minus existing occupying appointments minus time-off, sliced at the configured
     * granularity (design doc §6/§9). Pure read — never mutates anything.
     *
     * @return list<Carbon>
     */',
             'namespace' => 'App\\Services',
             'uses' => 
            array (
              'appointmentstatus' => 'App\\Enums\\AppointmentStatus',
              'dentistconflictexception' => 'App\\Exceptions\\Appointments\\DentistConflictException',
              'earlynoshowexception' => 'App\\Exceptions\\Appointments\\EarlyNoShowException',
              'invalidstatustransitionexception' => 'App\\Exceptions\\Appointments\\InvalidStatusTransitionException',
              'outsideworkinghoursexception' => 'App\\Exceptions\\Appointments\\OutsideWorkingHoursException',
              'patientconflictexception' => 'App\\Exceptions\\Appointments\\PatientConflictException',
              'appointment' => 'App\\Models\\Appointment',
              'dentisttimeoff' => 'App\\Models\\DentistTimeOff',
              'dentistworkinghour' => 'App\\Models\\DentistWorkingHour',
              'carbon' => 'Carbon\\Carbon',
              'carboninterface' => 'Carbon\\CarbonInterface',
              'queryexception' => 'Illuminate\\Database\\QueryException',
              'collection' => 'Illuminate\\Support\\Collection',
              'auth' => 'Illuminate\\Support\\Facades\\Auth',
              'db' => 'Illuminate\\Support\\Facades\\DB',
            ),
             'constUses' => 
            array (
            ),
          )),
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'array',
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'dentistId',
               'type' => 'string',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
            1 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'date',
               'type' => 'Carbon\\CarbonInterface',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
            2 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'durationMinutes',
               'type' => 'int',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
        1 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'dentistHasConflict',
           'phpDoc' => 
          \PHPStan\Dependency\ExportedNode\ExportedPhpDocNode::__set_state(array(
             'phpDocString' => '/**
     * Hard-block check (design doc §5.3, §10): true overlap against occupying-status
     * appointments for this dentist, excluding the appointment being rescheduled (if any).
     */',
             'namespace' => 'App\\Services',
             'uses' => 
            array (
              'appointmentstatus' => 'App\\Enums\\AppointmentStatus',
              'dentistconflictexception' => 'App\\Exceptions\\Appointments\\DentistConflictException',
              'earlynoshowexception' => 'App\\Exceptions\\Appointments\\EarlyNoShowException',
              'invalidstatustransitionexception' => 'App\\Exceptions\\Appointments\\InvalidStatusTransitionException',
              'outsideworkinghoursexception' => 'App\\Exceptions\\Appointments\\OutsideWorkingHoursException',
              'patientconflictexception' => 'App\\Exceptions\\Appointments\\PatientConflictException',
              'appointment' => 'App\\Models\\Appointment',
              'dentisttimeoff' => 'App\\Models\\DentistTimeOff',
              'dentistworkinghour' => 'App\\Models\\DentistWorkingHour',
              'carbon' => 'Carbon\\Carbon',
              'carboninterface' => 'Carbon\\CarbonInterface',
              'queryexception' => 'Illuminate\\Database\\QueryException',
              'collection' => 'Illuminate\\Support\\Collection',
              'auth' => 'Illuminate\\Support\\Facades\\Auth',
              'db' => 'Illuminate\\Support\\Facades\\DB',
            ),
             'constUses' => 
            array (
            ),
          )),
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'bool',
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'dentistId',
               'type' => 'string',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
            1 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'startAt',
               'type' => 'Carbon\\CarbonInterface',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
            2 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'endAt',
               'type' => 'Carbon\\CarbonInterface',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
            3 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'excludeAppointmentId',
               'type' => '?string',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => true,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
        2 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'patientHasConflict',
           'phpDoc' => 
          \PHPStan\Dependency\ExportedNode\ExportedPhpDocNode::__set_state(array(
             'phpDocString' => '/**
     * Soft-warning check (design doc §5.4): true overlap only, not merely another appointment
     * the same day.
     */',
             'namespace' => 'App\\Services',
             'uses' => 
            array (
              'appointmentstatus' => 'App\\Enums\\AppointmentStatus',
              'dentistconflictexception' => 'App\\Exceptions\\Appointments\\DentistConflictException',
              'earlynoshowexception' => 'App\\Exceptions\\Appointments\\EarlyNoShowException',
              'invalidstatustransitionexception' => 'App\\Exceptions\\Appointments\\InvalidStatusTransitionException',
              'outsideworkinghoursexception' => 'App\\Exceptions\\Appointments\\OutsideWorkingHoursException',
              'patientconflictexception' => 'App\\Exceptions\\Appointments\\PatientConflictException',
              'appointment' => 'App\\Models\\Appointment',
              'dentisttimeoff' => 'App\\Models\\DentistTimeOff',
              'dentistworkinghour' => 'App\\Models\\DentistWorkingHour',
              'carbon' => 'Carbon\\Carbon',
              'carboninterface' => 'Carbon\\CarbonInterface',
              'queryexception' => 'Illuminate\\Database\\QueryException',
              'collection' => 'Illuminate\\Support\\Collection',
              'auth' => 'Illuminate\\Support\\Facades\\Auth',
              'db' => 'Illuminate\\Support\\Facades\\DB',
            ),
             'constUses' => 
            array (
            ),
          )),
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'bool',
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'patientId',
               'type' => 'string',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
            1 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'startAt',
               'type' => 'Carbon\\CarbonInterface',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
            2 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'endAt',
               'type' => 'Carbon\\CarbonInterface',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
            3 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'excludeAppointmentId',
               'type' => '?string',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => true,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
        3 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'isOutsideWorkingHours',
           'phpDoc' => 
          \PHPStan\Dependency\ExportedNode\ExportedPhpDocNode::__set_state(array(
             'phpDocString' => '/**
     * Soft-warning check (design doc §5.5): outside the dentist\'s active working-hours ranges
     * for that day, or overlapping a time-off entry.
     */',
             'namespace' => 'App\\Services',
             'uses' => 
            array (
              'appointmentstatus' => 'App\\Enums\\AppointmentStatus',
              'dentistconflictexception' => 'App\\Exceptions\\Appointments\\DentistConflictException',
              'earlynoshowexception' => 'App\\Exceptions\\Appointments\\EarlyNoShowException',
              'invalidstatustransitionexception' => 'App\\Exceptions\\Appointments\\InvalidStatusTransitionException',
              'outsideworkinghoursexception' => 'App\\Exceptions\\Appointments\\OutsideWorkingHoursException',
              'patientconflictexception' => 'App\\Exceptions\\Appointments\\PatientConflictException',
              'appointment' => 'App\\Models\\Appointment',
              'dentisttimeoff' => 'App\\Models\\DentistTimeOff',
              'dentistworkinghour' => 'App\\Models\\DentistWorkingHour',
              'carbon' => 'Carbon\\Carbon',
              'carboninterface' => 'Carbon\\CarbonInterface',
              'queryexception' => 'Illuminate\\Database\\QueryException',
              'collection' => 'Illuminate\\Support\\Collection',
              'auth' => 'Illuminate\\Support\\Facades\\Auth',
              'db' => 'Illuminate\\Support\\Facades\\DB',
            ),
             'constUses' => 
            array (
            ),
          )),
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'bool',
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'dentistId',
               'type' => 'string',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
            1 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'startAt',
               'type' => 'Carbon\\CarbonInterface',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
            2 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'endAt',
               'type' => 'Carbon\\CarbonInterface',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
        4 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'search',
           'phpDoc' => 
          \PHPStan\Dependency\ExportedNode\ExportedPhpDocNode::__set_state(array(
             'phpDocString' => '/**
     * Date-range-bounded list (design doc §16/§19) — deliberately not paginated, same
     * exemption as the Dashboard summary endpoint: a calendar-range query is naturally bounded
     * (a week of one clinic\'s appointments is at most a few hundred rows), so classic
     * pagination would only get in the way of a board/list view that wants the whole range at
     * once. `date_from`/`date_to` are treated as whole-day boundaries so a plain `YYYY-MM-DD`
     * filter (the common board-view case) captures the entire day, not just midnight.
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, Appointment>
     */',
             'namespace' => 'App\\Services',
             'uses' => 
            array (
              'appointmentstatus' => 'App\\Enums\\AppointmentStatus',
              'dentistconflictexception' => 'App\\Exceptions\\Appointments\\DentistConflictException',
              'earlynoshowexception' => 'App\\Exceptions\\Appointments\\EarlyNoShowException',
              'invalidstatustransitionexception' => 'App\\Exceptions\\Appointments\\InvalidStatusTransitionException',
              'outsideworkinghoursexception' => 'App\\Exceptions\\Appointments\\OutsideWorkingHoursException',
              'patientconflictexception' => 'App\\Exceptions\\Appointments\\PatientConflictException',
              'appointment' => 'App\\Models\\Appointment',
              'dentisttimeoff' => 'App\\Models\\DentistTimeOff',
              'dentistworkinghour' => 'App\\Models\\DentistWorkingHour',
              'carbon' => 'Carbon\\Carbon',
              'carboninterface' => 'Carbon\\CarbonInterface',
              'queryexception' => 'Illuminate\\Database\\QueryException',
              'collection' => 'Illuminate\\Support\\Collection',
              'auth' => 'Illuminate\\Support\\Facades\\Auth',
              'db' => 'Illuminate\\Support\\Facades\\DB',
            ),
             'constUses' => 
            array (
            ),
          )),
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'Illuminate\\Support\\Collection',
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'filters',
               'type' => 'array',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
        5 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'delete',
           'phpDoc' => 
          \PHPStan\Dependency\ExportedNode\ExportedPhpDocNode::__set_state(array(
             'phpDocString' => '/**
     * Mirrors PatientService::delete()\'s existence for controller-calls-one-Service-method
     * consistency, even though it\'s a one-liner today. Soft delete only — reserved for pure
     * data-entry mistakes (§4); cancellation is a separate, non-destructive status transition.
     */',
             'namespace' => 'App\\Services',
             'uses' => 
            array (
              'appointmentstatus' => 'App\\Enums\\AppointmentStatus',
              'dentistconflictexception' => 'App\\Exceptions\\Appointments\\DentistConflictException',
              'earlynoshowexception' => 'App\\Exceptions\\Appointments\\EarlyNoShowException',
              'invalidstatustransitionexception' => 'App\\Exceptions\\Appointments\\InvalidStatusTransitionException',
              'outsideworkinghoursexception' => 'App\\Exceptions\\Appointments\\OutsideWorkingHoursException',
              'patientconflictexception' => 'App\\Exceptions\\Appointments\\PatientConflictException',
              'appointment' => 'App\\Models\\Appointment',
              'dentisttimeoff' => 'App\\Models\\DentistTimeOff',
              'dentistworkinghour' => 'App\\Models\\DentistWorkingHour',
              'carbon' => 'Carbon\\Carbon',
              'carboninterface' => 'Carbon\\CarbonInterface',
              'queryexception' => 'Illuminate\\Database\\QueryException',
              'collection' => 'Illuminate\\Support\\Collection',
              'auth' => 'Illuminate\\Support\\Facades\\Auth',
              'db' => 'Illuminate\\Support\\Facades\\DB',
            ),
             'constUses' => 
            array (
            ),
          )),
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'void',
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'appointment',
               'type' => 'App\\Models\\Appointment',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
        6 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'create',
           'phpDoc' => 
          \PHPStan\Dependency\ExportedNode\ExportedPhpDocNode::__set_state(array(
             'phpDocString' => '/**
     * Appointment creation workflow (design doc §5, §10): validates availability/conflicts,
     * then creates. Never receives `end_at` or `status` from the caller — both are always
     * computed here. Audit logging is automatic via Appointment\'s Auditable trait.
     *
     * @param  array<string, mixed>  $data
     */',
             'namespace' => 'App\\Services',
             'uses' => 
            array (
              'appointmentstatus' => 'App\\Enums\\AppointmentStatus',
              'dentistconflictexception' => 'App\\Exceptions\\Appointments\\DentistConflictException',
              'earlynoshowexception' => 'App\\Exceptions\\Appointments\\EarlyNoShowException',
              'invalidstatustransitionexception' => 'App\\Exceptions\\Appointments\\InvalidStatusTransitionException',
              'outsideworkinghoursexception' => 'App\\Exceptions\\Appointments\\OutsideWorkingHoursException',
              'patientconflictexception' => 'App\\Exceptions\\Appointments\\PatientConflictException',
              'appointment' => 'App\\Models\\Appointment',
              'dentisttimeoff' => 'App\\Models\\DentistTimeOff',
              'dentistworkinghour' => 'App\\Models\\DentistWorkingHour',
              'carbon' => 'Carbon\\Carbon',
              'carboninterface' => 'Carbon\\CarbonInterface',
              'queryexception' => 'Illuminate\\Database\\QueryException',
              'collection' => 'Illuminate\\Support\\Collection',
              'auth' => 'Illuminate\\Support\\Facades\\Auth',
              'db' => 'Illuminate\\Support\\Facades\\DB',
            ),
             'constUses' => 
            array (
            ),
          )),
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'App\\Models\\Appointment',
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'data',
               'type' => 'array',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
        7 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'reschedule',
           'phpDoc' => 
          \PHPStan\Dependency\ExportedNode\ExportedPhpDocNode::__set_state(array(
             'phpDocString' => '/**
     * In-place reschedule (design doc §11) — never creates a new row; re-runs full
     * availability/conflict checks only when the slot actually moves (`dentist_id`, `start_at`,
     * or `duration_minutes` changed). `reschedule_count` only increments when the dentist or
     * start time actually changes, matching the doc\'s definition of "reschedule". Status is
     * left untouched. Old → new values are captured automatically by the Auditable trait.
     *
     * @param  array<string, mixed>  $data
     */',
             'namespace' => 'App\\Services',
             'uses' => 
            array (
              'appointmentstatus' => 'App\\Enums\\AppointmentStatus',
              'dentistconflictexception' => 'App\\Exceptions\\Appointments\\DentistConflictException',
              'earlynoshowexception' => 'App\\Exceptions\\Appointments\\EarlyNoShowException',
              'invalidstatustransitionexception' => 'App\\Exceptions\\Appointments\\InvalidStatusTransitionException',
              'outsideworkinghoursexception' => 'App\\Exceptions\\Appointments\\OutsideWorkingHoursException',
              'patientconflictexception' => 'App\\Exceptions\\Appointments\\PatientConflictException',
              'appointment' => 'App\\Models\\Appointment',
              'dentisttimeoff' => 'App\\Models\\DentistTimeOff',
              'dentistworkinghour' => 'App\\Models\\DentistWorkingHour',
              'carbon' => 'Carbon\\Carbon',
              'carboninterface' => 'Carbon\\CarbonInterface',
              'queryexception' => 'Illuminate\\Database\\QueryException',
              'collection' => 'Illuminate\\Support\\Collection',
              'auth' => 'Illuminate\\Support\\Facades\\Auth',
              'db' => 'Illuminate\\Support\\Facades\\DB',
            ),
             'constUses' => 
            array (
            ),
          )),
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'App\\Models\\Appointment',
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'appointment',
               'type' => 'App\\Models\\Appointment',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
            1 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'data',
               'type' => 'array',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
        8 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'cancel',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'App\\Models\\Appointment',
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'appointment',
               'type' => 'App\\Models\\Appointment',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
            1 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'cancellationReason',
               'type' => '?string',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
        9 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'markNoShow',
           'phpDoc' => 
          \PHPStan\Dependency\ExportedNode\ExportedPhpDocNode::__set_state(array(
             'phpDocString' => '/**
     * @throws EarlyNoShowException if `start_at` hasn\'t passed yet and `$overrideEarly` is false
     *                              (soft rule, design doc §5.9/§13).
     */',
             'namespace' => 'App\\Services',
             'uses' => 
            array (
              'appointmentstatus' => 'App\\Enums\\AppointmentStatus',
              'dentistconflictexception' => 'App\\Exceptions\\Appointments\\DentistConflictException',
              'earlynoshowexception' => 'App\\Exceptions\\Appointments\\EarlyNoShowException',
              'invalidstatustransitionexception' => 'App\\Exceptions\\Appointments\\InvalidStatusTransitionException',
              'outsideworkinghoursexception' => 'App\\Exceptions\\Appointments\\OutsideWorkingHoursException',
              'patientconflictexception' => 'App\\Exceptions\\Appointments\\PatientConflictException',
              'appointment' => 'App\\Models\\Appointment',
              'dentisttimeoff' => 'App\\Models\\DentistTimeOff',
              'dentistworkinghour' => 'App\\Models\\DentistWorkingHour',
              'carbon' => 'Carbon\\Carbon',
              'carboninterface' => 'Carbon\\CarbonInterface',
              'queryexception' => 'Illuminate\\Database\\QueryException',
              'collection' => 'Illuminate\\Support\\Collection',
              'auth' => 'Illuminate\\Support\\Facades\\Auth',
              'db' => 'Illuminate\\Support\\Facades\\DB',
            ),
             'constUses' => 
            array (
            ),
          )),
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'App\\Models\\Appointment',
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'appointment',
               'type' => 'App\\Models\\Appointment',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
            1 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'overrideEarly',
               'type' => 'bool',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => true,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
        10 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'confirm',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'App\\Models\\Appointment',
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'appointment',
               'type' => 'App\\Models\\Appointment',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
        11 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'checkIn',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'App\\Models\\Appointment',
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'appointment',
               'type' => 'App\\Models\\Appointment',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
        12 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'start',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'App\\Models\\Appointment',
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'appointment',
               'type' => 'App\\Models\\Appointment',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
        13 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'complete',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'App\\Models\\Appointment',
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'appointment',
               'type' => 'App\\Models\\Appointment',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
      ),
       'attributes' => 
      array (
      ),
    )),
  ),
  '/var/www/html/app/Services/AppointmentTypeService.php' => 
  array (
    0 => 
    \PHPStan\Dependency\ExportedNode\ExportedClassNode::__set_state(array(
       'name' => 'App\\Services\\AppointmentTypeService',
       'phpDoc' => NULL,
       'abstract' => false,
       'final' => false,
       'extends' => NULL,
       'implements' => 
      array (
      ),
       'usedTraits' => 
      array (
      ),
       'traitUseAdaptations' => 
      array (
      ),
       'statements' => 
      array (
        0 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'list',
           'phpDoc' => 
          \PHPStan\Dependency\ExportedNode\ExportedPhpDocNode::__set_state(array(
             'phpDocString' => '/**
     * Returns every type (active or not), ordered by name — deliberately not paginated. This
     * is a small clinic-configured lookup table consumed as a dropdown in the booking UI, and
     * paginating it would break that UX (same category of deliberate exemption as the
     * date-range-bounded appointments list in `AppointmentService::search()`).
     *
     * @return Collection<int, AppointmentType>
     */',
             'namespace' => 'App\\Services',
             'uses' => 
            array (
              'appointmenttype' => 'App\\Models\\AppointmentType',
              'collection' => 'Illuminate\\Support\\Collection',
            ),
             'constUses' => 
            array (
            ),
          )),
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'Illuminate\\Support\\Collection',
           'parameters' => 
          array (
          ),
           'attributes' => 
          array (
          ),
        )),
        1 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'create',
           'phpDoc' => 
          \PHPStan\Dependency\ExportedNode\ExportedPhpDocNode::__set_state(array(
             'phpDocString' => '/**
     * @param  array<string, mixed>  $data
     */',
             'namespace' => 'App\\Services',
             'uses' => 
            array (
              'appointmenttype' => 'App\\Models\\AppointmentType',
              'collection' => 'Illuminate\\Support\\Collection',
            ),
             'constUses' => 
            array (
            ),
          )),
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'App\\Models\\AppointmentType',
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'data',
               'type' => 'array',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
        2 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'update',
           'phpDoc' => 
          \PHPStan\Dependency\ExportedNode\ExportedPhpDocNode::__set_state(array(
             'phpDocString' => '/**
     * @param  array<string, mixed>  $data
     */',
             'namespace' => 'App\\Services',
             'uses' => 
            array (
              'appointmenttype' => 'App\\Models\\AppointmentType',
              'collection' => 'Illuminate\\Support\\Collection',
            ),
             'constUses' => 
            array (
            ),
          )),
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'App\\Models\\AppointmentType',
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'type',
               'type' => 'App\\Models\\AppointmentType',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
            1 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'data',
               'type' => 'array',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
        3 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'delete',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'void',
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'type',
               'type' => 'App\\Models\\AppointmentType',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
      ),
       'attributes' => 
      array (
      ),
    )),
  ),
  '/var/www/html/app/Services/AuditLogService.php' => 
  array (
    0 => 
    \PHPStan\Dependency\ExportedNode\ExportedClassNode::__set_state(array(
       'name' => 'App\\Services\\AuditLogService',
       'phpDoc' => NULL,
       'abstract' => false,
       'final' => false,
       'extends' => NULL,
       'implements' => 
      array (
      ),
       'usedTraits' => 
      array (
      ),
       'traitUseAdaptations' => 
      array (
      ),
       'statements' => 
      array (
        0 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'record',
           'phpDoc' => 
          \PHPStan\Dependency\ExportedNode\ExportedPhpDocNode::__set_state(array(
             'phpDocString' => '/**
     * @param  array<string, mixed>  $changes
     */',
             'namespace' => 'App\\Services',
             'uses' => 
            array (
              'auditlog' => 'App\\Models\\AuditLog',
              'model' => 'Illuminate\\Database\\Eloquent\\Model',
              'auth' => 'Illuminate\\Support\\Facades\\Auth',
            ),
             'constUses' => 
            array (
            ),
          )),
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'void',
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'model',
               'type' => 'Illuminate\\Database\\Eloquent\\Model',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
            1 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'action',
               'type' => 'string',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
            2 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'changes',
               'type' => 'array',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
      ),
       'attributes' => 
      array (
      ),
    )),
  ),
  '/var/www/html/app/Services/AuthService.php' => 
  array (
    0 => 
    \PHPStan\Dependency\ExportedNode\ExportedClassNode::__set_state(array(
       'name' => 'App\\Services\\AuthService',
       'phpDoc' => NULL,
       'abstract' => false,
       'final' => false,
       'extends' => NULL,
       'implements' => 
      array (
      ),
       'usedTraits' => 
      array (
      ),
       'traitUseAdaptations' => 
      array (
      ),
       'statements' => 
      array (
        0 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'login',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'App\\Models\\User',
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'request',
               'type' => 'Illuminate\\Http\\Request',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
            1 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'throttleKey',
               'type' => 'string',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
            2 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'email',
               'type' => 'string',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
            3 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'password',
               'type' => 'string',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
        1 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'logout',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'void',
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'request',
               'type' => 'Illuminate\\Http\\Request',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
      ),
       'attributes' => 
      array (
      ),
    )),
  ),
  '/var/www/html/app/Services/DashboardService.php' => 
  array (
    0 => 
    \PHPStan\Dependency\ExportedNode\ExportedClassNode::__set_state(array(
       'name' => 'App\\Services\\DashboardService',
       'phpDoc' => NULL,
       'abstract' => false,
       'final' => false,
       'extends' => NULL,
       'implements' => 
      array (
      ),
       'usedTraits' => 
      array (
      ),
       'traitUseAdaptations' => 
      array (
      ),
       'statements' => 
      array (
        0 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'summary',
           'phpDoc' => 
          \PHPStan\Dependency\ExportedNode\ExportedPhpDocNode::__set_state(array(
             'phpDocString' => '/**
     * Summary stat cards for the main dashboard.
     * Falls back to 0 for modules that are not implemented yet.
     */',
             'namespace' => 'App\\Services',
             'uses' => 
            array (
              'db' => 'Illuminate\\Support\\Facades\\DB',
              'schema' => 'Illuminate\\Support\\Facades\\Schema',
            ),
             'constUses' => 
            array (
            ),
          )),
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'array',
           'parameters' => 
          array (
          ),
           'attributes' => 
          array (
          ),
        )),
      ),
       'attributes' => 
      array (
      ),
    )),
  ),
  '/var/www/html/app/Services/DentistTimeOffService.php' => 
  array (
    0 => 
    \PHPStan\Dependency\ExportedNode\ExportedClassNode::__set_state(array(
       'name' => 'App\\Services\\DentistTimeOffService',
       'phpDoc' => NULL,
       'abstract' => false,
       'final' => false,
       'extends' => NULL,
       'implements' => 
      array (
      ),
       'usedTraits' => 
      array (
      ),
       'traitUseAdaptations' => 
      array (
      ),
       'statements' => 
      array (
        0 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'listForDentist',
           'phpDoc' => 
          \PHPStan\Dependency\ExportedNode\ExportedPhpDocNode::__set_state(array(
             'phpDocString' => '/**
     * @return Collection<int, DentistTimeOff>
     */',
             'namespace' => 'App\\Services',
             'uses' => 
            array (
              'dentisttimeoff' => 'App\\Models\\DentistTimeOff',
              'user' => 'App\\Models\\User',
              'collection' => 'Illuminate\\Support\\Collection',
            ),
             'constUses' => 
            array (
            ),
          )),
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'Illuminate\\Support\\Collection',
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'dentistId',
               'type' => 'string',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
        1 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'create',
           'phpDoc' => 
          \PHPStan\Dependency\ExportedNode\ExportedPhpDocNode::__set_state(array(
             'phpDocString' => '/**
     * @param  array<string, mixed>  $data
     */',
             'namespace' => 'App\\Services',
             'uses' => 
            array (
              'dentisttimeoff' => 'App\\Models\\DentistTimeOff',
              'user' => 'App\\Models\\User',
              'collection' => 'Illuminate\\Support\\Collection',
            ),
             'constUses' => 
            array (
            ),
          )),
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'App\\Models\\DentistTimeOff',
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'dentist',
               'type' => 'App\\Models\\User',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
            1 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'data',
               'type' => 'array',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
        2 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'delete',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'void',
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'timeOff',
               'type' => 'App\\Models\\DentistTimeOff',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
      ),
       'attributes' => 
      array (
      ),
    )),
  ),
  '/var/www/html/app/Services/DentistWorkingHourService.php' => 
  array (
    0 => 
    \PHPStan\Dependency\ExportedNode\ExportedClassNode::__set_state(array(
       'name' => 'App\\Services\\DentistWorkingHourService',
       'phpDoc' => NULL,
       'abstract' => false,
       'final' => false,
       'extends' => NULL,
       'implements' => 
      array (
      ),
       'usedTraits' => 
      array (
      ),
       'traitUseAdaptations' => 
      array (
      ),
       'statements' => 
      array (
        0 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'listForDentist',
           'phpDoc' => 
          \PHPStan\Dependency\ExportedNode\ExportedPhpDocNode::__set_state(array(
             'phpDocString' => '/**
     * @return Collection<int, DentistWorkingHour>
     */',
             'namespace' => 'App\\Services',
             'uses' => 
            array (
              'dentistworkinghour' => 'App\\Models\\DentistWorkingHour',
              'user' => 'App\\Models\\User',
              'collection' => 'Illuminate\\Support\\Collection',
            ),
             'constUses' => 
            array (
            ),
          )),
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'Illuminate\\Support\\Collection',
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'dentistId',
               'type' => 'string',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
        1 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'create',
           'phpDoc' => 
          \PHPStan\Dependency\ExportedNode\ExportedPhpDocNode::__set_state(array(
             'phpDocString' => '/**
     * @param  array<string, mixed>  $data
     */',
             'namespace' => 'App\\Services',
             'uses' => 
            array (
              'dentistworkinghour' => 'App\\Models\\DentistWorkingHour',
              'user' => 'App\\Models\\User',
              'collection' => 'Illuminate\\Support\\Collection',
            ),
             'constUses' => 
            array (
            ),
          )),
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'App\\Models\\DentistWorkingHour',
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'dentist',
               'type' => 'App\\Models\\User',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
            1 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'data',
               'type' => 'array',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
        2 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'delete',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'void',
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'workingHour',
               'type' => 'App\\Models\\DentistWorkingHour',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
      ),
       'attributes' => 
      array (
      ),
    )),
  ),
  '/var/www/html/app/Services/PatientService.php' => 
  array (
    0 => 
    \PHPStan\Dependency\ExportedNode\ExportedClassNode::__set_state(array(
       'name' => 'App\\Services\\PatientService',
       'phpDoc' => NULL,
       'abstract' => false,
       'final' => false,
       'extends' => NULL,
       'implements' => 
      array (
      ),
       'usedTraits' => 
      array (
      ),
       'traitUseAdaptations' => 
      array (
      ),
       'statements' => 
      array (
        0 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'paginate',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'Illuminate\\Contracts\\Pagination\\LengthAwarePaginator',
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'search',
               'type' => '?string',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => true,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
            1 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'perPage',
               'type' => 'int',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => true,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
        1 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'create',
           'phpDoc' => 
          \PHPStan\Dependency\ExportedNode\ExportedPhpDocNode::__set_state(array(
             'phpDocString' => '/**
     * @param  array<string, mixed>  $data
     */',
             'namespace' => 'App\\Services',
             'uses' => 
            array (
              'patient' => 'App\\Models\\Patient',
              'lengthawarepaginator' => 'Illuminate\\Contracts\\Pagination\\LengthAwarePaginator',
              'db' => 'Illuminate\\Support\\Facades\\DB',
            ),
             'constUses' => 
            array (
            ),
          )),
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'App\\Models\\Patient',
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'data',
               'type' => 'array',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
        2 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'update',
           'phpDoc' => 
          \PHPStan\Dependency\ExportedNode\ExportedPhpDocNode::__set_state(array(
             'phpDocString' => '/**
     * @param  array<string, mixed>  $data
     */',
             'namespace' => 'App\\Services',
             'uses' => 
            array (
              'patient' => 'App\\Models\\Patient',
              'lengthawarepaginator' => 'Illuminate\\Contracts\\Pagination\\LengthAwarePaginator',
              'db' => 'Illuminate\\Support\\Facades\\DB',
            ),
             'constUses' => 
            array (
            ),
          )),
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'App\\Models\\Patient',
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'patient',
               'type' => 'App\\Models\\Patient',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
            1 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'data',
               'type' => 'array',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
        3 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'delete',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'void',
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'patient',
               'type' => 'App\\Models\\Patient',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
      ),
       'attributes' => 
      array (
      ),
    )),
  ),
  '/var/www/html/app/Services/UserService.php' => 
  array (
    0 => 
    \PHPStan\Dependency\ExportedNode\ExportedClassNode::__set_state(array(
       'name' => 'App\\Services\\UserService',
       'phpDoc' => NULL,
       'abstract' => false,
       'final' => false,
       'extends' => NULL,
       'implements' => 
      array (
      ),
       'usedTraits' => 
      array (
      ),
       'traitUseAdaptations' => 
      array (
      ),
       'statements' => 
      array (
        0 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'paginate',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'Illuminate\\Contracts\\Pagination\\LengthAwarePaginator',
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'search',
               'type' => '?string',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => true,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
            1 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'perPage',
               'type' => 'int',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => true,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
        1 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'create',
           'phpDoc' => 
          \PHPStan\Dependency\ExportedNode\ExportedPhpDocNode::__set_state(array(
             'phpDocString' => '/**
     * @param  array{name: string, email: string, password: string, role: UserRole}  $data
     */',
             'namespace' => 'App\\Services',
             'uses' => 
            array (
              'userrole' => 'App\\Enums\\UserRole',
              'user' => 'App\\Models\\User',
              'lengthawarepaginator' => 'Illuminate\\Contracts\\Pagination\\LengthAwarePaginator',
              'hash' => 'Illuminate\\Support\\Facades\\Hash',
            ),
             'constUses' => 
            array (
            ),
          )),
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'App\\Models\\User',
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'data',
               'type' => 'array',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
        2 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'update',
           'phpDoc' => 
          \PHPStan\Dependency\ExportedNode\ExportedPhpDocNode::__set_state(array(
             'phpDocString' => '/**
     * @param  array{name?: string, email?: string, password?: string, role?: UserRole}  $data
     */',
             'namespace' => 'App\\Services',
             'uses' => 
            array (
              'userrole' => 'App\\Enums\\UserRole',
              'user' => 'App\\Models\\User',
              'lengthawarepaginator' => 'Illuminate\\Contracts\\Pagination\\LengthAwarePaginator',
              'hash' => 'Illuminate\\Support\\Facades\\Hash',
            ),
             'constUses' => 
            array (
            ),
          )),
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'App\\Models\\User',
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'user',
               'type' => 'App\\Models\\User',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
            1 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'data',
               'type' => 'array',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
        3 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'delete',
           'phpDoc' => NULL,
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'void',
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'user',
               'type' => 'App\\Models\\User',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
               'phpDoc' => NULL,
               'flags' => 0,
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
      ),
       'attributes' => 
      array (
      ),
    )),
  ),
); },
];
