import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';

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

type TermRow = { id: number; number: number; is_current: boolean };
type YearRow = { id: number; name: string; is_current: boolean; learners: number; terms: TermRow[] };

type Preview = {
    rows: { class: string; branch: string; total: number; type: 'promote' | 'graduate' | 'missing'; destination: string }[];
    promoting: number;
    graduating: number;
    missing: number;
    not_carried: number;
    target: { id: number; name: string };
};

type Props = {
    years: YearRow[];
    currentYear: { id: number; name: string } | null;
    targets: { id: number; name: string }[];
    targetYearId: number | null;
    preview: Preview | null;
};

/* ---------------- New year dialog ---------------- */
function NewYearDialog({ onClose }: { onClose: () => void }) {
    const form = useForm({ name: '' });

    return (
        <Dialog open onOpenChange={(o) => !o && onClose()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>New academic year</DialogTitle>
                    <DialogDescription>Three terms are created automatically.</DialogDescription>
                </DialogHeader>

                <div className="flex flex-col gap-2">
                    <Label>Year</Label>
                    <Input
                        placeholder="e.g. 2027"
                        value={form.data.name}
                        onChange={(e) => form.setData('name', e.target.value)}
                    />
                    {form.errors.name && <p className="text-sm text-destructive">{form.errors.name}</p>}
                </div>

                <DialogFooter>
                    <Button variant="outline" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button
                        disabled={form.processing}
                        onClick={() => form.post('/academic-years', { preserveScroll: true, onSuccess: onClose })}
                    >
                        Create
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

/* ---------------- Rollover confirmation ---------------- */
function RolloverDialog({
    preview,
    currentName,
    onClose,
}: {
    preview: Preview;
    currentName: string;
    onClose: () => void;
}) {
    const [makeCurrent, setMakeCurrent] = useState(true);
    const [processing, setProcessing] = useState(false);
    const [error, setError] = useState<string | null>(null);

    function run() {
        setProcessing(true);
        setError(null);

        router.post(
            '/academic-years/rollover',
            { target_year_id: preview.target.id, make_current: makeCurrent },
            {
                onSuccess: onClose,
                onError: (errors) => setError(Object.values(errors)[0] ?? 'The rollover failed.'),
                onFinish: () => setProcessing(false),
            },
        );
    }

    return (
        <Dialog open onOpenChange={(o) => !o && !processing && onClose()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        Roll over {currentName} to {preview.target.name}?
                    </DialogTitle>
                    <DialogDescription>
                        {preview.promoting} learners will move up a grade and {preview.graduating} will graduate. This
                        cannot be undone in one click, so make sure the preview is right.
                    </DialogDescription>
                </DialogHeader>

                <label className="flex items-center gap-2 text-sm">
                    <Checkbox checked={makeCurrent} onCheckedChange={(c) => setMakeCurrent(c === true)} />
                    Make {preview.target.name} the current academic year afterwards
                </label>

                {error && <p className="text-sm text-destructive">{error}</p>}

                <DialogFooter>
                    <Button variant="outline" onClick={onClose} disabled={processing}>
                        Cancel
                    </Button>
                    <Button onClick={run} disabled={processing}>
                        {processing ? 'Rolling over...' : 'Run rollover'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

/* ---------------- Page ---------------- */
export default function AcademicYearsIndex({ years, currentYear, targets, targetYearId, preview }: Props) {
    const { flash } = usePage<{ flash?: { success?: string } }>().props;
    const [newYearOpen, setNewYearOpen] = useState(false);
    const [rolloverOpen, setRolloverOpen] = useState(false);

    function setCurrentYear(y: YearRow) {
        if (confirm(`Make ${y.name} the current academic year?`)) {
            router.post(`/academic-years/${y.id}/current`, {}, { preserveScroll: true });
        }
    }

    function setCurrentTerm(t: TermRow) {
        router.post(`/academic-years/terms/${t.id}/current`, {}, { preserveScroll: true });
    }

    function choosePreviewYear(value: string) {
        // A normal visit: the server builds the preview and sends it back as props.
        router.get('/academic-years', value ? { target_year_id: value } : {}, {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        });
    }

    return (
        <>
            <Head title="Academic years" />

            <div className="flex flex-col gap-8 p-4">
                {flash?.success && (
                    <div className="rounded-md border border-green-600/30 bg-green-50 p-3 text-sm text-green-800 dark:bg-green-950 dark:text-green-200">
                        {flash.success}
                    </div>
                )}

                {/* Years */}
                <section className="flex flex-col gap-3">
                    <div className="flex items-center justify-between">
                        <h1 className="text-xl font-semibold">Academic years</h1>
                        <Button onClick={() => setNewYearOpen(true)}>New academic year</Button>
                    </div>

                    <div className="rounded-lg border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Year</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead>Terms</TableHead>
                                    <TableHead className="text-right">Learners enrolled</TableHead>
                                    <TableHead className="w-40" />
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {years.map((y) => (
                                    <TableRow key={y.id}>
                                        <TableCell className="font-medium">{y.name}</TableCell>
                                        <TableCell>
                                            {y.is_current ? <Badge>Current</Badge> : <Badge variant="outline">Past / upcoming</Badge>}
                                        </TableCell>
                                        <TableCell className="space-x-1">
                                            {y.terms.map((t) => (
                                                <Button
                                                    key={t.id}
                                                    size="sm"
                                                    variant={t.is_current ? 'default' : 'outline'}
                                                    // Only the current year's terms can be switched.
                                                    disabled={!y.is_current || t.is_current}
                                                    onClick={() => setCurrentTerm(t)}
                                                >
                                                    Term {t.number}
                                                </Button>
                                            ))}
                                        </TableCell>
                                        <TableCell className="text-right">{y.learners}</TableCell>
                                        <TableCell className="text-right">
                                            {!y.is_current && (
                                                <Button size="sm" variant="outline" onClick={() => setCurrentYear(y)}>
                                                    Set as current
                                                </Button>
                                            )}
                                        </TableCell>
                                    </TableRow>
                                ))}
                                {years.length === 0 && (
                                    <TableRow>
                                        <TableCell colSpan={5} className="py-8 text-center text-muted-foreground">
                                            No academic years yet.
                                        </TableCell>
                                    </TableRow>
                                )}
                            </TableBody>
                        </Table>
                    </div>
                </section>

                {/* Rollover */}
                {currentYear && (
                    <section className="flex flex-col gap-4 rounded-lg border p-4">
                        <div>
                            <h2 className="text-lg font-semibold">Year-end rollover</h2>
                            <p className="text-sm text-muted-foreground">
                                Moves every active learner in {currentYear.name} to the same stream of the next grade.
                                Learners in a branch's final grade graduate and appear under Alumni. Inactive and
                                transferred learners stay in {currentYear.name}. Learners who must repeat a grade can be
                                moved back afterwards with the bulk move on the class page.
                            </p>
                        </div>

                        <div className="flex max-w-sm flex-col gap-2">
                            <Label>Roll over into</Label>
                            <Select value={targetYearId ? String(targetYearId) : ''} onValueChange={choosePreviewYear}>
                                <SelectTrigger className="w-full">
                                    <SelectValue placeholder={targets.length ? 'Select a year' : 'Create a new year first'} />
                                </SelectTrigger>
                                <SelectContent>
                                    {targets.map((t) => (
                                        <SelectItem key={t.id} value={String(t.id)}>
                                            {t.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        {preview && (
                            <>
                                <div className="rounded-lg border">
                                    <Table>
                                        <TableHeader>
                                            <TableRow>
                                                <TableHead>From class</TableHead>
                                                <TableHead>Branch</TableHead>
                                                <TableHead className="text-right">Learners</TableHead>
                                                <TableHead>Moves to</TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {preview.rows.map((r, i) => (
                                                <TableRow key={i}>
                                                    <TableCell className="font-medium">{r.class}</TableCell>
                                                    <TableCell>{r.branch}</TableCell>
                                                    <TableCell className="text-right">{r.total}</TableCell>
                                                    <TableCell className={r.type === 'missing' ? 'text-destructive' : ''}>
                                                        {r.destination}
                                                    </TableCell>
                                                </TableRow>
                                            ))}
                                            {preview.rows.length === 0 && (
                                                <TableRow>
                                                    <TableCell colSpan={4} className="py-8 text-center text-muted-foreground">
                                                        There are no active learners to roll over.
                                                    </TableCell>
                                                </TableRow>
                                            )}
                                        </TableBody>
                                    </Table>
                                </div>

                                <p className="text-sm">
                                    <strong>{preview.promoting}</strong> will move up, <strong>{preview.graduating}</strong>{' '}
                                    will graduate, and <strong>{preview.not_carried}</strong> inactive or transferred
                                    learners stay behind.
                                </p>

                                {preview.missing > 0 && (
                                    <p className="text-sm text-destructive">
                                        {preview.missing} learners are in classes with no class in the next grade. Add
                                        the missing classes before running the rollover.
                                    </p>
                                )}

                                <div>
                                    <Button
                                        disabled={preview.missing > 0 || preview.promoting + preview.graduating === 0}
                                        onClick={() => setRolloverOpen(true)}
                                    >
                                        Run rollover
                                    </Button>
                                </div>
                            </>
                        )}
                    </section>
                )}
            </div>

            {newYearOpen && <NewYearDialog onClose={() => setNewYearOpen(false)} />}
            {rolloverOpen && preview && currentYear && (
                <RolloverDialog
                    preview={preview}
                    currentName={currentYear.name}
                    onClose={() => setRolloverOpen(false)}
                />
            )}
        </>
    );
}
