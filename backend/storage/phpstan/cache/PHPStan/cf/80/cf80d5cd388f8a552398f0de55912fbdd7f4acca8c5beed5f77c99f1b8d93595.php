<?php declare(strict_types = 1);

// odsl-/var/www/html/app/Enums/AppointmentStatus.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Enums\AppointmentStatus
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.3-8.4.23-94663669680a7adbe90adcbaa031c1edf413a7697a3864c283ad906d1bf2c269',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Enums\\AppointmentStatus',
        'filename' => '/var/www/html/app/Enums/AppointmentStatus.php',
      ),
    ),
    'namespace' => 'App\\Enums',
    'name' => 'App\\Enums\\AppointmentStatus',
    'shortName' => 'AppointmentStatus',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => true,
    'isBackedEnum' => true,
    'modifiers' => 0,
    'docComment' => NULL,
    'attributes' => 
    array (
    ),
    'startLine' => 5,
    'endLine' => 30,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => NULL,
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
      'name' => 
      array (
        'declaringClassName' => 'App\\Enums\\AppointmentStatus',
        'implementingClassName' => 'App\\Enums\\AppointmentStatus',
        'name' => 'name',
        'modifiers' => 2177,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'string',
            'isIdentifier' => true,
          ),
        ),
        'default' => NULL,
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => NULL,
        'endLine' => NULL,
        'startColumn' => -1,
        'endColumn' => -1,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'value' => 
      array (
        'declaringClassName' => 'App\\Enums\\AppointmentStatus',
        'implementingClassName' => 'App\\Enums\\AppointmentStatus',
        'name' => 'value',
        'modifiers' => 2177,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'string',
            'isIdentifier' => true,
          ),
        ),
        'default' => NULL,
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => NULL,
        'endLine' => NULL,
        'startColumn' => -1,
        'endColumn' => -1,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
    ),
    'immediateMethods' => 
    array (
      'occupyingStatuses' => 
      array (
        'name' => 'occupyingStatuses',
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
 * Statuses that occupy the dentist\'s time slot. Mirrors the status list hardcoded in the
 * appointments_no_overlapping_dentist_slots Postgres EXCLUDE constraint migration.
 *
 * @return list<self>
 */',
        'startLine' => 21,
        'endLine' => 24,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'App\\Enums',
        'declaringClassName' => 'App\\Enums\\AppointmentStatus',
        'implementingClassName' => 'App\\Enums\\AppointmentStatus',
        'currentClassName' => 'App\\Enums\\AppointmentStatus',
        'aliasName' => NULL,
      ),
      'occupiesTime' => 
      array (
        'name' => 'occupiesTime',
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
        'startLine' => 26,
        'endLine' => 29,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Enums',
        'declaringClassName' => 'App\\Enums\\AppointmentStatus',
        'implementingClassName' => 'App\\Enums\\AppointmentStatus',
        'currentClassName' => 'App\\Enums\\AppointmentStatus',
        'aliasName' => NULL,
      ),
      'cases' => 
      array (
        'name' => 'cases',
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
        'docComment' => NULL,
        'startLine' => NULL,
        'endLine' => NULL,
        'startColumn' => -1,
        'endColumn' => -1,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'App\\Enums',
        'declaringClassName' => 'App\\Enums\\AppointmentStatus',
        'implementingClassName' => 'App\\Enums\\AppointmentStatus',
        'currentClassName' => 'App\\Enums\\AppointmentStatus',
        'aliasName' => NULL,
      ),
      'from' => 
      array (
        'name' => 'from',
        'parameters' => 
        array (
          'value' => 
          array (
            'name' => 'value',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionUnionType',
              'data' => 
              array (
                'types' => 
                array (
                  0 => 
                  array (
                    'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                    'data' => 
                    array (
                      'name' => 'string',
                      'isIdentifier' => true,
                    ),
                  ),
                  1 => 
                  array (
                    'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                    'data' => 
                    array (
                      'name' => 'int',
                      'isIdentifier' => true,
                    ),
                  ),
                ),
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => NULL,
            'endLine' => NULL,
            'startColumn' => -1,
            'endColumn' => -1,
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
            'name' => 'static',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => NULL,
        'endLine' => NULL,
        'startColumn' => -1,
        'endColumn' => -1,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'App\\Enums',
        'declaringClassName' => 'App\\Enums\\AppointmentStatus',
        'implementingClassName' => 'App\\Enums\\AppointmentStatus',
        'currentClassName' => 'App\\Enums\\AppointmentStatus',
        'aliasName' => NULL,
      ),
      'tryFrom' => 
      array (
        'name' => 'tryFrom',
        'parameters' => 
        array (
          'value' => 
          array (
            'name' => 'value',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionUnionType',
              'data' => 
              array (
                'types' => 
                array (
                  0 => 
                  array (
                    'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                    'data' => 
                    array (
                      'name' => 'string',
                      'isIdentifier' => true,
                    ),
                  ),
                  1 => 
                  array (
                    'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                    'data' => 
                    array (
                      'name' => 'int',
                      'isIdentifier' => true,
                    ),
                  ),
                ),
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => NULL,
            'endLine' => NULL,
            'startColumn' => -1,
            'endColumn' => -1,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionUnionType',
          'data' => 
          array (
            'types' => 
            array (
              0 => 
              array (
                'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                'data' => 
                array (
                  'name' => 'static',
                  'isIdentifier' => true,
                ),
              ),
              1 => 
              array (
                'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                'data' => 
                array (
                  'name' => 'null',
                  'isIdentifier' => true,
                ),
              ),
            ),
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => NULL,
        'endLine' => NULL,
        'startColumn' => -1,
        'endColumn' => -1,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'App\\Enums',
        'declaringClassName' => 'App\\Enums\\AppointmentStatus',
        'implementingClassName' => 'App\\Enums\\AppointmentStatus',
        'currentClassName' => 'App\\Enums\\AppointmentStatus',
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
    'backingType' => 
    array (
      'name' => 'string',
      'isIdentifier' => true,
    ),
    'cases' => 
    array (
      'Scheduled' => 
      array (
        'name' => 'Scheduled',
        'value' => 
        array (
          'code' => '\'scheduled\'',
          'attributes' => 
          array (
            'startLine' => 7,
            'endLine' => 7,
            'startTokenPos' => 22,
            'startFilePos' => 83,
            'endTokenPos' => 22,
            'endFilePos' => 93,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 7,
        'endLine' => 7,
        'startColumn' => 5,
        'endColumn' => 33,
      ),
      'Confirmed' => 
      array (
        'name' => 'Confirmed',
        'value' => 
        array (
          'code' => '\'confirmed\'',
          'attributes' => 
          array (
            'startLine' => 8,
            'endLine' => 8,
            'startTokenPos' => 31,
            'startFilePos' => 117,
            'endTokenPos' => 31,
            'endFilePos' => 127,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 8,
        'endLine' => 8,
        'startColumn' => 5,
        'endColumn' => 33,
      ),
      'CheckedIn' => 
      array (
        'name' => 'CheckedIn',
        'value' => 
        array (
          'code' => '\'checked_in\'',
          'attributes' => 
          array (
            'startLine' => 9,
            'endLine' => 9,
            'startTokenPos' => 40,
            'startFilePos' => 151,
            'endTokenPos' => 40,
            'endFilePos' => 162,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 9,
        'endLine' => 9,
        'startColumn' => 5,
        'endColumn' => 34,
      ),
      'InProgress' => 
      array (
        'name' => 'InProgress',
        'value' => 
        array (
          'code' => '\'in_progress\'',
          'attributes' => 
          array (
            'startLine' => 10,
            'endLine' => 10,
            'startTokenPos' => 49,
            'startFilePos' => 187,
            'endTokenPos' => 49,
            'endFilePos' => 199,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 10,
        'endLine' => 10,
        'startColumn' => 5,
        'endColumn' => 36,
      ),
      'Completed' => 
      array (
        'name' => 'Completed',
        'value' => 
        array (
          'code' => '\'completed\'',
          'attributes' => 
          array (
            'startLine' => 11,
            'endLine' => 11,
            'startTokenPos' => 58,
            'startFilePos' => 223,
            'endTokenPos' => 58,
            'endFilePos' => 233,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 11,
        'endLine' => 11,
        'startColumn' => 5,
        'endColumn' => 33,
      ),
      'Cancelled' => 
      array (
        'name' => 'Cancelled',
        'value' => 
        array (
          'code' => '\'cancelled\'',
          'attributes' => 
          array (
            'startLine' => 12,
            'endLine' => 12,
            'startTokenPos' => 67,
            'startFilePos' => 257,
            'endTokenPos' => 67,
            'endFilePos' => 267,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 12,
        'endLine' => 12,
        'startColumn' => 5,
        'endColumn' => 33,
      ),
      'NoShow' => 
      array (
        'name' => 'NoShow',
        'value' => 
        array (
          'code' => '\'no_show\'',
          'attributes' => 
          array (
            'startLine' => 13,
            'endLine' => 13,
            'startTokenPos' => 76,
            'startFilePos' => 288,
            'endTokenPos' => 76,
            'endFilePos' => 296,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 13,
        'endLine' => 13,
        'startColumn' => 5,
        'endColumn' => 28,
      ),
    ),
  ),
));