<?php

namespace Tests\Unit\Enums;

use App\Enums\AiInteractionFeature;
use Tests\TestCase;

class AiInteractionFeatureTest extends TestCase
{
    public function test_clinical_note_draft_and_treatment_suggestion_are_phi_bearing(): void
    {
        $this->assertTrue(AiInteractionFeature::ClinicalNoteDraft->isPhiBearing());
        $this->assertTrue(AiInteractionFeature::TreatmentSuggestion->isPhiBearing());
    }

    public function test_dashboard_insight_smart_search_and_report_narrative_are_not_phi_bearing(): void
    {
        $this->assertFalse(AiInteractionFeature::DashboardInsight->isPhiBearing());
        $this->assertFalse(AiInteractionFeature::SmartSearch->isPhiBearing());
        $this->assertFalse(AiInteractionFeature::ReportNarrative->isPhiBearing());
    }
}
