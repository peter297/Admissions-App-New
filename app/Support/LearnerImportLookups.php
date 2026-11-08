<?php

namespace App\Support;

use App\Models\Branch;
use App\Models\Bus;
use App\Models\ClassRoom;
use App\Models\TransportRoute;
use App\Models\User;

/**
 * Turns the text in a whole-school import file (branch, class, route, bus, status)
 * into database ids. Everything is loaded once, so 1,500 rows cost a handful of queries.
 */
class LearnerImportLookups
{
    private array $branches = [];
    private ?Branch $onlyBranch = null;
    private array $classIndex = [];
    private array $routes = [];
    private array $buses = [];

    public function __construct(User $user)
    {
        // A branch admin can only import into their own branch.
        $branchList = Branch::query()
            ->when($user->branch_id, fn ($q) => $q->where('id', $user->branch_id))
            ->get();

        foreach ($branchList as $b) {
            $this->branches[$this->norm($b->name)] = $b;
            $this->branches[$this->norm($b->code)] = $b;
        }
        $this->onlyBranch = $branchList->count() === 1 ? $branchList->first() : null;

        $classes = ClassRoom::query()
            ->with(['gradeLevel:id,name,level', 'stream:id,name'])
            ->whereIn('branch_id', $branchList->pluck('id'))
            ->get();

        foreach ($classes as $c) {
            // "Grade 3 Blue" can be written as "Grade 3 Blue" or just "3 Blue".
            $this->classIndex[$this->norm($c->name)][] = $c;
            $this->classIndex[$this->norm(str_ireplace('grade', '', $c->name))][] = $c;
        }

        foreach (TransportRoute::all() as $r) {
            $this->routes[$this->norm($r->name)] = $r;
        }
        foreach (Bus::all() as $b) {
            $this->buses[$this->norm($b->number_plate)] = $b;
        }
    }

    /** @return array{0: array, 1: array} the ids to save, and any problems found */
    public function resolve(array $refs): array
    {
        $errors = [];
        $branch = null;

        if ($refs['branch'] !== null) {
            $branch = $this->branches[$this->norm($refs['branch'])] ?? null;
            if (! $branch) {
                $errors[] = "Branch {$refs['branch']} was not found, or you do not manage it";
            }
        } else {
            $branch = $this->onlyBranch;
        }

        $classText = $refs['class']
            ?? (($refs['grade'] !== null && $refs['stream'] !== null) ? $refs['grade'].' '.$refs['stream'] : null);

        $class = null;
        if ($classText === null) {
            $errors[] = 'Class is missing';
        } elseif ($refs['branch'] === null || $branch) {
            $found = collect($this->classIndex[$this->norm($classText)] ?? [])
                ->when($branch, fn ($c) => $c->where('branch_id', $branch->id))
                ->unique('id')
                ->values();

            if ($found->isEmpty()) {
                $errors[] = "Class {$classText} was not found".($branch ? " in {$branch->name}" : '');
            } elseif ($found->count() > 1) {
                $errors[] = "Branch is needed, because several branches have a class called {$classText}";
            } else {
                $class = $found->first();
            }
        }

        $status = 'active';
        if ($refs['status'] !== null) {
            $status = match ($this->norm($refs['status'])) {
                'active' => 'active',
                'inactive' => 'inactive',
                'transferred', 'transfer' => 'transferred',
                default => null,
            };
            if ($status === null) {
                $errors[] = 'Status must be Active, Inactive or Transferred';
                $status = 'active';
            }
        }

        $route = null;
        if ($refs['route'] !== null) {
            $route = $this->routes[$this->norm($refs['route'])] ?? null;
            if (! $route) $errors[] = "Transport route {$refs['route']} does not exist. Add it under Transport first";
        }

        $bus = null;
        if ($refs['bus'] !== null) {
            $bus = $this->buses[$this->norm($refs['bus'])] ?? null;
            if (! $bus) {
                $errors[] = "Bus {$refs['bus']} does not exist. Add it under Transport first";
            } elseif ($class && (int) $bus->branch_id !== (int) $class->branch_id) {
                $errors[] = "Bus {$refs['bus']} belongs to a different branch from the class";
            }
        }

        return [[
            'class_id' => $class?->id,
            'branch_id' => $class?->branch_id,
            'status' => $status,
            'transport_route_id' => $route?->id,
            'bus_id' => $bus?->id,
        ], $errors];
    }

    private function norm(string $s): string
    {
        return preg_replace('/[^a-z0-9]/', '', strtolower($s));
    }
}
