import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';

import SettingsHeader from '@/components/settings-header';
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

type Cell = { stream_id: number; class_id: number | null; learners: number; can_delete: boolean };
type GradeRow = { id: number; name: string; cells: Cell[] };
type BranchBlock = { id: number; name: string; first_grade_id: number; final_grade_id: number; grades: GradeRow[] };
type StreamRow = { id: number; name: string; classes: number; can_delete: boolean };

type Props = {
    branches: BranchBlock[];
    streams: { id: number; name: string }[];
    streamRows: StreamRow[];
    allGrades: { id: number; name: string }[];
    year: string | null;
    isSuperAdmin: boolean;
};

function StreamDialog({ stream, onClose }: { stream: StreamRow | null; onClose: () => void }) {
    const form = useForm({ name: stream?.name ?? '', create_classes: true });

    function save() {
        const options = { preserveScroll: true, onSuccess: onClose };
        if (stream) form.put(`/settings/streams/${stream.id}`, options);
        else form.post('/settings/streams', options);
    }

    return (
        <Dialog open onOpenChange={(o) => !o && onClose()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{stream ? 'Rename stream' : 'New stream'}</DialogTitle>
                    {!stream && (
                        <DialogDescription>
                            For example Yellow. Tick the box to also create a class of this stream in every grade of every branch.
                        </DialogDescription>
                    )}
                </DialogHeader>

                <div className="flex flex-col gap-2">
                    <Label>Stream name</Label>
                    <Input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                    {form.errors.name && <p className="text-sm text-destructive">{form.errors.name}</p>}
                </div>

                {!stream && (
                    <label className="flex items-center gap-2 text-sm">
                        <Checkbox
                            checked={form.data.create_classes}
                            onCheckedChange={(c) => form.setData('create_classes', c === true)}
                        />
                        Create classes for this stream in all branches
                    </label>
                )}

                <DialogFooter>
                    <Button variant="outline" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button onClick={save} disabled={form.processing || !form.data.name.trim()}>
                        Save
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

export default function ClassesSettings({ branches, streams, streamRows, allGrades, year, isSuperAdmin }: Props) {
    // undefined = closed, null = new stream, an object = renaming that stream
    const [streamDialog, setStreamDialog] = useState<StreamRow | null | undefined>(undefined);

    function addClass(branchId: number, gradeId: number, streamId: number) {
        router.post(
            '/settings/classes',
            { branch_id: branchId, grade_level_id: gradeId, stream_id: streamId },
            { preserveScroll: true },
        );
    }

    function removeClass(classId: number, label: string) {
        if (confirm(`Remove ${label}?`)) {
            router.delete(`/settings/classes/${classId}`, { preserveScroll: true });
        }
    }

    function changeFinalGrade(branchId: number, gradeId: string) {
        router.put(`/settings/branches/${branchId}/final-grade`, { grade_level_id: Number(gradeId) }, { preserveScroll: true });
    }

    function changeFirstGrade(branchId: number, gradeId: string) {
        router.put(`/settings/branches/${branchId}/first-grade`, { grade_level_id: Number(gradeId) }, { preserveScroll: true });
    }

    function fillClasses(branchId: number) {
        router.post(`/settings/branches/${branchId}/fill-classes`, {}, { preserveScroll: true });
    }

    function removeStream(s: StreamRow) {
        if (confirm(`Delete the stream ${s.name}?`)) {
            router.delete(`/settings/streams/${s.id}`, { preserveScroll: true });
        }
    }

    return (
        <>
            <Head title="Classes and streams" />

            <div className="flex flex-col gap-6 p-4">
                <SettingsHeader title="School settings" isSuperAdmin={isSuperAdmin} />

                {/* Streams */}
                <section className="flex flex-col gap-3">
                    <div className="flex items-center justify-between">
                        <h2 className="text-lg font-semibold">Streams</h2>
                        <Button onClick={() => setStreamDialog(null)}>New stream</Button>
                    </div>

                    <div className="overflow-x-auto rounded-lg border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Stream</TableHead>
                                    <TableHead className="text-right">Classes</TableHead>
                                    <TableHead />
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {streamRows.map((s) => (
                                    <TableRow key={s.id}>
                                        <TableCell className="font-medium">{s.name}</TableCell>
                                        <TableCell className="text-right">{s.classes}</TableCell>
                                        <TableCell className="space-x-2 text-right whitespace-nowrap">
                                            <Button size="sm" variant="outline" onClick={() => setStreamDialog(s)}>
                                                Rename
                                            </Button>
                                            {s.can_delete && (
                                                <Button size="sm" variant="outline" onClick={() => removeStream(s)}>
                                                    Delete
                                                </Button>
                                            )}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                </section>

                {/* Classes per branch */}
                {branches.map((b) => (
                    <section key={b.id} className="flex flex-col gap-3">
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <h2 className="text-lg font-semibold">{b.name}</h2>

                            <div className="flex flex-wrap items-center gap-2">
                                <Label className="text-sm">First grade</Label>
                                <Select value={String(b.first_grade_id)} onValueChange={(v) => changeFirstGrade(b.id, v)}>
                                    <SelectTrigger className="w-32">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {allGrades.map((g) => (
                                            <SelectItem key={g.id} value={String(g.id)}>
                                                {g.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <Button size="sm" variant="outline" onClick={() => fillClasses(b.id)}>
                                    Create missing classes
                                </Button>
                            </div>

                            <div className="flex items-center gap-2">
                                <Label className="text-sm">Final grade</Label>
                                <Select value={String(b.final_grade_id)} onValueChange={(v) => changeFinalGrade(b.id, v)}>
                                    <SelectTrigger className="w-36">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {allGrades.map((g) => (
                                            <SelectItem key={g.id} value={String(g.id)}>
                                                {g.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>

                        <div className="overflow-x-auto rounded-lg border">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Grade</TableHead>
                                        {streams.map((s) => (
                                            <TableHead key={s.id}>{s.name}</TableHead>
                                        ))}
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {b.grades.map((g) => (
                                        <TableRow key={g.id}>
                                            <TableCell className="font-medium">{g.name}</TableCell>
                                            {g.cells.map((c) => {
                                                const stream = streams.find((s) => s.id === c.stream_id)!;
                                                return (
                                                    <TableCell key={c.stream_id}>
                                                        {c.class_id ? (
                                                            <span className="flex items-center gap-2">
                                                                <span className="text-sm">{c.learners} learners</span>
                                                                {c.can_delete && (
                                                                    <Button
                                                                        size="sm"
                                                                        variant="ghost"
                                                                        onClick={() => removeClass(c.class_id!, `${g.name} ${stream.name}`)}
                                                                    >
                                                                        Remove
                                                                    </Button>
                                                                )}
                                                            </span>
                                                        ) : (
                                                            <Button
                                                                size="sm"
                                                                variant="outline"
                                                                onClick={() => addClass(b.id, g.id, c.stream_id)}
                                                            >
                                                                Add
                                                            </Button>
                                                        )}
                                                    </TableCell>
                                                );
                                            })}
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>
                    </section>
                ))}

                <p className="text-sm text-muted-foreground">
                    Learner numbers are active learners in {year ?? 'the current year'}. A class can only be removed if it has never had
                    learners. Changing a branch's final grade takes effect at the next year-end rollover.
                </p>
            </div>

            {streamDialog !== undefined && <StreamDialog stream={streamDialog} onClose={() => setStreamDialog(undefined)} />}
        </>
    );
}
