<?php declare(strict_types = 1);

// odsl-/var/www/html/app/Models
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v1-enums',
   'data' => 
  array (
    '/var/www/html/app/Models/Appointment.php' => 
    array (
      0 => '3034a0de2813dd6f438aea5c4462a6bdbb22a29de7d103d9750498895815d8fc',
      1 => 
      array (
        0 => 'app\\models\\appointment',
      ),
      2 => 
      array (
        0 => 'app\\models\\casts',
        1 => 'app\\models\\patient',
        2 => 'app\\models\\dentist',
        3 => 'app\\models\\appointmenttype',
        4 => 'app\\models\\cancelledby',
        5 => 'app\\models\\reminders',
        6 => 'app\\models\\scopeactive',
        7 => 'app\\models\\scopefordentist',
        8 => 'app\\models\\scopeforpatient',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/app/Models/AppointmentReminder.php' => 
    array (
      0 => '1681a52046762f5a702f24ecfc134ae79c4a490ebf2b14269b9a4ee64a3ce474',
      1 => 
      array (
        0 => 'app\\models\\appointmentreminder',
      ),
      2 => 
      array (
        0 => 'app\\models\\casts',
        1 => 'app\\models\\appointment',
        2 => 'app\\models\\scopepending',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/app/Models/AppointmentType.php' => 
    array (
      0 => 'f1b55db720b7b0b1d397c522eaec55791cfdfd8bd68f521c95f5a63e61c79eb1',
      1 => 
      array (
        0 => 'app\\models\\appointmenttype',
      ),
      2 => 
      array (
        0 => 'app\\models\\casts',
        1 => 'app\\models\\appointments',
        2 => 'app\\models\\scopeactive',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/app/Models/AuditLog.php' => 
    array (
      0 => '3c70bac32f42333f0dfd73dafb606802375f3693005cbad833f7f05052d03748',
      1 => 
      array (
        0 => 'app\\models\\auditlog',
      ),
      2 => 
      array (
        0 => 'app\\models\\casts',
        1 => 'app\\models\\user',
        2 => 'app\\models\\auditable',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/app/Models/Concerns/Auditable.php' => 
    array (
      0 => '3e8ba22f57ec908d120568497896d6a0cd1d96fd31dd773eb6321f6f736b7cb4',
      1 => 
      array (
        0 => 'app\\models\\concerns\\auditable',
      ),
      2 => 
      array (
        0 => 'app\\models\\concerns\\bootauditable',
        1 => 'app\\models\\concerns\\auditlogs',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/app/Models/DentistTimeOff.php' => 
    array (
      0 => '06f63a956cc0ea7642e2938b6a4cb59cfe94426309e7c62647b3de65392e3538',
      1 => 
      array (
        0 => 'app\\models\\dentisttimeoff',
      ),
      2 => 
      array (
        0 => 'app\\models\\casts',
        1 => 'app\\models\\dentist',
        2 => 'app\\models\\scopefordentist',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/app/Models/DentistWorkingHour.php' => 
    array (
      0 => '0a5ca64a99ea3d186ca6468daab6e32113cb298ab5f5b0cd8ebd6754e0420d21',
      1 => 
      array (
        0 => 'app\\models\\dentistworkinghour',
      ),
      2 => 
      array (
        0 => 'app\\models\\casts',
        1 => 'app\\models\\dentist',
        2 => 'app\\models\\scopeactive',
        3 => 'app\\models\\scopefordentist',
        4 => 'app\\models\\scopefordayofweek',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/app/Models/Patient.php' => 
    array (
      0 => 'f1e0cc69d3e21b8753c40e41c5a698f227c5178b2333cc0044ecd50fc7fc0eb2',
      1 => 
      array (
        0 => 'app\\models\\patient',
      ),
      2 => 
      array (
        0 => 'app\\models\\casts',
        1 => 'app\\models\\getpatientcodeattribute',
        2 => 'app\\models\\getfullnameattribute',
        3 => 'app\\models\\appointments',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/app/Models/User.php' => 
    array (
      0 => '8536a7ab7e0d8d16ffae548c1329ecfec34ca8c9194704a9307a27cbf1ff1261',
      1 => 
      array (
        0 => 'app\\models\\user',
      ),
      2 => 
      array (
        0 => 'app\\models\\casts',
        1 => 'app\\models\\isadmin',
        2 => 'app\\models\\appointmentsasdentist',
        3 => 'app\\models\\workinghours',
        4 => 'app\\models\\timeoff',
      ),
      3 => 
      array (
      ),
    ),
  ),
));