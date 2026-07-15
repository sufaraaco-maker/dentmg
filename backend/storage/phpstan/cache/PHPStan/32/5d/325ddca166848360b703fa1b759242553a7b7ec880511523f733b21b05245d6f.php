<?php declare(strict_types = 1);

// odsl-/var/www/html/app/Http/Requests/Appointment/IndexAppointmentRequest.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Http\Requests\Appointment\IndexAppointmentRequest
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.3-8.4.23-3163316dc6d24ef3c0266a4fa1f4d29278fab1136e35cca69e73e52035f3a8cb',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Http\\Requests\\Appointment\\IndexAppointmentRequest',
        'filename' => '/var/www/html/app/Http/Requests/Appointment/IndexAppointmentRequest.php',
      ),
    ),
    'namespace' => 'App\\Http\\Requests\\Appointment',
    'name' => 'App\\Http\\Requests\\Appointment\\IndexAppointmentRequest',
    'shortName' => 'IndexAppointmentRequest',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => NULL,
    'attributes' => 
    array (
    ),
    'startLine' => 11,
    'endLine' => 46,
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
        'docComment' => '/**
 * Open to any authenticated role (design doc §14 — clinic-wide read visibility); the
 * controller still calls `AppointmentPolicy::viewAny()` for consistency with the rest of
 * the API, per `api-guidelines.md`.
 */',
        'startLine' => 18,
        'endLine' => 21,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Http\\Requests\\Appointment',
        'declaringClassName' => 'App\\Http\\Requests\\Appointment\\IndexAppointmentRequest',
        'implementingClassName' => 'App\\Http\\Requests\\Appointment\\IndexAppointmentRequest',
        'currentClassName' => 'App\\Http\\Requests\\Appointment\\IndexAppointmentRequest',
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
 * Bounded by a required date range instead of classic pagination (design doc §16/§19).
 *
 * @return array<string, mixed>
 */',
        'startLine' => 28,
        'endLine' => 45,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Http\\Requests\\Appointment',
        'declaringClassName' => 'App\\Http\\Requests\\Appointment\\IndexAppointmentRequest',
        'implementingClassName' => 'App\\Http\\Requests\\Appointment\\IndexAppointmentRequest',
        'currentClassName' => 'App\\Http\\Requests\\Appointment\\IndexAppointmentRequest',
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