import { Head, Link } from '@inertiajs/react';
import { useMemo, useState } from 'react';

import AlumniExportDialog from '@/components/alumni-export-dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';

type Learner = {
    enrollment_id: number;
    assessment_number: string | null;
    full_name: string;
    admission_number: string;
    gender: 'male' | 'female';
    nationality: 'citizen' | 'non_citizen';
    kemis_code: string | null;
    class: string;
    graduated_on: string | null;
};

type Props = {
    cohort: { name: string; branch: string; year: string; academic_year_id: number; branch_id: number };
    learners: Learner[];
    exportColumns: { key: string; label: string }[];
};

export default function AlumniShow({ cohort, learners, exportColumns }: Props) {
    const [search, setSearch] = useState('');
    const [exportOpen, setExportOpen] = useState(false);

    const visible = useMemo(() => {
        const term = search.trim().toLowerCase();
        if (!term) return learners;
        return learners.filter(
            (l) =>
                l.full_name.toLowerCase().includes(term) ||
                l.admission_number.toLowerCase().includes(term) ||
                (l.assessment_number ?? '').toLowerCase().includes(term) ||
                (l.kemis_code ?? '').toLowerCase().includes(term),
        );
    }, [learners, search]);

    return (
        <>
            <Head title={cohort.name} />

            <div className="flex flex-col gap-4 p-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold">{cohort.name}</h1>
                        <p className="text-sm text-muted-foreground">
                            {cohort.branch} · {learners.length} learners
                        </p>
                    </div>

                    <Button asChild variant="outline" size="sm">
                        <Link href="/alumni">Back to alumni</Link>
                    </Button>
                </div>

                <div className="flex flex-wrap items-center gap-3">
                    <Input
                        className="w-full sm:w-72"
                        placeholder="Search name, admission, assessment or Kemis no..."
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                    />
                    <Button variant="outline" onClick={() => setExportOpen(true)}>
                        Download
                    </Button>
                </div>

                <div className="rounded-lg border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Assessment No.</TableHead>
                                <TableHead>Name</TableHead>
                                <TableHead>Admission No.</TableHead>
                                <TableHead>Citizenship</TableHead>
                                <TableHead>Gender</TableHead>
                                <TableHead>Kemis No.</TableHead>
                                <TableHead>Last class</TableHead>
                                <TableHead>Graduated</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {visible.map((l) => (
                                <TableRow key={l.enrollment_id}>
                                    <TableCell>{l.assessment_number ?? '-'}</TableCell>
                                    <TableCell className="font-medium">{l.full_name}</TableCell>
                                    <TableCell>{l.admission_number}</TableCell>
                                    <TableCell>{l.nationality === 'citizen' ? 'Citizen' : 'Non citizen'}</TableCell>
                                    <TableCell className="capitalize">{l.gender}</TableCell>
                                    <TableCell>{l.kemis_code ?? '-'}</TableCell>
                                    <TableCell>{l.class}</TableCell>
                                    <TableCell>{l.graduated_on ?? '-'}</TableCell>
                                </TableRow>
                            ))}

                            {visible.length === 0 && (
                                <TableRow>
                                    <TableCell colSpan={8} className="py-8 text-center text-muted-foreground">
                                        No learners found.
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                </div>
            </div>

            <AlumniExportDialog
                open={exportOpen}
                onOpenChange={setExportOpen}
                url={`/alumni/${cohort.academic_year_id}/${cohort.branch_id}/export`}
                columns={exportColumns}
            />
        </>
    );
}
