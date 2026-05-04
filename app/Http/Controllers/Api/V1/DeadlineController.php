<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\MunicipalityDeadline;
use Illuminate\Http\Request;

class DeadlineController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('view deadlines');

        $deadlines = MunicipalityDeadline::with(['municipality:id,name,code'])
            ->when($request->search, function ($query, $search) {
                $query->whereHas('municipality', function ($q) use ($search) {
                    $q->where('name', 'ilike', "%{$search}%")
                        ->orWhere('code', 'ilike', "%{$search}%");
                });
            })
            ->when($request->municipality_id, function ($query, $municipalityId) {
                $query->where('municipality_id', $municipalityId);
            })
            ->when($request->status === 'upcoming', function ($query) {
                $query->where('deadline_date', '>=', now());
            })
            ->when($request->status === 'past', function ($query) {
                $query->where('deadline_date', '<', now());
            })
            ->orderBy('deadline_date', $request->sort_order === 'desc' ? 'desc' : 'asc')
            ->paginate($request->per_page ?? 20);

        return response()->json([
            'data' => $deadlines->items(),
            'meta' => [
                'current_page' => $deadlines->currentPage(),
                'last_page' => $deadlines->lastPage(),
                'per_page' => $deadlines->perPage(),
                'total' => $deadlines->total(),
            ],
        ]);
    }

    public function show(MunicipalityDeadline $deadline)
    {
        $this->authorize('view deadlines');

        return response()->json([
            'data' => $deadline->load(['municipality', 'assignments.company', 'assignments.user']),
        ]);
    }
}
