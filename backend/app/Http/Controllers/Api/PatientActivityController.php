<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PatientActivityResource;
use App\Models\Patient;
use App\Models\PatientActivity;
use App\Policies\PatientActivityPolicy;
use Illuminate\Http\Request;

class PatientActivityController extends Controller
{
    public function index(Request $request, Patient $patient)
    {
        $this->authorize('viewAny', PatientActivity::class);

        // Security Architecture Decision (design doc §9A/§16 decision 3): categories the actor
        // can't read are excluded from the query itself, never fetched then hidden. Computed once
        // per request — a fixed small number of can() checks, not one per row.
        $allowedCategories = PatientActivityPolicy::allowedCategories($request->user());

        $requestedCategory = $request->query('category');
        $categories = $requestedCategory
            ? array_intersect($allowedCategories, [$requestedCategory])
            : $allowedCategories;

        $activities = $patient->activities()
            ->with('actor')
            ->whereIn('category', $categories)
            ->when($request->query('from'), fn ($query, $from) => $query->where('occurred_at', '>=', $from))
            ->when($request->query('to'), fn ($query, $to) => $query->where('occurred_at', '<=', $to))
            ->latest('occurred_at')
            ->paginate((int) $request->query('per_page', 15));

        return PatientActivityResource::collection($activities);
    }
}
