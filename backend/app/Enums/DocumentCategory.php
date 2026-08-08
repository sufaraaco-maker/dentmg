<?php

namespace App\Enums;

/**
 * Design doc §7/§16 decision 1: `lab_report` was dropped from the originally-proposed list and
 * replaced with `clinical_summary` — Documents should not offer a category that reads as "where lab
 * results go" when the Laboratory module already owns that workflow via `LabCase`.
 */
enum DocumentCategory: string
{
    case ConsentForm = 'consent_form';
    case Insurance = 'insurance';
    case Referral = 'referral';
    case ClinicalSummary = 'clinical_summary';
    case Correspondence = 'correspondence';
    case Other = 'other';
}
