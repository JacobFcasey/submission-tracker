<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\AuditLogger;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Inertia\Inertia;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    public function create()
    {
        $this->authorize('manage roles');

        $permissions = Permission::all()
            ->groupBy(function ($permission) {
                $parts = explode(' ', $permission->name);
                return count($parts) > 1 ? $parts[1] : 'general';
            });

        return Inertia::render('Admin/Roles/Create', [
            'permissions' => $permissions,
        ]);
    }

    public function index(Request $request)
    {
        $this->authorize('manage roles');

        $filters = $request->only(['search']);

        $roles = Role::with('permissions')
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where('name', 'like', "%{$search}%");
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $permissions = Permission::all();

        return Inertia::render('Admin/Roles/Index', [
            'roles' => $roles,
            'permissions' => $permissions,
            'filters' => $filters,
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('manage roles');

        $request->validate([
            'name' => 'required|string|max:255|unique:roles,name',
        ]);

        $role = Role::create(['name' => $request->name, 'guard_name' => 'web']);

        AuditLogger::requestEvent('created', auth()->user(), [
            'subject' => 'role',
            'role_id' => $role->id,
            'role_name' => $role->name,
        ]);

        return redirect()->back()->with('success', 'Role created successfully.');
    }

    public function edit(Role $role)
    {
        $this->authorize('manage roles');

        return redirect()->route('admin.roles.index');
    }

    public function update(Request $request, Role $role)
    {
        $this->authorize('manage roles');

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:roles,name,' . $role->id,
        ]);

        $oldName = $role->name;

        $role->update([
            'name' => $validated['name'],
        ]);

        AuditLogger::requestEvent('updated', auth()->user(), [
            'subject' => 'role',
            'role_id' => $role->id,
            'old_name' => $oldName,
            'new_name' => $role->name,
        ]);

        return redirect()->back()->with('success', 'Role updated successfully.');
    }

    public function updatePermissions(Request $request, Role $role)
    {
        $this->authorize('manage roles');

        $request->validate([
            'permission_id' => 'required|exists:permissions,id',
            'action' => 'required|in:attach,detach',
        ]);

        $permission = Permission::find($request->permission_id);

        if ($request->action === 'attach') {
            $role->givePermissionTo($permission);
            $message = "Permission {$permission->name} added to role {$role->name}";
            $event = 'permission_attached';
        } else {
            $role->revokePermissionTo($permission);
            $message = "Permission {$permission->name} removed from role {$role->name}";
            $event = 'permission_detached';
        }

        AuditLogger::requestEvent($event, auth()->user(), [
            'subject' => 'role_permission',
            'role_id' => $role->id,
            'role_name' => $role->name,
            'permission_id' => $permission->id,
            'permission_name' => $permission->name,
        ]);

        return redirect()->back()->with('success', $message);
    }

    public function destroy(Role $role)
    {
        $this->authorize('manage roles');

        // Prevent deletion of essential roles
        if (in_array($role->name, ['super-admin', 'admin', 'superadmin'])) {
            return redirect()->back()->with('error', 'Cannot delete essential roles.');
        }

        $payload = [
            'subject' => 'role',
            'role_id' => $role->id,
            'role_name' => $role->name,
        ];

        $role->delete();

        AuditLogger::requestEvent('deleted', auth()->user(), $payload);

        return redirect()->back()->with('success', 'Role deleted successfully.');
    }
}
