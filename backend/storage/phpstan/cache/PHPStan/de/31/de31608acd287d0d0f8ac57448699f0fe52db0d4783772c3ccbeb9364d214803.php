<?php declare(strict_types = 1);

// odsl-/var/www/html/app/Exceptions/Appointments/OutsideWorkingHoursException.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Exceptions\Appointments\OutsideWorkingHoursException
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.3-8.4.23-b47c0b6e2a46b8af8634d782445bc87afd5dbbf537f78f8bee5a75459998c4d4',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Exceptions\\Appointments\\OutsideWorkingHoursException',
        'filename' => '/var/www/html/app/Exceptions/Appointments/OutsideWorkingHoursException.php',
      ),
    ),
    'namespace' => 'App\\Exceptions\\Appointments',
    'name' => 'App\\Exceptions\\Appointments\\OutsideWorkingHoursException',
    'shortName' => 'OutsideWorkingHoursException',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * Soft warning (design doc §5.5): the requested slot falls outside the dentist\'s working
 * hours or during their time-off. Overridable via `override_outside_working_hours`.
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 13,
    'endLine' => 33,
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
        'declaringClassName' => 'App\\Exceptions\\Appointments\\OutsideWorkingHoursException',
        'implementingClassName' => 'App\\Exceptions\\Appointments\\OutsideWorkingHoursException',
        'currentClassName' => 'App\\Exceptions\\Appointments\\OutsideWorkingHoursException',
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
            'startLine' => 24,
            'endLine' => 24,
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
 * 422 — a business-rule validation failure on the request as submitted (not a resource
 * conflict), same family as EarlyNoShowException/InvalidStatusTransitionException.
 */',
        'startLine' => 24,
        'endLine' => 32,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Exceptions\\Appointments',
        'declaringClassName' => 'App\\Exceptions\\Appointments\\OutsideWorkingHoursException',
        'implementingClassName' => 'App\\Exceptions\\Appointments\\OutsideWorkingHoursException',
        'currentClassName' => 'App\\Exceptions\\Appointments\\OutsideWorkingHoursException',
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