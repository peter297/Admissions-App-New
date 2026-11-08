<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\User;
use App\Support\RoleBranchRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

/** Super Admin only (see the routes). */
class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $branches = Branch::orderBy('name')->get(['id', 'name']);
        $branchNames = $branches->pluck('name', 'id');

        $users = User::with('roles:id,name')->orderBy('name')->get()->map(fn (User $u) => [
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'role' => $u->roles->first()?->name,
            'branch_id' => $u->branch_id,
            'branch' => $u->branch_id ? ($branchNames[$u->branch_id] ?? null) : null,
            'is_self' => $u->id === $request->user()->id,
        ]);

        return Inertia::render('school-settings/users', [
            'users' => $users,
            'roles' => Role::orderBy('name')->pluck('name'),
            'branches' => $branches,
            'roleRules' => RoleBranchRules::forFrontend(),
            'isSuperAdmin' => true,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', Rule::exists('roles', 'name')],
            'branch_id' => ['nullable', 'exists:branches,id'],
            'password' => ['required', Password::defaults()],
        ]);

        $user = new User();
        $user->forceFill([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'branch_id' => RoleBranchRules::branchIdFor($data['role'], $data['branch_id'] ?? null),
            // The 'verified' middleware guards every page, and an admin-created user has no email to verify.
            'email_verified_at' => now(),
        ])->save();

        $user->syncRoles([$data['role']]);

        return back()->with('success', "{$user->name} can now log in as {$data['role']}.");
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'role' => ['required', Rule::exists('roles', 'name')],
            'branch_id' => ['nullable', 'exists:branches,id'],
            'password' => ['nullable', Password::defaults()],
        ]);

        $currentRole = $user->roles->first()?->name;

        if ($data['role'] !== $currentRole) {
            if ($user->id === $request->user()->id) {
                throw ValidationException::withMessages(['role' => 'You cannot change your own role.']);
            }
            if ($currentRole === 'Super Admin' && $this->superAdminCount() <= 1) {
                throw ValidationException::withMessages(['role' => 'This is the only Super Admin. Make another user a Super Admin first.']);
            }
        }

        $user->forceFill([
            'name' => $data['name'],
            'email' => $data['email'],
            'branch_id' => RoleBranchRules::branchIdFor($data['role'], $data['branch_id'] ?? null),
        ]);

        if (! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }

        $user->save();
        $user->syncRoles([$data['role']]);

        return back()->with('success', "{$user->name} updated.");
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->id === $request->user()->id) {
            throw ValidationException::withMessages(['action' => 'You cannot delete your own account.']);
        }
        if ($user->hasRole('Super Admin') && $this->superAdminCount() <= 1) {
            throw ValidationException::withMessages(['action' => 'This is the only Super Admin and cannot be deleted.']);
        }

        $user->delete();

        return back()->with('success', 'User deleted.');
    }

    private function superAdminCount(): int
    {
        return User::role('Super Admin')->count();
    }
}
