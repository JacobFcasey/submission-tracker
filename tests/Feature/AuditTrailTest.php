<?php

namespace Tests\Feature;

use App\Models\Audit;
use App\Models\Company;
use App\Models\Municipality;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AuditTrailTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_and_logout_are_recorded_in_audit_trail(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $user = User::factory()->create([
            'employee_number' => 'EMP001',
            'password' => Hash::make('secret123'),
        ]);

        $this->post('/login', [
            'employee_number' => 'EMP001',
            'password' => 'secret123',
        ])->assertRedirect('/dashboard');

        $this->assertDatabaseHas('audits', [
            'event' => 'logged_in',
            'user_id' => $user->id,
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
        ]);

        $this->post('/logout')->assertRedirect('/login');

        $this->assertDatabaseHas('audits', [
            'event' => 'logged_out',
            'user_id' => $user->id,
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
        ]);
    }

    public function test_model_changes_are_written_to_audits_table(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $municipality = Municipality::create([
            'name' => 'City of Audit',
            'province' => 'Gauteng',
            'code' => 'COA',
        ]);

        $company = Company::create([
            'name' => 'Tracked Co',
            'registration_number' => 'REG-1',
            'status' => 'active',
            'contact_email' => 'tracked@example.com',
            'municipality_id' => $municipality->id,
        ]);

        $company->update([
            'contact_email' => 'updated@example.com',
        ]);

        $company->delete();

        $this->assertDatabaseHas('audits', [
            'event' => 'created',
            'auditable_type' => Municipality::class,
            'auditable_id' => $municipality->id,
        ]);

        $this->assertDatabaseHas('audits', [
            'event' => 'created',
            'auditable_type' => Company::class,
            'auditable_id' => $company->id,
        ]);

        $this->assertDatabaseHas('audits', [
            'event' => 'updated',
            'auditable_type' => Company::class,
            'auditable_id' => $company->id,
        ]);

        $this->assertDatabaseHas('audits', [
            'event' => 'deleted',
            'auditable_type' => Company::class,
            'auditable_id' => $company->id,
        ]);

        $updateAudit = Audit::query()
            ->where('event', 'updated')
            ->where('auditable_type', Company::class)
            ->where('auditable_id', $company->id)
            ->latest('id')
            ->first();

        $this->assertSame('tracked@example.com', data_get($updateAudit?->old_values, 'contact_email'));
        $this->assertSame('updated@example.com', data_get($updateAudit?->new_values, 'contact_email'));
    }

    public function test_audit_details_page_loads(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permission = Permission::findOrCreate('view audits', 'web');
        $user = User::factory()->create();
        $user->givePermissionTo($permission);

        $audit = Audit::create([
            'user_type' => User::class,
            'user_id' => $user->id,
            'event' => 'logged_in',
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
            'old_values' => [],
            'new_values' => ['employee_number' => $user->employee_number],
            'url' => '/login',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'tags' => 'logged_in,login.store,post',
        ]);

        $response = $this->actingAs($user)->get("/admin/audits/{$audit->id}");

        $response->assertOk();
        $response->assertSee('Admin\/Audits\/Show');
        $response->assertSee((string) $audit->id);
        $response->assertSee('logged_in');
    }
}
