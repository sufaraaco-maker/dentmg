<?php

namespace App\Enums;

/**
 * V1 classification set (design doc §2) — a lightweight filter/label, not a configurable-type
 * system. `Other` covers anything that doesn't fit the other four rather than growing this enum
 * speculatively.
 */
enum ClinicalNoteType: string
{
    case Progress = 'progress';
    case Consultation = 'consultation';
    case Phone = 'phone';
    case Referral = 'referral';
    case Other = 'other';
}
