<?php declare(strict_types = 1);

// odsl-/var/www/html/app/Http/Requests/DentistWorkingHour/StoreDentistWorkingHourRequest.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Http\Requests\DentistWorkingHour\StoreDentistWorkingHourRequest
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.3-8.4.23-5d954feb0226f2632993a4001f25116462a7208dc77e2f8d6e13c8c9f943341b',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Http\\Requests\\DentistWorkingHour\\StoreDentistWorkingHourRequest',
        'filename' => '/var/www/html/app/Http/Requests/DentistWorkingHour/StoreDentistWorkingHourRequest.php',
      ),
    ),
    'namespace' => 'App\\Http\\Requests\\DentistWorkingHour',
    'name' => 'App\\Http\\Requests\\DentistWorkingHour\\StoreDentistWorkingHourRequest',
    'shortName' => 'StoreDentistWorkingHourRequest',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => NULL,
    'attributes' => 
    array (
    ),
    'startLine' => 8,
    'endLine' => 31,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => 'Illuminate\\Foundation\\Http\\FormRequest',
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
      'authorize' => 
      array (
        'name' => 'authorize',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'bool',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 10,
        'endLine' => 13,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Http\\Requests\\DentistWorkingHour',
        'declaringClassName' => 'App\\Http\\Requests\\DentistWorkingHour\\StoreDentistWorkingHourRequest',
        'implementingClassName' => 'App\\Http\\Requests\\DentistWorkingHour\\StoreDentistWorkingHourRequest',
        'currentClassName' => 'App\\Http\\Requests\\DentistWorkingHour\\StoreDentistWorkingHourRequest',
        'aliasName' => NULL,
      ),
      'rules' => 
      array (
        'name' => 'rules',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'array',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Multiple rows per `day_of_week` are valid (lunch-break split shifts) — no uniqueness
 * rule here (design doc §6). Overlap-within-the-same-day sanity checking, if any, belongs
 * to AppointmentService, not this layer.
 *
 * @return array<string, mixed>
 */',
        'startLine' => 22,
        'endLine' => 30,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Http\\Requests\\DentistWorkingHour',
        'declaringClassName' => 'App\\Http\\Requests\\DentistWorkingHour\\StoreDentistWorkingHourRequest',
        'implementingClassName' => 'App\\Http\\Requests\\DentistWorkingHour\\StoreDentistWorkingHourRequest',
        'currentClassName' => 'App\\Http\\Requests\\DentistWorkingHour\\StoreDentistWorkingHourRequest',
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