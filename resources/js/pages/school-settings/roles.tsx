import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';

import SettingsHeader from '@/components/settings-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';

type RoleRow = {
    id: number;
    name: string;
    users: number;
    permissions: string[];
    built_in: boolean;
    locked: boolean;
};

type Group = { label: string; items: { name: string; label: string }[] };

function RoleDialog({ role, groups, onClose }: { role: RoleRow | null; groups: Group[]; onClose: () => void }) {
    const form = useForm<{ name: string; permissions: string[] }>({
        name: role?.name ?? '',
        permissions: role?.permissions ?? [],
    });
    const { data, setData, errors, processing } = form;

    function toggle(name: string, checked: boolean) {
        setData('permissions', checked ? [...data.permissions, name] : data.permissions.filter((p) => p !== name));
    }

    function toggleGroup(group: Group, checked: boolean) {
        const names = group.items.map((i) => i.name);
        const others = data.permissions.filter((p) => !names.includes(p));
        setData('permissions', checked ? [...others, ...names] : others);
    }

    function save() {
        const options = { preserveScroll: true, onSuccess: onClose };
        if (role) form.put(`/settings/roles/${role.id}`, options);
        else form.post('/settings/roles', options);
    }

    return (
        <Dialog open onOpenChange={(o) => !o && onClose()}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>{role ? `Edit ${role.name}` : 'New role'}</DialogTitle>
                    <DialogDescription>Tick what people with this role are allowed to do.</DialogDescription>
                </DialogHeader>

                <div className="flex flex-col gap-2">
                    <Label>Role name</Label>
                    <Input
                        value={data.name}
                        disabled={role?.built_in}
                        onChange={(e) => setData('name', e.target.value)}
                    />
                    {role?.built_in && (
                        <p className="text-xs text-muted-foreground">Built-in roles keep their names.</p>
                    )}
                    {errors.name && <p className="text-sm text-destructive">{errors.name}</p>}
                </div>

                <div className="grid gap-4 sm:grid-cols-2">
                    {groups.map((g) => {
                        const all = g.items.every((i) => data.permissions.includes(i.name));
                        return (
                            <div key={g.label} className="flex flex-col gap-2 rounded-lg border p-3">
                                <label className="flex items-center gap-2 text-sm font-semibold">
                                    <Checkbox checked={all} onCheckedChange={(c) => toggleGroup(g, c === true)} />
                                    {g.label}
                                </label>
                                {g.items.map((i) => (
                                    <label key={i.name} className="flex items-center gap-2 pl-6 text-sm">
                                        <Checkbox
                                            checked={data.permissions.includes(i.name)}
                                            onCheckedChange={(c) => toggle(i.name, c === true)}
                                        />
                                        {i.label}
                                    </label>
                                ))}
                            </div>
                        );
                    })}
                </div>

                <DialogFooter>
                    <Button variant="outline" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button onClick={save} disabled={processing || !data.name.trim()}>
                        Save
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

export default function RolesPage({
    roles,
    groups,
    isSuperAdmin,
}: {
    roles: RoleRow[];
    groups: Group[];
    isSuperAdmin: boolean;
}) {
    // undefined = closed, null = new role, an object = editing that role
    const [dialog, setDialog] = useState<RoleRow | null | undefined>(undefined);

    function remove(r: RoleRow) {
        if (confirm(`Delete the role ${r.name}?`)) {
            router.delete(`/settings/roles/${r.id}`, { preserveScroll: true });
        }
    }

    return (
        <>
            <Head title="Roles and permissions" />

            <div className="flex flex-col gap-4 p-4">
                <SettingsHeader title="School settings" isSuperAdmin={isSuperAdmin} />

                <div className="flex items-center justify-between">
                    <h2 className="text-lg font-semibold">Roles and permissions</h2>
                    <Button onClick={() => setDialog(null)}>New role</Button>
                </div>

                <div className="overflow-x-auto rounded-lg border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Role</TableHead>
                                <TableHead className="text-right">Users</TableHead>
                                <TableHead>Access</TableHead>
                                <TableHead />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {roles.map((r) => (
                                <TableRow key={r.id}>
                                    <TableCell className="font-medium">
                                        {r.name} {r.built_in && <Badge variant="outline">Built in</Badge>}
                                    </TableCell>
                                    <TableCell className="text-right">{r.users}</TableCell>
                                    <TableCell>
                                        {r.locked ? 'Full access, always' : `${r.permissions.length} permissions`}
                                    </TableCell>
                                    <TableCell className="space-x-2 text-right whitespace-nowrap">
                                        {!r.locked && (
                                            <>
                                                <Button size="sm" variant="outline" onClick={() => setDialog(r)}>
                                                    Edit
                                                </Button>
                                                {!r.built_in && (
                                                    <Button size="sm" variant="outline" onClick={() => remove(r)}>
                                                        Delete
                                                    </Button>
                                                )}
                                            </>
                                        )}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>
            </div>

            {/* Mounted only while open, so the form always starts fresh. */}
            {dialog !== undefined && <RoleDialog role={dialog} groups={groups} onClose={() => setDialog(undefined)} />}
        </>
    );
}
