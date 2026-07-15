<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Dentist = 'dentist';
    case Receptionist = 'receptionist';
}
