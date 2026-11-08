import { Head, Link, router } from '@inertiajs/react';
import { type FormEvent, useState } from 'react';

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

type Result = {
    enrollment_id: number;
    full_name: string;
    admission_number: string;
    assessment_number: string | null;
    class: string;
    class_id: number;
    branch: string;
    year: string;
    year_id: number;
    is_current_year: boolean;
    status: 'active' | 'inactive' | 'transferred' | 'graduated';
};

type Props = {
    q: string;
    academicYearId: number | null;
    years: { id: number; name: string }[];
    results: Result[];
    tooShort: boolean;
    limit: number;
};

const statusStyle: Record<Result['status'], 'default' | 'secondary' | 'outline'> = {
    active: 'default',
    inactive: 'secondary',
    transferred: 'outline',
    graduated: 'secondary',
};

export default function LearnerSearch({ q, academicYearId, years, results, tooShort, limit }: Props) {
    const [text, setText] = useState(q);
    const [year, setYear] = useState(academicYearId ? String(academicYearId) : 'all');

    function search(e?: FormEvent, nextYear = year) {
        e?.preventDefault();

        const params: Record<string, string> = {};
        if (text.trim()) params.q = text.trim();
        if (nextYear !== 'all') params.academic_year_id = nextYear;

        router.get('/learners', params, { preserveState: true, replace: true });
    }

    return (
        <>
            <Head title="Find a learner" />

            <div className="flex flex-col gap-4 p-4">
                <h1 className="text-xl font-semibold">Find a learner</h1>

                <form onSubmit={search} className="flex flex-wrap items-center gap-3">
                    <Input
                        autoFocus
                        className="w-full sm:w-96"
                        placeholder="Name, admission, assessment or Kemis number..."
                        value={text}
                        onChange={(e) => setText(e.target.value)}
                    />

                    <Select
                        value={year}
                        onValueChange={(v) => {
                            setYear(v);
                            search(undefined, v);
                        }}
                    >
                        <SelectTrigger className="w-40">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All years</SelectItem>
                            {years.map((y) => (
                                <SelectItem key={y.id} value={String(y.id)}>
                                    {y.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    <Button type="submit">Search</Button>
                </form>

                {tooShort && <p className="text-sm text-muted-foreground">Type at least two characters.</p>}

                {q.length >= 2 && (
                    <div className="overflow-x-auto rounded-lg border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Name</TableHead>
                                    <TableHead>Admission No.</TableHead>
                                    <TableHead>Assessment No.</TableHead>
                                    <TableHead>Class</TableHead>
                                    <TableHead>Branch</TableHead>
                                    <TableHead>Year</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead />
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {results.map((r) => (
                                    <TableRow key={r.enrollment_id}>
                                        <TableCell className="font-medium">{r.full_name}</TableCell>
                                        <TableCell>{r.admission_number}</TableCell>
                                        <TableCell>{r.assessment_number ?? '-'}</TableCell>
                                        <TableCell>{r.class}</TableCell>
                                        <TableCell>{r.branch}</TableCell>
                                        <TableCell>{r.year}</TableCell>
                                        <TableCell>
                                            <Badge variant={statusStyle[r.status]} className="capitalize">
                                                {r.status}
                                            </Badge>
                                        </TableCell>
                                        <TableCell className="space-x-2 text-right whitespace-nowrap">
                                            <Button asChild size="sm" variant="outline">
                                                <Link href={`/classes/${r.class_id}?academic_year_id=${r.year_id}`}>View class</Link>
                                            </Button>
                                            {r.is_current_year && (
                                                <Button asChild size="sm" variant="outline">
                                                    <Link href={`/enrollments/${r.enrollment_id}/edit`}>Edit</Link>
                                                </Button>
                                            )}
                                        </TableCell>
                                    </TableRow>
                                ))}

                                {results.length === 0 && !tooShort && (
                                    <TableRow>
                                        <TableCell colSpan={8} className="py-8 text-center text-muted-foreground">
                                            No learners found for "{q}".
                                        </TableCell>
                                    </TableRow>
                                )}
                            </TableBody>
                        </Table>
                    </div>
                )}

                {results.length >= limit && (
                    <p className="text-sm text-muted-foreground">
                        Showing the first {limit} matches. Type more of the name or number to narrow it down.
                    </p>
                )}
            </div>
        </>
    );
}
