<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Company;
use App\Models\Municipality;
use App\Models\MunicipalityDeadline;
use App\Support\AuditLogger;
use App\Notifications\Admin\UserEditAction;
use Spatie\Permission\Models\Role;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    public function index()
    {
        $users = User::with('roles')
            ->withCount('assignments')
            ->orderBy('name')
            ->paginate(20);

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'roles' => Role::all()->pluck('name'),
        ]);
    }

    public function edit(User $user)
    {
        $user->load('roles', 'assignments.municipality', 'assignments.company');

        // Get municipalities with their existing deadlines
        $municipalities = Municipality::with('deadlines')
            ->orderBy('name')
            ->get();

        // Get all companies with municipality
        $companies = Company::with('municipality')
            ->orderBy('name')
            ->get();

        // Prepare existing deadlines data
        $existingDeadlines = [];
        foreach ($municipalities as $municipality) {
            if ($municipality->deadlines->count() > 0) {
                $existingDeadlines[$municipality->id] = $municipality->deadlines->map(function($deadline) {
                    return [
                        'id' => $deadline->id,
                        'deadline_date' => $deadline->deadline_date->format('Y-m-d'),
                        'notes' => $deadline->notes,
                    ];
                })->toArray();
            }
        }

        return Inertia::render('Admin/Users/Edit', [
            'user' => $user,
            'roles' => Role::all()->pluck('name'),
            'municipalities' => $municipalities,
            'companies' => $companies,
            'userRoles' => $user->getRoleNames(),
            'userAssignments' => $user->assignments()->with(['municipality', 'company'])->get(),
            'existingDeadlines' => $existingDeadlines,
        ]);
    }

    public function update(Request $request, User $user)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'employee_number' => 'required|string|unique:users,employee_number,' . $user->id,
            'roles' => 'array',
            'password' => 'nullable|confirmed|min:8',
        ]);

        $before = [
            'name' => $user->name,
            'email' => $user->email,
            'employee_number' => $user->employee_number,
            'roles' => $user->getRoleNames()->sort()->values()->all(),
        ];

        $user->update([
            'name' => $request->name,
            'email' => $request->email,
            'employee_number' => $request->employee_number,
        ]);

        $passwordUpdated = false;
        if ($request->filled('password')) {
            $user->update(['password' => Hash::make($request->password)]);
            $passwordUpdated = true;
        }

        if ($request->has('roles')) {
            $user->syncRoles($request->roles);
        }

        $afterRoles = $user->getRoleNames()->sort()->values()->all();
        $changedFields = [];
        foreach (['name', 'email', 'employee_number'] as $field) {
            if ((string) $before[$field] !== (string) $user->{$field}) {
                $changedFields[] = $field;
            }
        }
        $rolesChanged = $before['roles'] !== $afterRoles;

        if (!empty($changedFields) || $rolesChanged || $passwordUpdated) {
            AuditLogger::requestEvent('updated', auth()->user(), [
                'subject' => 'user_admin_update',
                'target_user_id' => $user->id,
                'target_user_name' => $user->name,
                'changed_fields' => $changedFields,
                'roles_before' => $before['roles'],
                'roles_after' => $afterRoles,
                'password_updated' => $passwordUpdated,
            ]);

            $messageParts = [];
            if (!empty($changedFields)) {
                $messageParts[] = 'profile details updated';
            }
            if ($rolesChanged) {
                $messageParts[] = 'roles updated';
            }
            if ($passwordUpdated) {
                $messageParts[] = 'password reset';
            }

            $user->notify(new UserEditAction(
                actionType: 'user_updated',
                message: 'Your account was updated by an administrator: ' . implode(', ', $messageParts) . '.',
                meta: [
                    'target_user_id' => $user->id,
                    'target_user_name' => $user->name,
                    'changed_fields' => $changedFields,
                    'roles_before' => $before['roles'],
                    'roles_after' => $afterRoles,
                    'password_updated' => $passwordUpdated,
                    'updated_by_user_id' => auth()->id(),
                    'updated_by_user_name' => auth()->user()?->name,
                ]
            ));
        }

        return redirect()->back()
            ->with('success', 'User updated successfully');
    }

    public function destroy(User $user)
    {
        if ($user->id === auth()->id()) {
            return redirect()->back()->with('error', 'You cannot delete your own account');
        }

        $user->delete();

        return redirect()->route('admin.users.index')
            ->with('success', 'User deleted successfully');
    }

    public function assignMunicipality(Request $request, User $user)
    {
        $request->validate([
            'municipality_id' => 'required|exists:municipalities,id',
            'company_ids' => 'required|array|min:1',
            'company_ids.*' => 'exists:companies,id',
            'deadline_date' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        try {
            $createdSummary = [
                'municipality_id' => null,
                'municipality_name' => null,
                'deadline_date' => null,
                'company_count' => 0,
                'company_names' => [],
            ];

            DB::transaction(function () use ($request, $user, &$createdSummary) {
                $municipality = Municipality::findOrFail($request->municipality_id);

                // Determine the deadline date
                $deadlineDate = null;

                // Check if municipality has existing deadlines
                $existingDeadline = MunicipalityDeadline::where('municipality_id', $municipality->id)
                    ->orderBy('deadline_date', 'asc')
                    ->first();

                if ($existingDeadline) {
                    // Use existing deadline date
                    $deadlineDate = $existingDeadline->deadline_date;
                } elseif ($request->deadline_date) {
                    // Create new deadline for municipality
                    $deadlineDate = $request->deadline_date;

                    MunicipalityDeadline::create([
                        'municipality_id' => $municipality->id,
                        'deadline_date' => $deadlineDate,
                        'notes' => $request->notes ?? 'Deadline created via user assignment',
                    ]);
                } else {
                    throw new \Exception('Municipality has no existing deadlines. Please provide a deadline date.');
                }

                $assignments = [];
                $companies = Company::whereIn('id', $request->company_ids)->get();

                // Create assignments for each company
                foreach ($companies as $company) {
                    // Check if assignment already exists
                    $existingAssignment = $user->assignments()
                        ->where('municipality_id', $municipality->id)
                        ->where('company_id', $company->id)
                        ->where('deadline_date', $deadlineDate)
                        ->first();

                    if ($existingAssignment) {
                        // Update existing assignment
                        $existingAssignment->update([
                            'notes' => $request->notes,
                        ]);
                        $assignments[] = $existingAssignment;
                    } else {
                        // Create new assignment
                        $assignment = $user->assignments()->create([
                            'municipality_id' => $municipality->id,
                            'company_id' => $company->id,
                            'deadline_date' => $deadlineDate,
                            'notes' => $request->notes,
                        ]);
                        $assignments[] = $assignment;
                    }
                }

                $createdSummary = [
                    'municipality_id' => $municipality->id,
                    'municipality_name' => $municipality->name,
                    'deadline_date' => (string) $deadlineDate,
                    'company_count' => count($assignments),
                    'company_names' => $companies->pluck('name')->values()->all(),
                ];

                // Store assignment data in session for other pages to pick up
                session()->flash('assignment_created', [
                    'message' => 'New assignment(s) created',
                    'user_name' => $user->name,
                    'municipality_name' => $municipality->name,
                    'company_count' => count($assignments),
                    'deadline_date' => $deadlineDate,
                ]);
            });

            if ($createdSummary['company_count'] > 0) {
                AuditLogger::requestEvent('created', auth()->user(), [
                    'subject' => 'user_assignment',
                    'target_user_id' => $user->id,
                    'target_user_name' => $user->name,
                    'municipality_id' => $createdSummary['municipality_id'],
                    'municipality_name' => $createdSummary['municipality_name'],
                    'deadline_date' => $createdSummary['deadline_date'],
                    'company_count' => $createdSummary['company_count'],
                    'company_names' => $createdSummary['company_names'],
                ]);

                $user->notify(new UserEditAction(
                    actionType: 'assignment_created',
                    message: "You were assigned {$createdSummary['company_count']} compan" .
                        ($createdSummary['company_count'] === 1 ? 'y' : 'ies') .
                        " in {$createdSummary['municipality_name']}.",
                    meta: [
                        'target_user_id' => $user->id,
                        'target_user_name' => $user->name,
                        'municipality_id' => $createdSummary['municipality_id'],
                        'municipality_name' => $createdSummary['municipality_name'],
                        'deadline_date' => $createdSummary['deadline_date'],
                        'company_count' => $createdSummary['company_count'],
                        'company_names' => $createdSummary['company_names'],
                        'updated_by_user_id' => auth()->id(),
                        'updated_by_user_name' => auth()->user()?->name,
                    ]
                ));
            }

            return redirect()->back()->with('success', count($request->company_ids) . ' assignment(s) created successfully');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to create assignments: ' . $e->getMessage());
        }
    }

    public function removeAssignment(User $user, $assignmentId)
    {
        $assignment = $user->assignments()->findOrFail($assignmentId);
        $municipality = $assignment->municipality;
        $company = $assignment->company;
        $deadlineDate = $assignment->deadline_date;

        $assignment->delete();

        AuditLogger::requestEvent('deleted', auth()->user(), [
            'subject' => 'user_assignment',
            'target_user_id' => $user->id,
            'target_user_name' => $user->name,
            'municipality_id' => $municipality->id,
            'municipality_name' => $municipality->name,
            'company_id' => $company->id,
            'company_name' => $company->name,
            'deadline_date' => (string) $deadlineDate,
        ]);

        $user->notify(new UserEditAction(
            actionType: 'assignment_removed',
            message: "Your assignment for {$company->name} in {$municipality->name} was removed.",
            meta: [
                'target_user_id' => $user->id,
                'target_user_name' => $user->name,
                'municipality_id' => $municipality->id,
                'municipality_name' => $municipality->name,
                'company_id' => $company->id,
                'company_name' => $company->name,
                'deadline_date' => (string) $deadlineDate,
                'updated_by_user_id' => auth()->id(),
                'updated_by_user_name' => auth()->user()?->name,
            ]
        ));

        return redirect()->back()->with('success', 'Assignment removed successfully');
    }
}
