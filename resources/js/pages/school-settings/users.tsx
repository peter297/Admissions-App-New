import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';

import SettingsHeader from '@/components/settings-header';
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

type UserRow = {
    id: number;
    name: string;
    email: string;
    role: string | null;
    branch_id: number | null;
    branch: string | null;
    is_self: boolean;
};

type Rule = { mode: 'all' | 'fixed' | 'free'; branch_id: number | null };

type Props = {
    users: UserRow[];
    roles: string[];
    branches: { id: number; name: string }[];
    roleRules: Record<string, Rule>;
    isSuperAdmin: boolean;
};

const NONE = 'none';

function UserDialog({
    user,
    roles,
    branches,
    roleRules,
    onClose,
}: {
    user: UserRow | null;
    roles: string[];
    branches: Props['branches'];
    roleRules: Props['roleRules'];
    onClose: () => void;
}) {
    const form = useForm({
        name: user?.name ?? '',
        email: user?.email ?? '',
        role: user?.role ?? '',
        branch_id: user?.branch_id ? String(user.branch_id) : '',
        password: '',
    });
    const { data, setData, errors, processing } = form;

    const rule: Rule = roleRules[data.role] ?? { mode: 'free', branch_id: null };

    function chooseRole(role: string) {
        const r = roleRules[role] ?? { mode: 'free', branch_id: null };
        // Built-in roles decide the branch for you: one fixed branch, or none (all branches).
        setData((prev) => ({
            ...prev,
            role,
            branch_id: r.mode === 'fixed' ? String(r.branch_id ?? '') : r.mode === 'all' ? '' : prev.branch_id,
        }));
    }

    function save() {
        const options = { preserveScroll: true, onSuccess: onClose };
        if (user) form.put(`/settings/users/${user.id}`, options);
        else form.post('/settings/users', options);
    }

    return (
        <Dialog open onOpenChange={(o) => !o && onClose()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{user ? `Edit ${user.name}` : 'New user'}</DialogTitle>
                </DialogHeader>

                <div className="flex flex-col gap-3">
                    <div className="flex flex-col gap-2">
                        <Label>Full name</Label>
                        <Input value={data.name} onChange={(e) => setData('name', e.target.value)} />
                        {errors.name && <p className="text-sm text-destructive">{errors.name}</p>}
                    </div>

                    <div className="flex flex-col gap-2">
                        <Label>Email</Label>
                        <Input type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} />
                        {errors.email && <p className="text-sm text-destructive">{errors.email}</p>}
                    </div>

                    <div className="flex flex-col gap-2">
                        <Label>Role</Label>
                        <Select value={data.role} onValueChange={chooseRole} disabled={user?.is_self}>
                            <SelectTrigger className="w-full">
                                <SelectValue placeholder="Select role" />
                            </SelectTrigger>
                            <SelectContent>
                                {roles.map((r) => (
                                    <SelectItem key={r} value={r}>
                                        {r}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {user?.is_self && <p className="text-xs text-muted-foreground">You cannot change your own role.</p>}
                        {errors.role && <p className="text-sm text-destructive">{errors.role}</p>}
                    </div>

                    <div className="flex flex-col gap-2">
                        <Label>Branch</Label>
                        <Select
                            value={data.branch_id || NONE}
                            onValueChange={(v) => setData('branch_id', v === NONE ? '' : v)}
                            disabled={rule.mode !== 'free'}
                        >
                            <SelectTrigger className="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={NONE}>All branches</SelectItem>
                                {branches.map((b) => (
                                    <SelectItem key={b.id} value={String(b.id)}>
                                        {b.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {rule.mode !== 'free' && data.role && (
                            <p className="text-xs text-muted-foreground">
                                {rule.mode === 'fixed' ? 'This role is tied to one branch.' : 'This role sees every branch.'}
                            </p>
                        )}
                        {errors.branch_id && <p className="text-sm text-destructive">{errors.branch_id}</p>}
                    </div>

                    <div className="flex flex-col gap-2">
                        <Label>{user ? 'New password (leave blank to keep the current one)' : 'Temporary password'}</Label>
                        <Input
                            type="text"
                            autoComplete="off"
                            value={data.password}
                            onChange={(e) => setData('password', e.target.value)}
                        />
                        {errors.password && <p className="text-sm text-destructive">{errors.password}</p>}
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button onClick={save} disabled={processing || !data.role}>
                        Save
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

export default function UsersPage({ users, roles, branches, roleRules, isSuperAdmin }: Props) {
    // undefined = closed, null = new user, an object = editing that user
    const [dialog, setDialog] = useState<UserRow | null | undefined>(undefined);

    function remove(u: UserRow) {
        if (confirm(`Delete ${u.name}? They will no longer be able to log in.`)) {
            router.delete(`/settings/users/${u.id}`, { preserveScroll: true });
        }
    }

    return (
        <>
            <Head title="Users" />

            <div className="flex flex-col gap-4 p-4">
                <SettingsHeader title="School settings" isSuperAdmin={isSuperAdmin} />

                <div className="flex items-center justify-between">
                    <h2 className="text-lg font-semibold">Users</h2>
                    <Button onClick={() => setDialog(null)}>New user</Button>
                </div>

                <div className="overflow-x-auto rounded-lg border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Name</TableHead>
                                <TableHead>Email</TableHead>
                                <TableHead>Role</TableHead>
                                <TableHead>Branch</TableHead>
                                <TableHead />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {users.map((u) => (
                                <TableRow key={u.id}>
                                    <TableCell className="font-medium">
                                        {u.name} {u.is_self && <span className="text-xs text-muted-foreground">(you)</span>}
                                    </TableCell>
                                    <TableCell>{u.email}</TableCell>
                                    <TableCell>{u.role ?? 'No role'}</TableCell>
                                    <TableCell>{u.branch ?? 'All branches'}</TableCell>
                                    <TableCell className="space-x-2 text-right whitespace-nowrap">
                                        <Button size="sm" variant="outline" onClick={() => setDialog(u)}>
                                            Edit
                                        </Button>
                                        {!u.is_self && (
                                            <Button size="sm" variant="outline" onClick={() => remove(u)}>
                                                Delete
                                            </Button>
                                        )}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>
            </div>

            {dialog !== undefined && (
                <UserDialog
                    user={dialog}
                    roles={roles}
                    branches={branches}
                    roleRules={roleRules}
                    onClose={() => setDialog(undefined)}
                />
            )}
        </>
    );
}
