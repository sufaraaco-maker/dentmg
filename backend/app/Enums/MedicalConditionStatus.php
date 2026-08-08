<?php

namespace App\Enums;

enum MedicalConditionStatus: string
{
    case Active = 'active';
    case Resolved = 'resolved';
    case Chronic = 'chronic';
}
