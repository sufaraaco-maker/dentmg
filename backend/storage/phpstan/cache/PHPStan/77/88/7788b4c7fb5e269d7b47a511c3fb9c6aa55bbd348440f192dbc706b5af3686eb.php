<?php declare(strict_types = 1);

// odsl-/var/www/html/app/Exceptions/Appointments/PatientConflictException.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Exceptions\Appointments\PatientConflictException
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.3-8.4.23-03d5b6d7b344ea5c1b348b7ea5400d338bf0981728758d8e46b4362e119e0731',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Exceptions\\Appointments\\PatientConflictException',
        'filename' => '/var/www/html/app/Exceptions/Appointments/PatientConflictException.php',
      ),
    ),
    'namespace' => 'App\\Exceptions\\Appointments',
    'name' => 'App\\Exceptions\\Appointments\\PatientConflictException',
    'shortName' => 'PatientConflictException',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * Soft warning (design doc §5.4): the patient already has a genuinely overlapping
 * appointment (not just another one the same day). Overridable via `override_patient_conflict`.
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 13,
    'endLine' => 34,
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
        'declaringClassName' => 'App\\Exceptions\\Appointments\\PatientConflictException',
        'implementingClassName' => 'App\\Exceptions\\Appointments\\PatientConflictException',
        'currentClassName' => 'App\\Exceptions\\Appointments\\PatientConflictException',
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
 * 409 Conflict — same status family as DentistConflictException (both describe an
 * overlapping-appointment conflict), but `overridable`/`override_field` let the frontend
 * distinguish this soft warning and offer a "Book Anyway" confirmation.
 */',
        'startLine' => 25,
        'endLine' => 33,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Exceptions\\Appointments',
        'declaringClassName' => 'App\\Exceptions\\Appointments\\PatientConflictException',
        'implementingClassName' => 'App\\Exceptions\\Appointments\\PatientConflictException',
        'currentClassName' => 'App\\Exceptions\\Appointments\\PatientConflictException',
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