<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Municipality;
use Illuminate\Http\Request;

class MunicipalityController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('view municipalities');

        $municipalities = Municipality::query()
            ->when($request->search, function ($query, $search) {
                $query->where('name', 'ilike', "%{$search}%")
                    ->orWhere('code', 'ilike', "%{$search}%")
                    ->orWhere('region', 'ilike', "%{$search}%");
            })
            ->latest()
            ->paginate($request->per_page ?? 20);

        return response()->json([
            'data' => $municipalities->items(),
            'meta' => [
                'current_page' => $municipalities->currentPage(),
                'last_page' => $municipalities->lastPage(),
                'per_page' => $municipalities->perPage(),
                'total' => $municipalities->total(),
            ],
        ]);
    }

    public function show(Municipality $municipality)
    {
        $this->authorize('view municipalities');

        return response()->json([
            'data' => $municipality->load(['companies', 'deadlines']),
        ]);
    }
}
