<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class AffordabilityController extends Controller
{
    public function index()
    {
        $this->authorize('view uploads');
        return Inertia::render('Uploads/Affordability');
    }

    public function search(Request $request)
    {
        $this->authorize('view uploads');

        $request->validate([
            'id_number' => 'required|string|max:20',
            'pay_number' => 'required|string|max:30',
        ]);

        $idNumber = trim($request->id_number);
        $payNumber = trim($request->pay_number);

        try {
            // Step 1: Find member in local synced data (has casey_id)
            $local = \App\Models\CapsMember::where('id_number', $idNumber)
                ->where('pay_number', $payNumber)
                ->first();

            if (!$local) {
                return response()->json(['ok' => false, 'message' => 'No member found with that ID number and pay number.']);
            }

            // Step 2: Fetch full member detail from CAPS by casey_id
            $token = $this->getToken();
            if (!$token) {
                return response()->json(['ok' => false, 'message' => 'Failed to authenticate with CAPS.']);
            }

            // Try demo file first for known demo accounts
            $demoPath = storage_path("app/demo/{$payNumber}.json");
            if (file_exists($demoPath)) {
                $demo = json_decode(file_get_contents($demoPath), true);
                if ($demo && ($demo['id_number'] ?? '') === $idNumber) {
                    return response()->json(['ok' => true, 'member' => $this->transformDemoMember($demo)]);
                }
            }

            $baseUrl = rtrim(config('services.casey.base_url'), '/');
            $resp = Http::withOptions(['verify' => (bool) config('services.casey.verify_ssl', true)])
                ->withToken($token)
                ->get("$baseUrl/v1/member/get", ['id' => $local->casey_id]);

            if ($resp->failed()) {
                return response()->json(['ok' => false, 'message' => 'CAPS returned HTTP ' . $resp->status()]);
            }

            $data = $resp->json();
            $member = $data['member'] ?? $data;

            // Extract nested objects safely
            $memberStatus = $member['memberStatus'] ?? null;
            $statusName = is_array($memberStatus) ? ($memberStatus['status'] ?? null) : $memberStatus;
            $area = $member['area'] ?? null;
            $areaCode = is_array($area) ? ($area['areaCode'] ?? null) : ($member['areaId'] ?? null);
            $title = $member['title'] ?? null;
            $titleName = is_array($title) ? ($title['title'] ?? null) : $title;

            $result = [
                'id' => $member['id'] ?? null,
                'title' => $titleName,
                'initials' => $member['initials'] ?? null,
                'first_name' => $member['firstName'] ?? $member['first_name'] ?? null,
                'surname' => $member['surName'] ?? $member['surname'] ?? null,
                'id_number' => $member['idNumber'] ?? $member['id_number'] ?? null,
                'pay_number' => $member['payNumber'] ?? $member['pay_number'] ?? null,
                'date_of_birth' => $member['dateOfBirth'] ?? null,
                'gender' => $member['genderId'] ?? null,
                'cell_number' => $member['cellNumber'] ?? null,
                'email' => $member['email'] ?? null,
                'passport' => $member['passport'] ?? null,
                'occupation' => $member['occupation'] ?? null,
                'status' => $statusName,
                'area_code' => $areaCode,
                // Income
                'inc_basic' => $member['incBasic'] ?? 0,
                'inc_leave' => $member['incLeave'] ?? 0,
                'inc_overtime' => $member['incOvertime'] ?? 0,
                'inc_bonus' => $member['incBonus'] ?? 0,
                'inc_allowance' => $member['incAllowance'] ?? 0,
                'inc_other' => $member['incOther'] ?? 0,
                'inc_variable_total' => $member['incVariableTotal'] ?? 0,
                'inc_net_salary' => $member['incNetSalaryReported'] ?? 0,
                // Deductions
                'exp_system_deductions' => $member['expSystemDeductions'] ?? 0,
                'exp_payroll_import' => $member['expPayrollImportDeductions'] ?? 0,
                'exp_total' => $member['expTotal'] ?? 0,
                // Affordability
                'check_afford' => $member['checkAfford'] ?? false,
                'affordability_tier_used' => $member['affordabilityTierUsed'] ?? null,
                'affordability_take_home_percent' => $member['affordabilityTakeHomePercentUsed'] ?? null,
                'affordability_min_take_home' => $member['affordabilityMinTakeHomeUsed'] ?? null,
                'affordability_effective_income' => $member['affordabilityEffectiveIncomeUsed'] ?? null,
                'affordability_protected_income' => $member['affordabilityProtectedIncome'] ?? null,
                'affordability_max_deduction' => $member['affordabilityMaxDeductionAmount'] ?? null,
                'affordability_existing_deductions' => $member['affordabilityExistingDeductions'] ?? 0,
                'affordability_remaining' => $member['affordabilityRemainingAmount'] ?? null,
                'affordability_utilization' => $member['affordabilityUtilisationPercent'] ?? 0,
                'affordability_last_updated' => $member['affordabilityLastUpdated'] ?? null,
                'affordability_manual_override' => $member['affordabilityManualOverride'] ?? false,
            ];

            return response()->json(['ok' => true, 'member' => $result]);
        } catch (\Throwable $e) {
            Log::error('Affordability search failed: ' . $e->getMessage());
            return response()->json(['ok' => false, 'message' => 'Search failed: ' . $e->getMessage()]);
        }
    }

    private function getToken(): ?string
    {
        $jwt = session('caps_jwt');
        if ($jwt) return $jwt;

        return Cache::remember('caps_service_token', 50 * 60, function () {
            $baseUrl = rtrim((string) config('services.casey.base_url', ''), '/');
            $authEndpoint = ltrim((string) config('services.casey.auth_endpoint', ''), '/');
            try {
                $resp = Http::withOptions(['verify' => (bool) config('services.casey.verify_ssl', true)])
                    ->post("$baseUrl/$authEndpoint", [
                        'username' => config('services.casey.username'),
                        'password' => config('services.casey.password'),
                    ]);
                return $resp->successful() ? ($resp->json('token') ?? $resp->json('access_token')) : null;
            } catch (\Throwable $e) { return null; }
        });
    }

    private function transformDemoMember(array $d): array
    {
        $a = $d['affordability'] ?? [];
        $inc = $d['income'] ?? [];
        $ded = $d['deductions'] ?? [];
        return [
            'id' => $d['id'] ?? null,
            'title' => $d['title'] ?? null,
            'initials' => $d['initials'] ?? null,
            'first_name' => $d['first_name'] ?? null,
            'surname' => $d['surname'] ?? null,
            'id_number' => $d['id_number'] ?? null,
            'pay_number' => $d['pay_number'] ?? null,
            'date_of_birth' => $d['date_of_birth'] ?? null,
            'gender' => $d['gender'] ?? null,
            'cell_number' => $d['cell_number'] ?? null,
            'email' => $d['email'] ?? null,
            'passport' => $d['passport'] ?? null,
            'occupation' => $d['occupation'] ?? null,
            'status' => $d['status'] ?? null,
            'area_code' => $d['area_code'] ?? null,
            'inc_basic' => $inc['basic'] ?? 0,
            'inc_leave' => $inc['leave'] ?? 0,
            'inc_overtime' => $inc['overtime'] ?? 0,
            'inc_bonus' => $inc['bonus'] ?? 0,
            'inc_allowance' => $inc['allowance'] ?? 0,
            'inc_other' => $inc['other'] ?? 0,
            'inc_variable_total' => $inc['variable_total'] ?? 0,
            'inc_net_salary' => $inc['net_salary_reported'] ?? 0,
            'exp_system_deductions' => $ded['system_deductions'] ?? 0,
            'exp_payroll_import' => $ded['payroll_import'] ?? 0,
            'exp_total' => $ded['total'] ?? 0,
            'check_afford' => $a['check_afford'] ?? false,
            'affordability_tier_used' => $a['tier_used'] ?? null,
            'affordability_take_home_percent' => $a['take_home_percent_used'] ?? null,
            'affordability_min_take_home' => $a['min_take_home_used'] ?? null,
            'affordability_effective_income' => $a['effective_income_used'] ?? null,
            'affordability_protected_income' => $a['protected_income'] ?? null,
            'affordability_max_deduction' => $a['max_deduction_amount'] ?? null,
            'affordability_existing_deductions' => $a['existing_deductions'] ?? 0,
            'affordability_remaining' => $a['remaining_amount'] ?? null,
            'affordability_utilization' => $a['utilisation_percent'] ?? 0,
            'affordability_last_updated' => $a['last_updated'] ?? null,
            'affordability_manual_override' => $a['manual_override'] ?? false,
        ];
    }
}
