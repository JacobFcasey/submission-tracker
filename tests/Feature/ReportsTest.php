<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Municipality;
use App\Models\MunicipalityDeadline;
use App\Models\Uploads;
use App\Models\User;
use App\Models\UserAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ReportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_reports_index_loads_with_filtered_preview_data(): void
    {
        [$user, $municipality, $company] = $this->seedReportContext();

        Uploads::create([
            'reference' => 'REP-0001',
            'company_id' => $company->id,
            'municipality_id' => $municipality->id,
            'user_id' => $user->id,
            'status' => 'Completed',
            'submitted_at' => now(),
            'original_file_path' => ['uploads/example.eml'],
            'original_file_names' => ['example.eml'],
            'workings_file_path' => 'uploads/workings.xlsx',
            'workings_file_name' => 'workings.xlsx',
            'systems_import_file_path' => 'uploads/system.csv',
            'systems_import_file_name' => 'system.csv',
            'extracted_dates' => ['2026-04-01'],
            'system_import_date' => now(),
        ]);

        $response = $this->actingAs($user)->get('/admin/reports');

        $response->assertOk();
        $response->assertSee('Admin\/Reports\/Index');
        $response->assertSee('REP-0001');
    }

    public function test_reports_csv_export_downloads(): void
    {
        [$user, $municipality, $company] = $this->seedReportContext();

        Uploads::create([
            'reference' => 'REP-CSV-1',
            'company_id' => $company->id,
            'municipality_id' => $municipality->id,
            'user_id' => $user->id,
            'status' => 'Pending',
            'submitted_at' => now(),
            'original_file_path' => ['uploads/example.eml'],
            'original_file_names' => ['example.eml'],
            'extracted_dates' => ['2026-04-01'],
        ]);

        $response = $this->actingAs($user)->get('/admin/reports/export?format=csv');

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $response->assertHeader('content-disposition');
        $this->assertStringContainsString('REP-CSV-1', $response->streamedContent());
    }

    public function test_report_summary_endpoints_return_preview_and_download(): void
    {
        [$user, $municipality, $company] = $this->seedReportContext();

        $deadline = MunicipalityDeadline::create([
            'municipality_id' => $municipality->id,
            'deadline_date' => now()->addDays(3)->toDateString(),
            'notes' => 'Preview deadline',
        ]);

        UserAssignment::create([
            'user_id' => $user->id,
            'municipality_id' => $municipality->id,
            'company_id' => $company->id,
            'deadline_date' => $deadline->deadline_date,
            'notes' => 'Assigned',
        ]);

        Uploads::create([
            'reference' => 'REP-SUM-1',
            'company_id' => $company->id,
            'municipality_id' => $municipality->id,
            'user_id' => $user->id,
            'status' => 'Completed',
            'submitted_at' => now(),
            'original_file_path' => ['uploads/example.eml'],
            'original_file_names' => ['example.eml'],
            'extracted_dates' => ['2026-04-01'],
        ]);

        $uploadSummaryResponse = $this->actingAs($user)->getJson('/admin/reports/upload-summary');
        $uploadSummaryResponse->assertOk()->assertJsonStructure([
            'stats',
            'status_breakdown',
            'municipality_performance',
            'daily_volume',
        ]);

        $deadlineSummaryResponse = $this->actingAs($user)->getJson('/admin/reports/deadline-summary');
        $deadlineSummaryResponse->assertOk()->assertJsonStructure([
            'stats',
            'rows',
        ]);

        $deadlineDownload = $this->actingAs($user)->get('/admin/reports/deadline-summary?download=1');
        $deadlineDownload->assertOk();
        $deadlineDownload->assertHeader('content-disposition');
        $this->assertStringContainsString($municipality->name, $deadlineDownload->streamedContent());
    }

    private function seedReportContext(): array
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permission = Permission::findOrCreate('view reports', 'web');

        $user = User::factory()->create();
        $user->givePermissionTo($permission);

        $municipality = Municipality::factory()->create([
            'name' => 'Metro Alpha',
            'code' => 'MA1',
        ]);

        $company = Company::factory()->create([
            'name' => 'Alpha Services',
            'municipality_id' => $municipality->id,
            'status' => 'active',
        ]);

        return [$user, $municipality, $company];
    }
}
