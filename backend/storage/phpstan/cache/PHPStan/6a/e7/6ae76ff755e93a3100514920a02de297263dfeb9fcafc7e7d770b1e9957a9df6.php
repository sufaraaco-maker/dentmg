<?php declare(strict_types = 1);

// odsl-/var/www/html/app/Exceptions/Appointments/EarlyNoShowException.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Exceptions\Appointments\EarlyNoShowException
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.3-8.4.23-4c42035e1a463cbfbcc5edf3f940ae3fd2011d3f21ff3dabfd247ddb0f36ea58',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Exceptions\\Appointments\\EarlyNoShowException',
        'filename' => '/var/www/html/app/Exceptions/Appointments/EarlyNoShowException.php',
      ),
    ),
    'namespace' => 'App\\Exceptions\\Appointments',
    'name' => 'App\\Exceptions\\Appointments\\EarlyNoShowException',
    'shortName' => 'EarlyNoShowException',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * Soft warning (design doc §5.9/§13): marking no-show before `start_at` has passed is
 * unusual but allowed for staff backfilling records. Overridable via `override_early_no_show`.
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 13,
    'endLine' => 32,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => 'RuntimeException',
    'implementsClassNames' => 
    array (
    ),
    'traitClassNames' => 
    array (
    ),
    'immediateConstants' => 
    array (
    ),
    'immediateProperties' => 
    array (
    ),
    'immediateMethods' => 
    array (
      '__construct' => 
      array (
        'name' => '__construct',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 15,
        'endLine' => 18,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Exceptions\\Appointments',
        'declaringClassName' => 'App\\Exceptions\\Appointments\\EarlyNoShowException',
        'implementingClassName' => 'App\\Exceptions\\Appointments\\EarlyNoShowException',
        'currentClassName' => 'App\\Exceptions\\Appointments\\EarlyNoShowException',
        'aliasName' => NULL,
      ),
      'render' => 
      array (
        'name' => 'render',
        'parameters' => 
        array (
          'request' => 
          array (
            'name' => 'request',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Http\\Request',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 23,
            'endLine' => 23,
            'startColumn' => 28,
            'endColumn' => 43,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'Illuminate\\Http\\JsonResponse',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * 422 — a business-rule validation failure on the request as submitted.
 */',
        'startLine' => 23,
        'endLine' => 31,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Exceptions\\Appointments',
        'declaringClassName' => 'App\\Exceptions\\Appointments\\EarlyNoShowException',
        'implementingClassName' => 'App\\Exceptions\\Appointments\\EarlyNoShowException',
        'currentClassName' => 'App\\Exceptions\\Appointments\\EarlyNoShowException',
        'aliasName' => NULL,
      ),
    ),
    'traitsData' => 
    array (
      'aliases' => 
      array (
      ),
      'modifiers' => 
      array (
      ),
      'precedences' => 
      array (
      ),
      'hashes' => 
      array (
      ),
    ),
  ),
));