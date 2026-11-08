import { Head, router } from '@inertiajs/react';

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
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';

type Totals = { boys: number; girls: number; total: number; left_boys: number; left_girls: number; left_total: number };
type Section = { name: string; rows: ({ class: string } & Totals)[]; totals: Totals };
type BranchReport = { id: number; name: string; sections: Section[]; totals: Totals };

type Props = {
    years: { id: number; name: string }[];
    terms: { id: number; number: number }[];
    branches: { id: number; name: string }[];
    canPickBranch: boolean;
    filters: { period: string; academic_year_id: number | null; term_id: number | null; month: number; branch_id: number | null };
    report: { period_label: string; branches: BranchReport[]; grand: Totals; show_grand: boolean } | null;
    error: string | null;
};

const MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

// Two header rows: the group (on roll / left) and then Boys, Girls, Total.
function Head2() {
    return (
        <TableHeader>
            <TableRow>
                <TableHead rowSpan={2}>Class</TableHead>
                <TableHead colSpan={3} className="text-center">
                    Learners Enrolled
                </TableHead>
                <TableHead colSpan={3} className="text-center">
                    Learners who left
                </TableHead>
            </TableRow>
            <TableRow>
                {['Boys', 'Girls', 'Total', 'Boys', 'Girls', 'Total'].map((h, i) => (
                    <TableHead key={i} className="text-right">
                        {h}
                    </TableHead>
                ))}
            </TableRow>
        </TableHeader>
    );
}

function Cells({ t }: { t: Totals }) {
    return (
        <>
            <TableCell className="text-right">{t.boys}</TableCell>
            <TableCell className="text-right">{t.girls}</TableCell>
            <TableCell className="text-right">{t.total}</TableCell>
            <TableCell className="text-right">{t.left_boys}</TableCell>
            <TableCell className="text-right">{t.left_girls}</TableCell>
            <TableCell className="text-right">{t.left_total}</TableCell>
        </>
    );
}

export default function ReportsIndex({ years, terms, branches, canPickBranch, filters, report, error }: Props) {
    // Every filter change asks Laravel for a fresh preview.
    function apply(next: Record<string, string>) {
        const merged: Record<string, string> = {
            period: filters.period,
            academic_year_id: String(filters.academic_year_id ?? ''),
            term_id: String(filters.term_id ?? ''),
            month: String(filters.month),
            branch_id: String(filters.branch_id ?? ''),
            ...next,
        };

        // A different year has different terms, so the term starts again.
        if (next.academic_year_id) merged.term_id = '';

        const params: Record<string, string> = {};
        Object.entries(merged).forEach(([k, v]) => v && (params[k] = v));

        router.get('/reports', params, { preserveState: true, preserveScroll: true, replace: true });
    }

    function download() {
        const params = new URLSearchParams({
            period: filters.period,
            month: String(filters.month),
        });
        if (filters.academic_year_id) params.set('academic_year_id', String(filters.academic_year_id));
        if (filters.term_id) params.set('term_id', String(filters.term_id));
        if (filters.branch_id) params.set('branch_id', String(filters.branch_id));

        // A file download, so a normal browser navigation and not an Inertia visit.
        window.location.href = `/reports/learners/pdf?${params.toString()}`;
    }

    return (
        <>
            <Head title="Reports" />

            <div className="flex flex-col gap-4 p-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold">Reports</h1>
                        <p className="text-sm text-muted-foreground">
                            Learners Enrolled and learners who left, by class and section.
                        </p>
                    </div>
                    <Button onClick={download} disabled={!report}>
                        Download PDF
                    </Button>
                </div>

                <div className="flex flex-wrap items-center gap-3">
                    <Select value={filters.period} onValueChange={(v) => apply({ period: v })}>
                        <SelectTrigger className="w-36">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="monthly">Monthly</SelectItem>
                            <SelectItem value="termly">Termly</SelectItem>
                            <SelectItem value="yearly">Yearly</SelectItem>
                        </SelectContent>
                    </Select>

                    <Select value={String(filters.academic_year_id ?? '')} onValueChange={(v) => apply({ academic_year_id: v })}>
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

                    {filters.period === 'termly' && (
                        <Select value={String(filters.term_id ?? '')} onValueChange={(v) => apply({ term_id: v })}>
                            <SelectTrigger className="w-32">
                                <SelectValue placeholder="Term" />
                            </SelectTrigger>
                            <SelectContent>
                                {terms.map((t) => (
                                    <SelectItem key={t.id} value={String(t.id)}>
                                        Term {t.number}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    )}

                    {filters.period === 'monthly' && (
                        <Select value={String(filters.month)} onValueChange={(v) => apply({ month: v })}>
                            <SelectTrigger className="w-40">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {MONTHS.map((m, i) => (
                                    <SelectItem key={m} value={String(i + 1)}>
                                        {m}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    )}

                    {canPickBranch && (
                        <Select value={String(filters.branch_id ?? 'all')} onValueChange={(v) => apply({ branch_id: v === 'all' ? '' : v })}>
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
                </div>

                {error && <div className="rounded-md border border-destructive/40 p-3 text-sm text-destructive">{error}</div>}

                {report && (
                    <>
                        <h2 className="text-lg font-semibold">{report.period_label}</h2>

                        {report.branches.map((b) => (
                            <section key={b.id} className="flex flex-col gap-3">
                                <h3 className="border-b pb-1 text-base font-semibold">{b.name}</h3>

                                {b.sections.length === 0 && (
                                    <p className="text-sm text-muted-foreground">No learners to report for this period.</p>
                                )}

                                {b.sections.map((s) => (
                                    <div key={s.name} className="flex flex-col gap-1">
                                        <div className="text-sm font-medium">{s.name}</div>
                                        <div className="overflow-x-auto rounded-lg border">
                                            <Table>
                                                <Head2 />
                                                <TableBody>
                                                    {s.rows.map((r) => (
                                                        <TableRow key={r.class}>
                                                            <TableCell className="font-medium">{r.class}</TableCell>
                                                            <Cells t={r} />
                                                        </TableRow>
                                                    ))}
                                                    <TableRow className="bg-muted/50 font-semibold">
                                                        <TableCell>Total {s.name}</TableCell>
                                                        <Cells t={s.totals} />
                                                    </TableRow>
                                                </TableBody>
                                            </Table>
                                        </div>
                                    </div>
                                ))}

                                {b.sections.length > 0 && (
                                    <div className="overflow-x-auto rounded-lg border">
                                        <Table>
                                            <TableBody>
                                                <TableRow className="bg-muted font-semibold">
                                                    <TableCell>Total {b.name}</TableCell>
                                                    <Cells t={b.totals} />
                                                </TableRow>
                                            </TableBody>
                                        </Table>
                                    </div>
                                )}
                            </section>
                        ))}

                        {report.show_grand && (
                            <div className="overflow-x-auto rounded-lg border">
                                <Table>
                                    <TableBody>
                                        <TableRow className="bg-muted font-semibold">
                                            <TableCell>School total (all branches)</TableCell>
                                            <Cells t={report.grand} />
                                        </TableRow>
                                    </TableBody>
                                </Table>
                            </div>
                        )}

                        {/* <p className="text-xs text-muted-foreground">
                            On roll = learners in the class at the end of the period. Left = learners made inactive or
                            transferred during the period. Classes with no learners and no leavers are not listed.
                        </p> */}
                    </>
                )}
            </div>
        </>
    );
}

// Add the same breadcrumb pattern you used on the other pages.
