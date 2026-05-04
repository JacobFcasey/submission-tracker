<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Uploads;
use App\Services\CaseyPremiumBatchService;
use Illuminate\Http\Request;

class UploadController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('view uploads');

        $user = $request->user();
        $isAdmin = $user->hasRole('admin') || $user->hasRole('super-admin') || $user->is_admin;

        $uploads = Uploads::with(['municipality:id,name', 'company:id,name', 'user:id,name'])
            ->when(!$isAdmin, function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->when($request->search, function ($query, $search) {
                $query->where('reference', 'ilike', "%{$search}%")
                    ->orWhereHas('company', function ($q) use ($search) {
                        $q->where('name', 'ilike', "%{$search}%");
                    })
                    ->orWhereHas('municipality', function ($q) use ($search) {
                        $q->where('name', 'ilike', "%{$search}%");
                    });
            })
            ->when($request->municipality_id, function ($query, $municipalityId) {
                $query->where('municipality_id', $municipalityId);
            })
            ->when($request->company_id, function ($query, $companyId) {
                $query->where('company_id', $companyId);
            })
            ->when($request->status, function ($query, $status) {
                $query->where('status', $status);
            })
            ->latest('submitted_at')
            ->paginate($request->per_page ?? 20);

        return response()->json([
            'data' => $uploads->items(),
            'meta' => [
                'current_page' => $uploads->currentPage(),
                'last_page' => $uploads->lastPage(),
                'per_page' => $uploads->perPage(),
                'total' => $uploads->total(),
            ],
        ]);
    }

    public function show(Uploads $upload)
    {
        $this->authorize('view uploads');

        $user = request()->user();
        $isAdmin = $user->hasRole('admin') || $user->hasRole('super-admin') || $user->is_admin;

        if (!$isAdmin && $upload->user_id !== $user->id) {
            abort(403, 'Unauthorized');
        }

        return response()->json([
            'data' => $upload->load(['municipality', 'company', 'user']),
        ]);
    }

    public function premiumBatchDetailedInfo(Request $request, CaseyPremiumBatchService $caseyService)
    {
        $this->authorize('view uploads');

        $policyBatchId = (int) $request->query(
            'policy_batch_id',
            (int) config('services.casey.premium_batch_id', 5239)
        );

        if ($policyBatchId < 1) {
            $policyBatchId = 5239;
        }

        return response()->json(
            $caseyService->fetchDetailedInfo($policyBatchId)
        );
    }
}
