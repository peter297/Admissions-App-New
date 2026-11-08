<?php

namespace App\Support;

use App\Models\Branch;

/**
 * Which branch a user of each built-in role belongs to.
 *   all   = sees every branch, so the user has no branch
 *   fixed = always tied to one named branch
 *   free  = any role you create yourself: the branch is up to you
 */
class RoleBranchRules
{
    public const BUILT_IN = [
        'Super Admin', 'Principal', 'Deputy Principal',
        'Admin (Juja Road)', 'Admin (Kitisuru)', 'Admin (South C)', 'Head of Admissions',
    ];

    private const RULES = [
        'Super Admin' => ['mode' => 'all'],
        'Principal' => ['mode' => 'all'],
        'Head of Admissions' => ['mode' => 'all'],
        'Deputy Principal' => ['mode' => 'fixed', 'branch' => 'Kitisuru'],
        'Admin (Juja Road)' => ['mode' => 'fixed', 'branch' => 'Juja Road'],
        'Admin (Kitisuru)' => ['mode' => 'fixed', 'branch' => 'Kitisuru'],
        'Admin (South C)' => ['mode' => 'fixed', 'branch' => 'South C'],
    ];

    public static function for(string $role): array
    {
        return self::RULES[$role] ?? ['mode' => 'free'];
    }

    /** The branch id a user of this role must have, or null for "no branch". 'free' returns the given value. */
    public static function branchIdFor(string $role, mixed $chosen): ?int
    {
        $rule = self::for($role);

        return match ($rule['mode']) {
            'all' => null,
            'fixed' => Branch::where('name', $rule['branch'])->value('id'),
            default => $chosen ? (int) $chosen : null,
        };
    }

    /** For the React form: role name => mode and branch id. */
    public static function forFrontend(): array
    {
        $ids = Branch::pluck('id', 'name');

        return collect(self::RULES)->map(fn ($r) => [
            'mode' => $r['mode'],
            'branch_id' => isset($r['branch']) ? ($ids[$r['branch']] ?? null) : null,
        ])->all();
    }
}
