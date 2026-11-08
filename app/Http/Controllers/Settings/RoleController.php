<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Support\PermissionCatalog;
use App\Support\RoleBranchRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/** Super Admin only (see the routes): this screen can grant any permission to anyone. */
class RoleController extends Controller
{
    public function index(): Response
    {
        $existing = Permission::pluck('name')->all();

        // Only offer permissions that exist in the database, so saving can never fail on a missing one.
        $groups = collect(PermissionCatalog::groups())
            ->map(fn ($g) => ['label' => $g['label'], 'items' => collect($g['items'])->whereIn('name', $existing)->values()->all()])
            ->filter(fn ($g) => count($g['items']) > 0)
            ->values();

        $roles = Role::withCount('users')->with('permissions:id,name')->orderBy('name')->get()
            ->map(fn (Role $r) => [
                'id' => $r->id,
                'name' => $r->name,
                'users' => $r->users_count,
                'permissions' => $r->permissions->pluck('name')->values(),
                'built_in' => in_array($r->name, RoleBranchRules::BUILT_IN, true),
                'locked' => $r->name === 'Super Admin',
            ]);

        return Inertia::render('school-settings/roles', [
            'roles' => $roles,
            'groups' => $groups,
            'isSuperAdmin' => true,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:roles,name'],
            'permissions' => ['array'],
            'permissions.*' => ['string', Rule::in($this->allowedPermissions())],
        ]);

        $role = Role::create(['name' => $data['name'], 'guard_name' => 'web']);
        $role->syncPermissions($data['permissions'] ?? []);

        return back()->with('success', "Role {$role->name} created.");
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $this->refuseSuperAdmin($role);

        $builtIn = in_array($role->name, RoleBranchRules::BUILT_IN, true);

        $data = $request->validate([
            // Built-in roles keep their names, because other rules (like their branch) depend on them.
            'name' => $builtIn
                ? ['required', Rule::in([$role->name])]
                : ['required', 'string', 'max:100', Rule::unique('roles', 'name')->ignore($role->id)],
            'permissions' => ['array'],
            'permissions.*' => ['string', Rule::in($this->allowedPermissions())],
        ]);

        $role->update(['name' => $data['name']]);
        $role->syncPermissions($data['permissions'] ?? []);

        return back()->with('success', "Role {$role->name} updated.");
    }

    public function destroy(Role $role): RedirectResponse
    {
        $this->refuseSuperAdmin($role);

        if (in_array($role->name, RoleBranchRules::BUILT_IN, true)) {
            throw ValidationException::withMessages(['action' => 'The built-in roles cannot be deleted.']);
        }
        if ($role->users()->exists()) {
            throw ValidationException::withMessages(['action' => "{$role->name} still has users. Move them to another role first."]);
        }

        $role->delete();

        return back()->with('success', "Role {$role->name} deleted.");
    }

    private function refuseSuperAdmin(Role $role): void
    {
        if ($role->name === 'Super Admin') {
            throw ValidationException::withMessages(['action' => 'Super Admin always has full access and cannot be changed.']);
        }
    }

    private function allowedPermissions(): array
    {
        return Permission::whereIn('name', PermissionCatalog::names())->pluck('name')->all();
    }
}
