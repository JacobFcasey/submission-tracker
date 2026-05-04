<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Municipality;
use App\Services\CaseyReferenceDataService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CapsDataSyncController extends Controller
{
    public function __construct(private readonly CaseyReferenceDataService $service) {}

    /**
     * Trigger a full CAPS reference data sync (municipalities + companies).
     * Returns JSON so the frontend can display results inline.
     */
    public function sync(Request $request): JsonResponse
    {
        Log::info('[CAPS Sync] Manual sync triggered', [
            'user' => $request->user()?->employee_number,
        ]);

        $result = $this->service->syncAll();

        $message = $result['ok']
            ? sprintf(
                'Sync complete: %d municipalities (%d new), %d companies (%d new)',
                $result['municipalities']['fetched'] ?? 0,
                $result['municipalities']['created'] ?? 0,
                $result['companies']['fetched'] ?? 0,
                $result['companies']['created'] ?? 0,
            )
            : 'Sync failed: ' . ($result['municipalities']['message'] ?? $result['companies']['message'] ?? 'Unknown error');

        return response()->json([
            'ok' => $result['ok'],
            'message' => $message,
            'municipalities' => $result['municipalities'],
            'companies' => $result['companies'],
        ], $result['ok'] ? 200 : 502);
    }

    /**
     * Return the current sync status — last sync time, counts.
     */
    public function status(): JsonResponse
    {
        $municipalityCount = Municipality::count();
        $companyCount = Company::count();

        $lastMunicipalitySync = Municipality::whereNotNull('casey_synced_at')
            ->max('casey_synced_at');
        $lastCompanySync = Company::whereNotNull('casey_synced_at')
            ->max('casey_synced_at');

        return response()->json([
            'municipalities' => $municipalityCount,
            'companies' => $companyCount,
            'lastMunicipalitySync' => $lastMunicipalitySync,
            'lastCompanySync' => $lastCompanySync,
            'hasData' => $municipalityCount > 0 || $companyCount > 0,
        ]);
    }
}
