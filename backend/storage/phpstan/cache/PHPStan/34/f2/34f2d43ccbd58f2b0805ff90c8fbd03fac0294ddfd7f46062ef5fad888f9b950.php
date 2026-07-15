<?php declare(strict_types = 1);

// odsl-/var/www/html/app/Exceptions/Appointments/DentistConflictException.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Exceptions\Appointments\DentistConflictException
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.3-8.4.23-8f7d3440e1a75bd13ab651db3a16e8d52255bb018338ae1e01b76d7dfd44b89c',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Exceptions\\Appointments\\DentistConflictException',
        'filename' => '/var/www/html/app/Exceptions/Appointments/DentistConflictException.php',
      ),
    ),
    'namespace' => 'App\\Exceptions\\Appointments',
    'name' => 'App\\Exceptions\\Appointments\\DentistConflictException',
    'shortName' => 'DentistConflictException',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * Hard block, never overridable (design doc §5.3, §10): the dentist already has an
 * overlapping, time-occupying appointment.
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
        'declaringClassName' => 'App\\Exceptions\\Appointments\\DentistConflictException',
        'implementingClassName' => 'App\\Exceptions\\Appointments\\DentistConflictException',
        'currentClassName' => 'App\\Exceptions\\Appointments\\DentistConflictException',
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
            'startLine' => 25,
            'endLine' => 25,
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
 * 409 Conflict — framed the same way as PatientConflictException (both describe an
 * overlapping-appointment conflict), but with no `overridable` key: this one is a hard
 * block, so the frontend has nothing to offer the user except picking a different slot.
 */',
        'startLine' => 25,
        'endLine' => 31,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Exceptions\\Appointments',
        'declaringClassName' => 'App\\Exceptions\\Appointments\\DentistConflictException',
        'implementingClassName' => 'App\\Exceptions\\Appointments\\DentistConflictException',
        'currentClassName' => 'App\\Exceptions\\Appointments\\DentistConflictException',
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