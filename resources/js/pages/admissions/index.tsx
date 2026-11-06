import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';

import ColumnsExportDialog from '@/components/columns-export-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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

type Row = {
    id: number;
    full_name: string;
    branch: string;
    branch_id: number;
    grade: string;
    grade_level_id: number;
    assessment_number: string | null;
    date_of_birth: string | null;
    parent_name: string;
    parent_type: string;
    parent_phone: string;
    mpesa_code: string | null;
    result: 'pending' | 'passed' | 'failed';
    notes: string | null;
    admitted: boolean;
};

type Option = { id: number; name: string };

type Props = {
    applicants: Row[];
    summary: { total: number; pending: number; passed: number; failed: number; admitted: number };
    years: Option[];
    branches: Option[];
    grades: Option[];
    filters: { academic_year_id: number | null; branch_id: number | null; grade_level_id: number | null; result: string };
    classOptions: Record<string, { id: number; label: string }[]>;
    exportColumns: { key: string; label: string }[];
    can: { manage: boolean; promote: boolean; export: boolean };
    canPickBranch: boolean;
};

/* ---------------- Promote dialog ---------------- */
function PromoteDialog({
    applicant,
    classes,
    onClose,
}: {
    applicant: Row;
    classes: { id: number; label: string }[];
    onClose: () => void;
}) {
    const form = useForm({
        class_id: classes.length === 1 ? String(classes[0].id) : '',
        admission_number: '',
        nationality: 'citizen',
        kemis_code: '',
        takes_lunch: false,
    });
    const { data, setData, errors, processing } = form;

    return (
        <Dialog open onOpenChange={(o) => !o && onClose()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Admit {applicant.full_name}</DialogTitle>
                    <DialogDescription>
                        {applicant.grade}, {applicant.branch}. This creates the learner and places them in a class.
                    </DialogDescription>
                </DialogHeader>

                <div className="flex flex-col gap-3">
                    <div className="flex flex-col gap-2">
                        <Label>Class</Label>
                        <Select value={data.class_id} onValueChange={(v) => setData('class_id', v)}>
                            <SelectTrigger className="w-full">
                                <SelectValue placeholder="Select class" />
                            </SelectTrigger>
                            <SelectContent>
                                {classes.map((c) => (
                                    <SelectItem key={c.id} value={String(c.id)}>
                                        {c.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {errors.class_id && <p className="text-sm text-destructive">{errors.class_id}</p>}
                    </div>

                    <div className="flex flex-col gap-2">
                        <Label>Admission number</Label>
                        <Input value={data.admission_number} onChange={(e) => setData('admission_number', e.target.value)} />
                        {errors.admission_number && <p className="text-sm text-destructive">{errors.admission_number}</p>}
                    </div>

                    <div className="grid gap-3 sm:grid-cols-2">
                        <div className="flex flex-col gap-2">
                            <Label>Citizenship</Label>
                            <Select value={data.nationality} onValueChange={(v) => setData('nationality', v)}>
                                <SelectTrigger className="w-full">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="citizen">Citizen</SelectItem>
                                    <SelectItem value="non_citizen">Non citizen</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="flex flex-col gap-2">
                            <Label>School lunch</Label>
                            <Select
                                value={data.takes_lunch ? 'yes' : 'no'}
                                onValueChange={(v) => setData('takes_lunch', v === 'yes')}
                            >
                                <SelectTrigger className="w-full">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="yes">Yes</SelectItem>
                                    <SelectItem value="no">No</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>

                    <div className="flex flex-col gap-2">
                        <Label>Kemis code (optional)</Label>
                        <Input value={data.kemis_code} onChange={(e) => setData('kemis_code', e.target.value)} />
                        {errors.kemis_code && <p className="text-sm text-destructive">{errors.kemis_code}</p>}
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button
                        disabled={processing || !data.class_id || !data.admission_number}
                        onClick={() => form.post(`/admissions/${applicant.id}/promote`, { preserveScroll: true, onSuccess: onClose })}
                    >
                        {processing ? 'Admitting...' : 'Admit learner'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

/* ---------------- Page ---------------- */
export default function AdmissionsIndex({
    applicants,
    summary,
    years,
    branches,
    grades,
    filters,
    classOptions,
    exportColumns,
    can,
    canPickBranch,
}: Props) {
    const { flash } = usePage<{ flash?: { success?: string } }>().props;

    const [search, setSearch] = useState('');
    const [promoting, setPromoting] = useState<Row | null>(null);
    const [exportOpen, setExportOpen] = useState(false);

    const visible = useMemo(() => {
        const term = search.trim().toLowerCase();
        if (!term) return applicants;
        return applicants.filter((a) =>
            [a.full_name, a.parent_name, a.parent_phone, a.mpesa_code ?? '', a.assessment_number ?? '']
                .join(' ')
                .toLowerCase()
                .includes(term),
        );
    }, [applicants, search]);

    // Year, branch, grade and result change the SQL, so they go back to Laravel.
    function applyFilter(key: keyof Props['filters'], value: string) {
        const next = { ...filters, [key]: value === 'all' ? null : key === 'result' ? value : Number(value) };

        const params: Record<string, string | number> = {};
        if (next.academic_year_id) params.academic_year_id = next.academic_year_id;
        if (next.branch_id) params.branch_id = next.branch_id;
        if (next.grade_level_id) params.grade_level_id = next.grade_level_id;
        if (next.result && next.result !== 'all') params.result = next.result as string;

        router.get('/admissions', params, { preserveState: true, preserveScroll: true, replace: true });
    }

    function remove(a: Row) {
        if (confirm(`Remove ${a.full_name} from admissions?`)) {
            router.delete(`/admissions/${a.id}`, { preserveScroll: true });
        }
    }

    // The same filters are sent to the export so the file matches the page.
    const exportParams: Record<string, string> = { result: filters.result };
    if (filters.academic_year_id) exportParams.academic_year_id = String(filters.academic_year_id);
    if (filters.branch_id) exportParams.branch_id = String(filters.branch_id);
    if (filters.grade_level_id) exportParams.grade_level_id = String(filters.grade_level_id);

    const stats = [
        ['Applicants', summary.total],
        ['Pending', summary.pending],
        ['Passed, not admitted', summary.passed],
        ['Failed', summary.failed],
        ['Admitted', summary.admitted],
    ] as const;

    return (
        <>
            <Head title="Admissions" />

            <div className="flex flex-col gap-4 p-4">
                {flash?.success && (
                    <div className="rounded-md border border-green-600/30 bg-green-50 p-3 text-sm text-green-800 dark:bg-green-950 dark:text-green-200">
                        {flash.success}
                    </div>
                )}

                <div className="flex flex-wrap items-center justify-between gap-3">
                    <h1 className="text-xl font-semibold">Admissions</h1>
                    <div className="flex gap-2">
                        {can.export && (
                            <Button variant="outline" onClick={() => setExportOpen(true)}>
                                Download
                            </Button>
                        )}
                        {can.manage && (
                            <Button asChild>
                                <Link href="/admissions/create">Add applicant</Link>
                            </Button>
                        )}
                    </div>
                </div>

                <div className="grid grid-cols-2 gap-3 sm:grid-cols-5">
                    {stats.map(([label, value]) => (
                        <div key={label} className="rounded-lg border p-3">
                            <div className="text-2xl font-semibold">{value}</div>
                            <div className="text-xs text-muted-foreground">{label}</div>
                        </div>
                    ))}
                </div>

                <div className="flex flex-wrap items-center gap-3">
                    <Input
                        className="w-full sm:w-72"
                        placeholder="Search name, parent, phone or M-Pesa code..."
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                    />

                    <Select value={String(filters.academic_year_id ?? '')} onValueChange={(v) => applyFilter('academic_year_id', v)}>
                        <SelectTrigger className="w-32">
                            <SelectValue placeholder="Year" />
                        </SelectTrigger>
                        <SelectContent>
                            {years.map((y) => (
                                <SelectItem key={y.id} value={String(y.id)}>
                                    {y.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    {canPickBranch && (
                        <Select value={String(filters.branch_id ?? 'all')} onValueChange={(v) => applyFilter('branch_id', v)}>
                            <SelectTrigger className="w-40">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All branches</SelectItem>
                                {branches.map((b) => (
                                    <SelectItem key={b.id} value={String(b.id)}>
                                        {b.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    )}

                    <Select value={String(filters.grade_level_id ?? 'all')} onValueChange={(v) => applyFilter('grade_level_id', v)}>
                        <SelectTrigger className="w-36">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All grades</SelectItem>
                            {grades.map((g) => (
                                <SelectItem key={g.id} value={String(g.id)}>
                                    {g.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    <Select value={filters.result} onValueChange={(v) => applyFilter('result', v)}>
                        <SelectTrigger className="w-52">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All results</SelectItem>
                            <SelectItem value="pending">Pending</SelectItem>
                            <SelectItem value="passed">Passed, not admitted</SelectItem>
                            <SelectItem value="failed">Failed</SelectItem>
                            <SelectItem value="admitted">Admitted</SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                {/* The table scrolls sideways on small screens instead of the whole page. */}
                <div className="overflow-x-auto rounded-lg border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Name</TableHead>
                                <TableHead>Grade</TableHead>
                                <TableHead>Branch</TableHead>
                                <TableHead>Assessment No.</TableHead>
                                <TableHead>Date of birth</TableHead>
                                <TableHead>Parent / guardian</TableHead>
                                <TableHead>Phone</TableHead>
                                <TableHead>M-Pesa code</TableHead>
                                <TableHead>Result</TableHead>
                                <TableHead>Notes</TableHead>
                                <TableHead />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {visible.map((a) => (
                                <TableRow key={a.id}>
                                    <TableCell className="font-medium">{a.full_name}</TableCell>
                                    <TableCell>{a.grade}</TableCell>
                                    <TableCell>{a.branch}</TableCell>
                                    <TableCell>{a.assessment_number ?? '-'}</TableCell>
                                    <TableCell>{a.date_of_birth ?? '-'}</TableCell>
                                    <TableCell>
                                        {a.parent_name}
                                        <div className="text-xs text-muted-foreground capitalize">{a.parent_type}</div>
                                    </TableCell>
                                    <TableCell>{a.parent_phone}</TableCell>
                                    <TableCell>{a.mpesa_code ?? '-'}</TableCell>
                                    <TableCell>
                                        {a.admitted ? (
                                            <Badge variant="secondary">Admitted</Badge>
                                        ) : a.result === 'passed' ? (
                                            <Badge>Passed</Badge>
                                        ) : a.result === 'failed' ? (
                                            <Badge variant="destructive">Failed</Badge>
                                        ) : (
                                            <Badge variant="outline">Pending</Badge>
                                        )}
                                    </TableCell>
                                    <TableCell className="max-w-[200px] truncate" title={a.notes ?? ''}>
                                        {a.notes ?? '-'}
                                    </TableCell>
                                    <TableCell className="space-x-2 text-right whitespace-nowrap">
                                        {can.promote && a.result === 'passed' && !a.admitted && (
                                            <Button size="sm" onClick={() => setPromoting(a)}>
                                                Admit
                                            </Button>
                                        )}
                                        {can.manage && !a.admitted && (
                                            <>
                                                <Button asChild size="sm" variant="outline">
                                                    <Link href={`/admissions/${a.id}/edit`}>Edit</Link>
                                                </Button>
                                                <Button size="sm" variant="outline" onClick={() => remove(a)}>
                                                    Delete
                                                </Button>
                                            </>
                                        )}
                                    </TableCell>
                                </TableRow>
                            ))}

                            {visible.length === 0 && (
                                <TableRow>
                                    <TableCell colSpan={11} className="py-8 text-center text-muted-foreground">
                                        No applicants found.
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                </div>
            </div>

            {promoting && (
                <PromoteDialog
                    applicant={promoting}
                    classes={classOptions[`${promoting.branch_id}-${promoting.grade_level_id}`] ?? []}
                    onClose={() => setPromoting(null)}
                />
            )}

            <ColumnsExportDialog
                open={exportOpen}
                onOpenChange={setExportOpen}
                title="Download admissions report"
                url="/admissions/export"
                columns={exportColumns}
                defaultColumns={['row_number', 'full_name', 'grade', 'assessment_number', 'parent_name', 'parent_phone', 'mpesa_code', 'result']}
                extraParams={exportParams}
            />
        </>
    );
}

// Add the same breadcrumb pattern you used on the other pages.
