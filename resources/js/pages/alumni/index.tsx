import { Head, Link } from '@inertiajs/react';
import { useMemo, useState } from 'react';

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

type Cohort = {
    academic_year_id: number;
    year: string;
    branch_id: number;
    branch: string;
    name: string;
    total: number;
    boys: number;
    girls: number;
};

export default function AlumniIndex({ cohorts }: { cohorts: Cohort[] }) {
    const [search, setSearch] = useState('');
    const [year, setYear] = useState('all');
    const [branch, setBranch] = useState('all');

    // The filter options come from the data itself, so there is nothing to configure.
    const years = useMemo(() => [...new Set(cohorts.map((c) => c.year))], [cohorts]);
    const branches = useMemo(() => [...new Set(cohorts.map((c) => c.branch))], [cohorts]);

    const visible = useMemo(() => {
        const term = search.trim().toLowerCase();
        return cohorts.filter(
            (c) =>
                (year === 'all' || c.year === year) &&
                (branch === 'all' || c.branch === branch) &&
                (!term || c.name.toLowerCase().includes(term) || c.branch.toLowerCase().includes(term)),
        );
    }, [cohorts, search, year, branch]);

    return (
        <>
            <Head title="Alumni" />

            <div className="flex flex-col gap-4 p-4">
                <h1 className="text-xl font-semibold">Alumni</h1>

                <div className="flex flex-wrap items-center gap-3">
                    <Input
                        className="w-full sm:w-64"
                        placeholder="Search cohort or branch..."
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                    />

                    <Select value={year} onValueChange={setYear}>
                        <SelectTrigger className="w-36">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All years</SelectItem>
                            {years.map((y) => (
                                <SelectItem key={y} value={y}>
                                    {y}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    {branches.length > 1 && (
                        <Select value={branch} onValueChange={setBranch}>
                            <SelectTrigger className="w-44">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All branches</SelectItem>
                                {branches.map((b) => (
                                    <SelectItem key={b} value={b}>
                                        {b}
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
                                <TableHead>Cohort</TableHead>
                                <TableHead>Branch</TableHead>
                                <TableHead className="text-right">Learners</TableHead>
                                <TableHead className="text-right">Boys</TableHead>
                                <TableHead className="text-right">Girls</TableHead>
                                <TableHead className="w-24" />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {visible.map((c) => (
                                <TableRow key={`${c.academic_year_id}-${c.branch_id}`}>
                                    <TableCell className="font-medium">{c.name}</TableCell>
                                    <TableCell>{c.branch}</TableCell>
                                    <TableCell className="text-right">{c.total}</TableCell>
                                    <TableCell className="text-right">{c.boys}</TableCell>
                                    <TableCell className="text-right">{c.girls}</TableCell>
                                    <TableCell className="text-right">
                                        <Button asChild size="sm" variant="outline">
                                            <Link href={`/alumni/${c.academic_year_id}/${c.branch_id}`}>View</Link>
                                        </Button>
                                    </TableCell>
                                </TableRow>
                            ))}

                            {visible.length === 0 && (
                                <TableRow>
                                    <TableCell colSpan={6} className="py-8 text-center text-muted-foreground">
                                        No alumni yet. Learners appear here after a year-end rollover graduates the final grade.
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
