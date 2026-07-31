<?php

namespace App\Enums;

/**
 * Mirrors the design doc's §2 risk-tiering (ai-assistant-design.md) — the three zero-PHI features
 * (DashboardInsight/SmartSearch/ReportNarrative) never carry a `patient_id` on their log row; the
 * two PHI-bearing features (ClinicalNoteDraft/TreatmentSuggestion) always do and are gated behind
 * `ClinicSetting.ai_assistant_phi_features_acknowledged`.
 */
enum AiInteractionFeature: string
{
    case DashboardInsight = 'dashboard_insight';
    case SmartSearch = 'smart_search';
    case ReportNarrative = 'report_narrative';
    case ClinicalNoteDraft = 'clinical_note_draft';
    case TreatmentSuggestion = 'treatment_suggestion';

    /**
     * The two features that send patient-identified clinical content to the Claude API (design
     * doc §5 Layer 2) — require `ai_assistant_phi_features_acknowledged` in addition to the
     * general `ai_assistant_enabled` toggle.
     */
    public function isPhiBearing(): bool
    {
        return match ($this) {
            self::ClinicalNoteDraft, self::TreatmentSuggestion => true,
            self::DashboardInsight, self::SmartSearch, self::ReportNarrative => false,
        };
    }
}
