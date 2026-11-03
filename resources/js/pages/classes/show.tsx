import { Head, Link, router, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';

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
import ClassExportDialog from '@/components/class-export-dialog';
import ClassImportDialog from '@/components/class-import-dialog';
import { Input } from '@/components/ui/input';
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

type LearnerRow = {
    enrollment_id: number;
    learner_id: number;
    assessment_number: string | null;
    full_name: string;
    admission_number: string;
    gender: 'male' | 'female';
    nationality: 'citizen' | 'non_citizen';
    kemis_code: string | null;
    lunch: boolean;
    status: 'active' | 'inactive' | 'transferred' | 'graduated';
};

type Props = {
    classRoom: { id: number; name: string; branch: string };
    learners: LearnerRow[];
    moveTargets: { id: number; label: string }[];
    academicYears: { id: number; name: string }[];
    filters: { academic_year_id: number | null };
    isCurrentYear: boolean;
    exportColumns: { key: string; label: string }[];
};

const statusStyle: Record<LearnerRow['status'], 'default' | 'secondary' | 'outline'> = {
    active: 'default',
    inactive: 'secondary',
    transferred: 'outline',
    graduated: 'secondary',
};

export default function ClassShow({
    classRoom,
    learners,
    moveTargets,
    academicYears,
    filters,
    isCurrentYear,
    exportColumns,
}: Props) {
    const [search, setSearch] = useState('');
    const [exportOpen, setExportOpen] = useState(false);
    const [importOpen, setImportOpen] = useState(false);
    const { flash } = usePage<{ flash?: { success?: string } }>().props;
    const [status, setStatus] = useState('all');

    // Selected rows are stored as a list of enrollment ids.
    const [selected, setSelected] = useState<number[]>([]);

    const [moveOpen, setMoveOpen] = useState(false);
    const [targetClassId, setTargetClassId] = useState('');
    const [moving, setMoving] = useState(false);
    const [moveError, setMoveError] = useState<string | null>(null);

    const visible = useMemo(() => {
        const term = search.trim().toLowerCase();
        return learners.filter((l) => {
            if (status !== 'all' && l.status !== status) return false;
            if (!term) return true;
            return (
                l.full_name.toLowerCase().includes(term) ||
                l.admission_number.toLowerCase().includes(term) ||
                (l.assessment_number ?? '').toLowerCase().includes(term) ||
                (l.kemis_code ?? '').toLowerCase().includes(term)
            );
        });
    }, [learners, search, status]);

    const visibleIds = visible.map((l) => l.enrollment_id);
    const allSelected =
        visibleIds.length > 0 && visibleIds.every((id) => selected.includes(id));

    function toggleOne(id: number, checked: boolean) {
        setSelected((prev) =>
            checked ? [...prev, id] : prev.filter((x) => x !== id),
        );
    }

    function toggleAll(checked: boolean) {
        setSelected(checked ? visibleIds : []);
    }

    function changeYear(value: string) {
        router.get(
            `/classes/${classRoom.id}`,
            { academic_year_id: value },
            { replace: true },
        );
    }

    function confirmMove() {
        if (!targetClassId) return;
        setMoving(true);
        setMoveError(null);

        router.post(
            `/classes/${classRoom.id}/move`,
            { enrollment_ids: selected, target_class_id: Number(targetClassId) },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setSelected([]);
                    setTargetClassId('');
                    setMoveOpen(false);
                },
                onError: (errors) => {
                    setMoveError(Object.values(errors)[0] ?? 'Could not move learners.');
                },
                onFinish: () => setMoving(false),
            },
        );
    }

    return (
        <>
            <Head title={classRoom.name} />

            <div className="flex flex-col gap-4 p-4">
                {flash?.success && (
                    <div className="rounded-md border border-green-600/30 bg-green-50 p-3 text-sm text-green-800 dark:bg-green-950 dark:text-green-200">
                        {flash.success}
                    </div>
                )}

                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold">{classRoom.name}</h1>
                        <p className="text-sm text-muted-foreground">
                            {classRoom.branch} · {learners.length} learners
                        </p>
                    </div>

                    <Button asChild variant="outline" size="sm">
                        <Link href={`/classes?academic_year_id=${filters.academic_year_id}`}>
                            Back to classes
                        </Link>
                    </Button>
                </div>

                <div className="flex flex-wrap items-center gap-3">
                    <Input
                        className="w-full sm:w-72"
                        placeholder="Search name, admission, assessment or Kemis no..."
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                    />

                    <Select value={status} onValueChange={setStatus}>
                        <SelectTrigger className="w-40">
                            <SelectValue placeholder="Status" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All statuses</SelectItem>
                            <SelectItem value="active">Active</SelectItem>
                            <SelectItem value="inactive">Inactive</SelectItem>
                            <SelectItem value="transferred">Transferred</SelectItem>
                            <SelectItem value="graduated">Graduated</SelectItem>
                        </SelectContent>
                    </Select>

                    <Select
                        value={String(filters.academic_year_id ?? '')}
                        onValueChange={changeYear}
                    >
                        <SelectTrigger className="w-36">
                            <SelectValue placeholder="Year" />
                        </SelectTrigger>
                        <SelectContent>
                            {academicYears.map((y) => (
                                <SelectItem key={y.id} value={String(y.id)}>
                                    {y.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    <Button variant="outline" onClick={() => setExportOpen(true)}>
                        Download
                    </Button>

                    {isCurrentYear && (
                        <Button
                            disabled={selected.length === 0}
                            onClick={() => setMoveOpen(true)}
                        >
                            Move selected ({selected.length})
                        </Button>
                    )}

                    {isCurrentYear && (
                        <Button asChild variant="outline">
                            <Link href={`/classes/${classRoom.id}/learners/create`}>Add learner</Link>
                        </Button>
                    )}

                    {isCurrentYear && (
                        <Button variant="outline" onClick={() => setImportOpen(true)}>
                            Import
                        </Button>
                    )}
                </div>

                <div className="rounded-lg border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                {isCurrentYear && (
                                    <TableHead className="w-10">
                                        <Checkbox
                                            checked={allSelected}
                                            onCheckedChange={(c) => toggleAll(c === true)}
                                            aria-label="Select all"
                                        />
                                    </TableHead>
                                )}
                                <TableHead>Assessment No.</TableHead>
                                <TableHead>Name</TableHead>
                                <TableHead>Admission No.</TableHead>
                                <TableHead>Citizenship</TableHead>
                                <TableHead>Gender</TableHead>
                                <TableHead>Kemis No.</TableHead>
                                <TableHead>Lunch</TableHead>
                                <TableHead>Status</TableHead>
                                {isCurrentYear && <TableHead className="w-20" />}
                            </TableRow>
                        </TableHeader>

                        <TableBody>
                            {visible.map((l) => (
                                <TableRow key={l.enrollment_id}>
                                    {isCurrentYear && (
                                        <TableCell>
                                            <Checkbox
                                                checked={selected.includes(l.enrollment_id)}
                                                onCheckedChange={(c) =>
                                                    toggleOne(l.enrollment_id, c === true)
                                                }
                                                aria-label={`Select ${l.full_name}`}
                                            />
                                        </TableCell>
                                    )}
                                    <TableCell>{l.assessment_number ?? '-'}</TableCell>
                                    <TableCell className="font-medium">{l.full_name}</TableCell>
                                    <TableCell>{l.admission_number}</TableCell>
                                    <TableCell>{l.nationality === 'citizen' ? 'Citizen' : 'Non citizen'}</TableCell>
                                    <TableCell className="capitalize">{l.gender}</TableCell>
                                    <TableCell>{l.kemis_code ?? '-'}</TableCell>
                                    <TableCell>{l.lunch ? 'Yes' : 'No'}</TableCell>
                                    <TableCell>
                                        <Badge variant={statusStyle[l.status]} className="capitalize">
                                            {l.status}
                                        </Badge>
                                    </TableCell>
                                    {isCurrentYear && (
                                        <TableCell className="text-right">
                                            <Button asChild size="sm" variant="outline">
                                                <Link href={`/enrollments/${l.enrollment_id}/edit`}>Edit</Link>
                                            </Button>
                                        </TableCell>
                                    )}
                                </TableRow>
                            ))}

                            {visible.length === 0 && (
                                <TableRow>
                                    <TableCell
                                        colSpan={10}
                                        className="py-8 text-center text-muted-foreground"
                                    >
                                        No learners found.
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                </div>
            </div>

            {importOpen && (
                <ClassImportDialog
                    classId={classRoom.id}
                    className={classRoom.name}
                    onClose={() => setImportOpen(false)}
                />
            )}

            <ClassExportDialog
                open={exportOpen}
                onOpenChange={setExportOpen}
                classId={classRoom.id}
                academicYearId={filters.academic_year_id}
                columns={exportColumns}
            />

            <Dialog open={moveOpen} onOpenChange={setMoveOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Move {selected.length} learner(s)</DialogTitle>
                        <DialogDescription>
                            Choose the class to move the selected learners into.
                        </DialogDescription>
                    </DialogHeader>

                    <Select value={targetClassId} onValueChange={setTargetClassId}>
                        <SelectTrigger>
                            <SelectValue placeholder="Select target class" />
                        </SelectTrigger>
                        <SelectContent>
                            {moveTargets.map((c) => (
                                <SelectItem key={c.id} value={String(c.id)}>
                                    {c.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    {moveError && <p className="text-sm text-destructive">{moveError}</p>}

                    <DialogFooter>
                        <Button variant="outline" onClick={() => setMoveOpen(false)}>
                            Cancel
                        </Button>
                        <Button onClick={confirmMove} disabled={!targetClassId || moving}>
                            {moving ? 'Moving...' : 'Move learners'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

// Use the same breadcrumb pattern you used at the bottom of classes/index.tsx.
// Example, if your index file uses the .layout form:
//
// ClassShow.layout = {
//     breadcrumbs: [
//         { title: 'Classes', href: '/classes' },
//         { title: 'Class learners', href: '#' },
//     ],
// };
