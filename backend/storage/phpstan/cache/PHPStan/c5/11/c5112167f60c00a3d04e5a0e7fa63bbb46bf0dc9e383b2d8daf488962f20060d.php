<?php declare(strict_types = 1);

// odsl-/var/www/html/app/Http/Requests/Appointment/UpdateAppointmentRequest.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Http\Requests\Appointment\UpdateAppointmentRequest
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.3-8.4.23-d432ad44b0eee8d92f33fbe7ebe295679b32954587cfc2afe9421e2fb2a09626',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Http\\Requests\\Appointment\\UpdateAppointmentRequest',
        'filename' => '/var/www/html/app/Http/Requests/Appointment/UpdateAppointmentRequest.php',
      ),
    ),
    'namespace' => 'App\\Http\\Requests\\Appointment',
    'name' => 'App\\Http\\Requests\\Appointment\\UpdateAppointmentRequest',
    'shortName' => 'UpdateAppointmentRequest',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => NULL,
    'attributes' => 
    array (
    ),
    'startLine' => 10,
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
        'docComment' => NULL,
        'startLine' => 12,
        'endLine' => 15,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Http\\Requests\\Appointment',
        'declaringClassName' => 'App\\Http\\Requests\\Appointment\\UpdateAppointmentRequest',
        'implementingClassName' => 'App\\Http\\Requests\\Appointment\\UpdateAppointmentRequest',
        'currentClassName' => 'App\\Http\\Requests\\Appointment\\UpdateAppointmentRequest',
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
 * Covers the "full edit" and in-place reschedule flows (design doc §11). `patient_id` is
 * deliberately not editable here — booking the wrong patient entirely is a soft-delete
 * case (§4), not a correction via update. `status` is not editable here either — it only
 * moves through the dedicated transition endpoints (confirm/check-in/start/.../cancel).
 *
 * @return array<string, mixed>
 */',
        'startLine' => 25,
        'endLine' => 45,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Http\\Requests\\Appointment',
        'declaringClassName' => 'App\\Http\\Requests\\Appointment\\UpdateAppointmentRequest',
        'implementingClassName' => 'App\\Http\\Requests\\Appointment\\UpdateAppointmentRequest',
        'currentClassName' => 'App\\Http\\Requests\\Appointment\\UpdateAppointmentRequest',
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