import { Head, Link, router } from '@inertiajs/react';

import { Button } from '@/components/ui/button';
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
    TableFooter,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';

type Option = { id: number; name: string };

type Props = {
    year: string | null;
    years: Option[];
    branches: Option[];
    grades: Option[];
    filters: { academic_year_id: number | null; branch_id: number | null; grade_level_id: number | null };
    canPickBranch: boolean;
    totals: { total: number; boys: number; girls: number; lunch: number; transport: number };
    statuses: { inactive: number; transferred: number; graduated: number };
    branchRows: { id: number; name: string; total: number; boys: number; girls: number; lunch: number; transport: number }[];
    streams: Option[];
    gradeRows: { id: number; name: string; streams: Record<string, number>; total: number }[];
    admissions: { total: number; pending: number; passed: number; failed: number; admitted: number } | null;
};

// A thin bar showing a value as a share of the largest value. No chart library needed.
function Bar({ value, max }: { value: number; max: number }) {
    const pct = max > 0 ? Math.round((value / max) * 100) : 0;
    return (
        <div className="h-2 w-full min-w-24 rounded bg-muted">
            <div className="h-2 rounded bg-primary/70" style={{ width: `${pct}%` }} />
        </div>
    );
}

export default function Dashboard({
    year,
    years,
    branches,
    grades,
    filters,
    canPickBranch,
    totals,
    statuses,
    branchRows,
    streams,
    gradeRows,
    admissions,
}: Props) {
    // Filters change the SQL, so each change asks Laravel for fresh numbers.
    function applyFilter(key: keyof Props['filters'], value: string) {
        const next = { ...filters, [key]: value === 'all' ? null : Number(value) };

        const params: Record<string, number> = {};
        if (next.academic_year_id) params.academic_year_id = next.academic_year_id;
        if (next.branch_id) params.branch_id = next.branch_id;
        if (next.grade_level_id) params.grade_level_id = next.grade_level_id;

        router.get('/dashboard', params, { preserveState: true, preserveScroll: true, replace: true });
    }

    const stats = [
        ['Active learners', totals.total],
        ['Boys', totals.boys],
        ['Girls', totals.girls],
        ['Take school lunch', totals.lunch],
        ['Use school transport', totals.transport],
    ] as const;

    const branchTotals = branchRows.reduce(
        (s, b) => ({
            total: s.total + b.total,
            boys: s.boys + b.boys,
            girls: s.girls + b.girls,
            lunch: s.lunch + b.lunch,
            transport: s.transport + b.transport,
        }),
        { total: 0, boys: 0, girls: 0, lunch: 0, transport: 0 },
    );
    const maxBranch = Math.max(0, ...branchRows.map((b) => b.total));
    const maxGrade = Math.max(0, ...gradeRows.map((g) => g.total));

    return (
        <>
            <Head title="Dashboard" />

            <div className="flex flex-col gap-6 p-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold">Dashboard</h1>
                        <p className="text-sm text-muted-foreground">Academic year {year ?? '-'}</p>
                    </div>

                    <div className="flex flex-wrap gap-3">
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
                    </div>
                </div>

                {/* Headline numbers */}
                <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
                    {stats.map(([label, value]) => (
                        <div key={label} className="rounded-lg border p-4">
                            <div className="text-3xl font-semibold">{value}</div>
                            <div className="text-sm text-muted-foreground">{label}</div>
                        </div>
                    ))}
                </div>

                <p className="text-sm text-muted-foreground">
                    Also in {year}: {statuses.inactive} inactive, {statuses.transferred} transferred and{' '}
                    {statuses.graduated} graduated.
                </p>

                {/* By branch */}
                <section className="flex flex-col gap-2">
                    <h2 className="text-lg font-semibold">Learners by branch</h2>
                    <div className="overflow-x-auto rounded-lg border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Branch</TableHead>
                                    <TableHead className="text-right">Learners</TableHead>
                                    <TableHead className="text-right">Boys</TableHead>
                                    <TableHead className="text-right">Girls</TableHead>
                                    <TableHead className="text-right">Lunch</TableHead>
                                    <TableHead className="text-right">Transport</TableHead>
                                    <TableHead className="w-40" />
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {branchRows.map((b) => (
                                    <TableRow key={b.id}>
                                        <TableCell className="font-medium">{b.name}</TableCell>
                                        <TableCell className="text-right">{b.total}</TableCell>
                                        <TableCell className="text-right">{b.boys}</TableCell>
                                        <TableCell className="text-right">{b.girls}</TableCell>
                                        <TableCell className="text-right">{b.lunch}</TableCell>
                                        <TableCell className="text-right">{b.transport}</TableCell>
                                        <TableCell>
                                            <Bar value={b.total} max={maxBranch} />
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                            {branchRows.length > 1 && (
                                <TableFooter>
                                    <TableRow>
                                        <TableCell>All branches</TableCell>
                                        <TableCell className="text-right">{branchTotals.total}</TableCell>
                                        <TableCell className="text-right">{branchTotals.boys}</TableCell>
                                        <TableCell className="text-right">{branchTotals.girls}</TableCell>
                                        <TableCell className="text-right">{branchTotals.lunch}</TableCell>
                                        <TableCell className="text-right">{branchTotals.transport}</TableCell>
                                        <TableCell />
                                    </TableRow>
                                </TableFooter>
                            )}
                        </Table>
                    </div>
                </section>

                {/* By grade and stream */}
                <section className="flex flex-col gap-2">
                    <h2 className="text-lg font-semibold">Learners by grade and stream</h2>
                    <div className="overflow-x-auto rounded-lg border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Grade</TableHead>
                                    {streams.map((s) => (
                                        <TableHead key={s.id} className="text-right">
                                            {s.name}
                                        </TableHead>
                                    ))}
                                    <TableHead className="text-right">Total</TableHead>
                                    <TableHead className="w-40" />
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {gradeRows.map((g) => (
                                    <TableRow key={g.id}>
                                        <TableCell className="font-medium">{g.name}</TableCell>
                                        {streams.map((s) => (
                                            <TableCell key={s.id} className="text-right">
                                                {g.streams[s.id] ?? 0}
                                            </TableCell>
                                        ))}
                                        <TableCell className="text-right font-medium">{g.total}</TableCell>
                                        <TableCell>
                                            <Bar value={g.total} max={maxGrade} />
                                        </TableCell>
                                    </TableRow>
                                ))}
                                {gradeRows.length === 0 && (
                                    <TableRow>
                                        <TableCell colSpan={streams.length + 3} className="py-8 text-center text-muted-foreground">
                                            No classes to show.
                                        </TableCell>
                                    </TableRow>
                                )}
                            </TableBody>
                        </Table>
                    </div>
                </section>

                {/* Admissions snapshot, only for users who can see admissions */}
                {admissions && (
                    <section className="flex flex-col gap-2">
                        <div className="flex items-center justify-between">
                            <h2 className="text-lg font-semibold">Admissions</h2>
                            <Button asChild variant="outline" size="sm">
                                <Link href="/admissions">View admissions</Link>
                            </Button>
                        </div>
                        <div className="grid grid-cols-2 gap-3 sm:grid-cols-5">
                            {(
                                [
                                    ['Applicants', admissions.total],
                                    ['Pending', admissions.pending],
                                    ['Passed, not admitted', admissions.passed],
                                    ['Failed', admissions.failed],
                                    ['Admitted', admissions.admitted],
                                ] as const
                            ).map(([label, value]) => (
                                <div key={label} className="rounded-lg border p-3">
                                    <div className="text-2xl font-semibold">{value}</div>
                                    <div className="text-xs text-muted-foreground">{label}</div>
                                </div>
                            ))}
                        </div>
                    </section>
                )}
            </div>
        </>
    );
}

// Keep the breadcrumb pattern your starter kit already uses for the dashboard page, for example:
// Dashboard.layout = { breadcrumbs: [{ title: 'Dashboard', href: '/dashboard' }] };
