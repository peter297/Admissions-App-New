<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Bus;
use App\Models\TransportRoute;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TransportController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $branches = $this->visibleBranches($user);

        $buses = Bus::query()
            ->with(['branch:id,name', 'transportRoute:id,name'])
            ->when($user->branch_id, fn ($q) => $q->where('branch_id', $user->branch_id))
            ->orderBy('number_plate')
            ->get()
            ->map(fn (Bus $b) => [
                'id' => $b->id,
                'number_plate' => $b->number_plate,
                'driver_name' => $b->driver_name,
                'assistant_name' => $b->assistant_name,
                'branch_id' => $b->branch_id,
                'branch' => $b->branch->name,
                'transport_route_id' => $b->transport_route_id,
                'route' => $b->transportRoute?->name,
            ]);

        return Inertia::render('transport/index', [
            'buses' => $buses,
            'routes' => TransportRoute::orderBy('name')->get(['id', 'name']),
            'branches' => $branches,
            'canPickBranch' => $branches->count() > 1,
        ]);
    }

    /* ---------- Routes ---------- */
    public function storeRoute(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255', 'unique:transport_routes,name']]);
        TransportRoute::create($data);

        return back();
    }

    public function updateRoute(Request $request, TransportRoute $transportRoute): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('transport_routes', 'name')->ignore($transportRoute->id)],
        ]);
        $transportRoute->update($data);

        return back();
    }

    public function destroyRoute(TransportRoute $transportRoute): RedirectResponse
    {
        // Foreign keys are nullOnDelete, so buses and learners simply lose the route.
        $transportRoute->delete();

        return back();
    }

    /* ---------- Buses ---------- */
    public function storeBus(Request $request): RedirectResponse
    {
        Bus::create($this->validatedBus($request, null));

        return back();
    }

    public function updateBus(Request $request, Bus $bus): RedirectResponse
    {
        $this->authorizeBus($request, $bus);
        $bus->update($this->validatedBus($request, $bus));

        return back();
    }

    public function destroyBus(Request $request, Bus $bus): RedirectResponse
    {
        $this->authorizeBus($request, $bus);
        $bus->delete();

        return back();
    }

    /* ---------- Helpers ---------- */
    private function validatedBus(Request $request, ?Bus $bus): array
    {
        $user = $request->user();

        $data = $request->validate([
            'number_plate' => ['required', 'string', 'max:20', Rule::unique('buses', 'number_plate')->ignore($bus?->id)],
            'driver_name' => ['required', 'string', 'max:255'],
            'assistant_name' => ['nullable', 'string', 'max:255'],
            'branch_id' => ['required', Rule::in($this->visibleBranches($user)->pluck('id')->all())],
            'transport_route_id' => ['nullable', 'exists:transport_routes,id'],
        ]);

        $data['number_plate'] = strtoupper($data['number_plate']);

        return $data;
    }

    private function visibleBranches($user)
    {
        return Branch::query()
            ->when($user->branch_id, fn ($q) => $q->where('id', $user->branch_id))
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    private function authorizeBus(Request $request, Bus $bus): void
    {
        $branchId = $request->user()->branch_id;
        abort_if($branchId && $branchId !== $bus->branch_id, 403);
    }
}
