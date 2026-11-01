import { Head, Link, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';

import AppLayout from '@/layouts/app-layout';
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
    TableFooter,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';

// 1. Types describe the shape of the props the controller sends.
type ClassRow = {
    id: number;
    name: string;
    branch: string;
    total: number;
    boys: number;
    girls: number;
};

type Option = { id: number; name: string };

type Props = {
    classes: ClassRow[];
    branches: Option[];
    academicYears: Option[];
    filters: { academic_year_id: number | null; branch_id: number | null };
    canPickBranch: boolean;
};

// 2. A component is just a function that receives props and returns markup.
export default function ClassesIndex({
    classes,
    branches,
    academicYears,
    filters,
    canPickBranch,
}: Props) {
    // 3. useState = a variable that re-renders the page when it changes
    //    (the closest thing to a public property in Livewire).
    const [search, setSearch] = useState('');

    // 4. Filtering by search happens in the browser, so it is instant.
    const visible = useMemo(() => {
        const term = search.trim().toLowerCase();
        if (!term) return classes;
        return classes.filter(
            (c) =>
                c.name.toLowerCase().includes(term) ||
                c.branch.toLowerCase().includes(term),
        );
    }, [classes, search]);

    const totals = useMemo(
        () =>
            visible.reduce(
                (sum, c) => ({
                    total: sum.total + c.total,
                    boys: sum.boys + c.boys,
                    girls: sum.girls + c.girls,
                }),
                { total: 0, boys: 0, girls: 0 },
            ),
        [visible],
    );

    // 5. Year and branch filters go to the server (they change the SQL),
    //    so we ask Laravel for a new page via Inertia's router.
    function applyFilter(key: 'academic_year_id' | 'branch_id', value: string) {
        const params: Record<string, number> = {};
        const next = { ...filters, [key]: value === 'all' ? null : Number(value) };

        if (next.academic_year_id) params.academic_year_id = next.academic_year_id;
        if (next.branch_id) params.branch_id = next.branch_id;

        router.get('/classes', params, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    }

    return (
        <>
            <Head title="Classes" />

            <div className="flex flex-col gap-4 p-4">
                <div className="flex flex-wrap items-center gap-3">
                    <Input
                        className="w-full sm:w-64"
                        placeholder="Search class or branch..."
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                    />

                    <Select
                        value={String(filters.academic_year_id ?? '')}
                        onValueChange={(v) => applyFilter('academic_year_id', v)}
                    >
                        <SelectTrigger className="w-40">
                            <SelectValue placeholder="Academic year" />
                        </SelectTrigger>
                        <SelectContent>
                            {academicYears.map((y) => (
                                <SelectItem key={y.id} value={String(y.id)}>
                                    {y.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    {canPickBranch && (
                        <Select
                            value={String(filters.branch_id ?? 'all')}
                            onValueChange={(v) => applyFilter('branch_id', v)}
                        >
                            <SelectTrigger className="w-44">
                                <SelectValue placeholder="Branch" />
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

                <div className="rounded-lg border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Class</TableHead>
                                <TableHead>Branch</TableHead>
                                <TableHead className="text-right">Learners</TableHead>
                                <TableHead className="text-right">Boys</TableHead>
                                <TableHead className="text-right">Girls</TableHead>
                                <TableHead className="w-24" />
                            </TableRow>
                        </TableHeader>

                        <TableBody>
                            {/* 6. .map() replaces @foreach. `key` helps React track rows. */}
                            {visible.map((c) => (
                                <TableRow key={c.id}>
                                    <TableCell className="font-medium">{c.name}</TableCell>
                                    <TableCell>{c.branch}</TableCell>
                                    <TableCell className="text-right">{c.total}</TableCell>
                                    <TableCell className="text-right">{c.boys}</TableCell>
                                    <TableCell className="text-right">{c.girls}</TableCell>
                                    <TableCell className="text-right">
                                        <Button asChild size="sm" variant="outline">
                                            <Link
                                                href={`/classes/${c.id}?academic_year_id=${filters.academic_year_id}`}
                                            >
                                                View
                                            </Link>
                                        </Button>
                                    </TableCell>
                                </TableRow>
                            ))}

                            {visible.length === 0 && (
                                <TableRow>
                                    <TableCell
                                        colSpan={6}
                                        className="py-8 text-center text-muted-foreground"
                                    >
                                        No classes found.
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>

                        <TableFooter>
                            <TableRow>
                                <TableCell colSpan={2}>Total</TableCell>
                                <TableCell className="text-right">{totals.total}</TableCell>
                                <TableCell className="text-right">{totals.boys}</TableCell>
                                <TableCell className="text-right">{totals.girls}</TableCell>
                                <TableCell />
                            </TableRow>
                        </TableFooter>
                    </Table>
                </div>
            </div>
        </>
    );
}
