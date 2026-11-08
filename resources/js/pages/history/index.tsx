import { Head } from '@inertiajs/react';
import { router } from '@inertiajs/react';
import { useMemo, useState } from 'react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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

type Row = {
    enrollment_id: number;
    full_name: string;
    admission_number: string;
    gender: 'male' | 'female';
    class: string;
    branch: string;
    status: 'inactive' | 'transferred';
    left_on: string | null;
    term: string;
    reason: string | null;
};

type Props = {
    years: { id: number; name: string }[];
    terms: { id: number; number: number }[];
    branches: { id: number; name: string }[];
    filters: { academic_year_id: number | null; term: string | number; status: string; branch_id: number | null };
    canPickBranch: boolean;
    summary: { total: number; boys: number; girls: number; inactive: number; transferred: number };
    rows: Row[];
};

export default function HistoryIndex({ years, terms, branches, filters, canPickBranch, summary, rows }: Props) {
    const [search, setSearch] = useState('');

    const visible = useMemo(() => {
        const q = search.trim().toLowerCase();
        if (!q) return rows;
        return rows.filter((r) =>
            [r.full_name, r.admission_number, r.class, r.reason ?? ''].join(' ').toLowerCase().includes(q),
        );
    }, [rows, search]);

    // Year, term, status and branch change the SQL, so they go back to Laravel.
    function apply(next: Partial<Record<'academic_year_id' | 'term' | 'status' | 'branch_id', string>>) {
        const merged = {
            academic_year_id: String(filters.academic_year_id ?? ''),
            term: String(filters.term),
            status: filters.status,
            branch_id: String(filters.branch_id ?? ''),
            ...next,
        };

        const params: Record<string, string> = {};
        if (merged.academic_year_id) params.academic_year_id = merged.academic_year_id;
        if (merged.term && merged.term !== 'all') params.term = merged.term;
        if (merged.status && merged.status !== 'all') params.status = merged.status;
        if (merged.branch_id) params.branch_id = merged.branch_id;

        // Changing the year changes which terms exist, so the term filter starts again.
        if (next.academic_year_id) delete params.term;

        router.get('/history', params, { preserveState: true, preserveScroll: true, replace: true });
    }

    function download(format: 'xlsx' | 'pdf') {
        const params = new URLSearchParams({ format });
        if (filters.academic_year_id) params.set('academic_year_id', String(filters.academic_year_id));
        if (filters.term !== 'all') params.set('term', String(filters.term));
        if (filters.status !== 'all') params.set('status', filters.status);
        if (filters.branch_id) params.set('branch_id', String(filters.branch_id));

        // A file download, so a normal browser navigation and not an Inertia visit.
        window.location.href = `/history/export?${params.toString()}`;
    }

    return (
        <>
            <Head title="Historical data" />

            <div className="flex flex-col gap-4 p-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold">Historical data</h1>
                        <p className="text-sm text-muted-foreground">Learners who left the school, and when and why.</p>
                    </div>
                    <div className="flex gap-2">
                        <Button variant="outline" onClick={() => download('xlsx')} disabled={rows.length === 0}>
                            Excel
                        </Button>
                        <Button variant="outline" onClick={() => download('pdf')} disabled={rows.length === 0}>
                            PDF
                        </Button>
                    </div>
                </div>

                <div className="flex flex-wrap items-center gap-3">
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

                    <Select value={String(filters.term)} onValueChange={(v) => apply({ term: v })}>
                        <SelectTrigger className="w-40">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All terms</SelectItem>
                            {terms.map((t) => (
                                <SelectItem key={t.id} value={String(t.id)}>
                                    Term {t.number}
                                </SelectItem>
                            ))}
                            <SelectItem value="none">Term not recorded</SelectItem>
                        </SelectContent>
                    </Select>

                    <Select value={filters.status} onValueChange={(v) => apply({ status: v })}>
                        <SelectTrigger className="w-40">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Inactive and transferred</SelectItem>
                            <SelectItem value="inactive">Inactive only</SelectItem>
                            <SelectItem value="transferred">Transferred only</SelectItem>
                        </SelectContent>
                    </Select>

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

                    <Input
                        className="w-full sm:w-64"
                        placeholder="Search name, admission, class or reason..."
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                    />
                </div>

                <div className="grid grid-cols-2 gap-3 sm:grid-cols-5">
                    {(
                        [
                            ['Learners who left', summary.total],
                            ['Boys', summary.boys],
                            ['Girls', summary.girls],
                            ['Inactive', summary.inactive],
                            ['Transferred', summary.transferred],
                        ] as const
                    ).map(([label, value]) => (
                        <div key={label} className="rounded-lg border p-3">
                            <div className="text-2xl font-semibold">{value}</div>
                            <div className="text-xs text-muted-foreground">{label}</div>
                        </div>
                    ))}
                </div>

                <div className="overflow-x-auto rounded-lg border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Name</TableHead>
                                <TableHead>Admission No.</TableHead>
                                <TableHead>Class</TableHead>
                                <TableHead>Branch</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Date left</TableHead>
                                <TableHead>Term</TableHead>
                                <TableHead>Reason</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {visible.map((r) => (
                                <TableRow key={r.enrollment_id}>
                                    <TableCell className="font-medium">{r.full_name}</TableCell>
                                    <TableCell>{r.admission_number}</TableCell>
                                    <TableCell>{r.class}</TableCell>
                                    <TableCell>{r.branch}</TableCell>
                                    <TableCell>
                                        <Badge variant={r.status === 'transferred' ? 'outline' : 'secondary'} className="capitalize">
                                            {r.status}
                                        </Badge>
                                    </TableCell>
                                    <TableCell>{r.left_on ?? '-'}</TableCell>
                                    <TableCell>{r.term}</TableCell>
                                    <TableCell className="max-w-xs whitespace-normal">{r.reason ?? '-'}</TableCell>
                                </TableRow>
                            ))}

                            {visible.length === 0 && (
                                <TableRow>
                                    <TableCell colSpan={8} className="py-8 text-center text-muted-foreground">
                                        No learners left in this period.
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                </div>
            </div>
        </>
    );
}

// Add the same breadcrumb pattern you used on the other pages.
