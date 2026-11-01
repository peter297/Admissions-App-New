import { Head, router, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';

import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';

type Route = { id: number; name: string };
type Branch = { id: number; name: string };
type BusRow = {
    id: number;
    number_plate: string;
    driver_name: string;
    assistant_name: string | null;
    branch_id: number;
    branch: string;
    transport_route_id: number | null;
    route: string | null;
};

type Props = { buses: BusRow[]; routes: Route[]; branches: Branch[]; canPickBranch: boolean };

const NONE = 'none';

/* ---------------- Bus dialog ---------------- */
function BusDialog({
    bus,
    routes,
    branches,
    canPickBranch,
    onClose,
}: {
    bus: BusRow | null;
    routes: Route[];
    branches: Branch[];
    canPickBranch: boolean;
    onClose: () => void;
}) {
    const form = useForm({
        number_plate: bus?.number_plate ?? '',
        driver_name: bus?.driver_name ?? '',
        assistant_name: bus?.assistant_name ?? '',
        branch_id: String(bus?.branch_id ?? branches[0]?.id ?? ''),
        transport_route_id: bus?.transport_route_id ? String(bus.transport_route_id) : '',
    });
    const { data, setData, errors, processing } = form;

    function save() {
        const options = { onSuccess: onClose, preserveScroll: true };
        if (bus) form.put(`/transport/buses/${bus.id}`, options);
        else form.post('/transport/buses', options);
    }

    return (
        <Dialog open onOpenChange={(o) => !o && onClose()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{bus ? 'Edit bus' : 'Add bus'}</DialogTitle>
                </DialogHeader>

                <div className="flex flex-col gap-3">
                    <div className="flex flex-col gap-2">
                        <Label>Number plate</Label>
                        <Input value={data.number_plate} onChange={(e) => setData('number_plate', e.target.value)} />
                        {errors.number_plate && <p className="text-sm text-destructive">{errors.number_plate}</p>}
                    </div>

                    <div className="flex flex-col gap-2">
                        <Label>Driver name</Label>
                        <Input value={data.driver_name} onChange={(e) => setData('driver_name', e.target.value)} />
                        {errors.driver_name && <p className="text-sm text-destructive">{errors.driver_name}</p>}
                    </div>

                    <div className="flex flex-col gap-2">
                        <Label>Assistant name</Label>
                        <Input
                            value={data.assistant_name}
                            onChange={(e) => setData('assistant_name', e.target.value)}
                        />
                    </div>

                    {canPickBranch && (
                        <div className="flex flex-col gap-2">
                            <Label>Branch</Label>
                            <Select value={data.branch_id} onValueChange={(v) => setData('branch_id', v)}>
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {branches.map((b) => (
                                        <SelectItem key={b.id} value={String(b.id)}>
                                            {b.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {errors.branch_id && <p className="text-sm text-destructive">{errors.branch_id}</p>}
                        </div>
                    )}

                    <div className="flex flex-col gap-2">
                        <Label>Route</Label>
                        <Select
                            value={data.transport_route_id || NONE}
                            onValueChange={(v) => setData('transport_route_id', v === NONE ? '' : v)}
                        >
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={NONE}>No route</SelectItem>
                                {routes.map((r) => (
                                    <SelectItem key={r.id} value={String(r.id)}>
                                        {r.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button onClick={save} disabled={processing}>
                        Save
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

/* ---------------- Route dialog ---------------- */
function RouteDialog({ route, onClose }: { route: Route | null; onClose: () => void }) {
    const form = useForm({ name: route?.name ?? '' });

    function save() {
        const options = { onSuccess: onClose, preserveScroll: true };
        if (route) form.put(`/transport/routes/${route.id}`, options);
        else form.post('/transport/routes', options);
    }

    return (
        <Dialog open onOpenChange={(o) => !o && onClose()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{route ? 'Rename route' : 'Add route'}</DialogTitle>
                </DialogHeader>

                <div className="flex flex-col gap-2">
                    <Label>Route name</Label>
                    <Input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                    {form.errors.name && <p className="text-sm text-destructive">{form.errors.name}</p>}
                </div>

                <DialogFooter>
                    <Button variant="outline" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button onClick={save} disabled={form.processing}>
                        Save
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

/* ---------------- Page ---------------- */
export default function TransportIndex({ buses, routes, branches, canPickBranch }: Props) {
    const [search, setSearch] = useState('');

    // `undefined` = closed, `null` = adding new, an object = editing that row.
    const [busDialog, setBusDialog] = useState<BusRow | null | undefined>(undefined);
    const [routeDialog, setRouteDialog] = useState<Route | null | undefined>(undefined);

    const visible = useMemo(() => {
        const term = search.trim().toLowerCase();
        if (!term) return buses;
        return buses.filter((b) =>
            [b.number_plate, b.driver_name, b.assistant_name ?? '', b.branch, b.route ?? '']
                .join(' ')
                .toLowerCase()
                .includes(term),
        );
    }, [buses, search]);

    function remove(url: string, label: string) {
        if (confirm(`Delete ${label}?`)) router.delete(url, { preserveScroll: true });
    }

    return (
        <>
            <Head title="Transport" />

            <div className="flex flex-col gap-8 p-4">
                {/* Buses */}
                <section className="flex flex-col gap-3">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <h1 className="text-xl font-semibold">Buses</h1>
                        <div className="flex gap-3">
                            <Input
                                className="w-64"
                                placeholder="Search buses..."
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                            />
                            <Button onClick={() => setBusDialog(null)}>Add bus</Button>
                        </div>
                    </div>

                    <div className="rounded-lg border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Number plate</TableHead>
                                    <TableHead>Driver</TableHead>
                                    <TableHead>Assistant</TableHead>
                                    <TableHead>Branch</TableHead>
                                    <TableHead>Route</TableHead>
                                    <TableHead className="w-40" />
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {visible.map((b) => (
                                    <TableRow key={b.id}>
                                        <TableCell className="font-medium">{b.number_plate}</TableCell>
                                        <TableCell>{b.driver_name}</TableCell>
                                        <TableCell>{b.assistant_name ?? '-'}</TableCell>
                                        <TableCell>{b.branch}</TableCell>
                                        <TableCell>{b.route ?? '-'}</TableCell>
                                        <TableCell className="space-x-2 text-right">
                                            <Button size="sm" variant="outline" onClick={() => setBusDialog(b)}>
                                                Edit
                                            </Button>
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                onClick={() => remove(`/transport/buses/${b.id}`, b.number_plate)}
                                            >
                                                Delete
                                            </Button>
                                        </TableCell>
                                    </TableRow>
                                ))}
                                {visible.length === 0 && (
                                    <TableRow>
                                        <TableCell colSpan={6} className="py-8 text-center text-muted-foreground">
                                            No buses found.
                                        </TableCell>
                                    </TableRow>
                                )}
                            </TableBody>
                        </Table>
                    </div>
                </section>

                {/* Routes */}
                <section className="flex flex-col gap-3">
                    <div className="flex items-center justify-between">
                        <h2 className="text-lg font-semibold">Routes</h2>
                        <Button variant="outline" onClick={() => setRouteDialog(null)}>
                            Add route
                        </Button>
                    </div>

                    <div className="rounded-lg border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Route</TableHead>
                                    <TableHead className="w-40" />
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {routes.map((r) => (
                                    <TableRow key={r.id}>
                                        <TableCell className="font-medium">{r.name}</TableCell>
                                        <TableCell className="space-x-2 text-right">
                                            <Button size="sm" variant="outline" onClick={() => setRouteDialog(r)}>
                                                Rename
                                            </Button>
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                onClick={() => remove(`/transport/routes/${r.id}`, r.name)}
                                            >
                                                Delete
                                            </Button>
                                        </TableCell>
                                    </TableRow>
                                ))}
                                {routes.length === 0 && (
                                    <TableRow>
                                        <TableCell colSpan={2} className="py-8 text-center text-muted-foreground">
                                            No routes yet.
                                        </TableCell>
                                    </TableRow>
                                )}
                            </TableBody>
                        </Table>
                    </div>
                </section>
            </div>

            {/* Dialogs are mounted only while open, so their form state starts fresh every time. */}
            {busDialog !== undefined && (
                <BusDialog
                    bus={busDialog}
                    routes={routes}
                    branches={branches}
                    canPickBranch={canPickBranch}
                    onClose={() => setBusDialog(undefined)}
                />
            )}
            {routeDialog !== undefined && (
                <RouteDialog route={routeDialog} onClose={() => setRouteDialog(undefined)} />
            )}
        </>
    );
}
