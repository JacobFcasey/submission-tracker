<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('view companies');

        $companies = Company::query()
            ->when($request->search, function ($query, $search) {
                $query->where('name', 'ilike', "%{$search}%")
                    ->orWhere('contact_email', 'ilike', "%{$search}%");
            })
            ->when($request->municipality_id, function ($query, $municipalityId) {
                $query->whereHas('municipalities', function ($q) use ($municipalityId) {
                    $q->where('municipalities.id', $municipalityId);
                });
            })
            ->latest()
            ->paginate($request->per_page ?? 20);

        return response()->json([
            'data' => $companies->items(),
            'meta' => [
                'current_page' => $companies->currentPage(),
                'last_page' => $companies->lastPage(),
                'per_page' => $companies->perPage(),
                'total' => $companies->total(),
            ],
        ]);
    }

    public function show(Company $company)
    {
        $this->authorize('view companies');

        return response()->json([
            'data' => $company->load(['municipalities']),
        ]);
    }
}
