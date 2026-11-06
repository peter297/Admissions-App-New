import { Head, Link, useForm } from '@inertiajs/react';
import { type ReactNode, useMemo } from 'react';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';

type FormValues = {
    academic_year_id: string;
    branch_id: string;
    grade_level_id: string;
    full_name: string;
    assessment_number: string;
    date_of_birth: string;
    gender: string;
    parent_name: string;
    parent_type: string;
    parent_phone: string;
    mpesa_code: string;
    result: string;
    notes: string;
};

type Props = {
    mode: 'create' | 'edit';
    values: FormValues;
    submitUrl: string;
    branches: { id: number; name: string; grades: { id: number; name: string; level: number }[] }[];
    academicYears: { id: number; name: string }[];
};

function Field({ label, error, children }: { label: string; error?: string; children: ReactNode }) {
    return (
        <div className="flex flex-col gap-2">
            <Label>{label}</Label>
            {children}
            {error && <p className="text-sm text-destructive">{error}</p>}
        </div>
    );
}

export default function AdmissionForm({ mode, values, submitUrl, branches, academicYears }: Props) {
    const form = useForm<FormValues>(values);
    const { data, setData, errors, processing } = form;

    // A branch only offers the grades it really has classes for.
    const grades = useMemo(
        () => branches.find((b) => String(b.id) === data.branch_id)?.grades ?? [],
        [branches, data.branch_id],
    );

    // Grade 4 to Grade 9 applicants must have an assessment number.
    const assessmentRequired = (grades.find((g) => String(g.id) === data.grade_level_id)?.level ?? 0) >= 4;

    function submit(e: React.FormEvent) {
        e.preventDefault();
        if (mode === 'create') form.post(submitUrl);
        else form.put(submitUrl);
    }

    return (
        <>
            <Head title={mode === 'create' ? 'Add applicant' : 'Edit applicant'} />

            <form onSubmit={submit} className="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4">
                <h1 className="text-xl font-semibold">{mode === 'create' ? 'Add applicant' : `Edit applicant: ${data.full_name}`}</h1>

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <Field label="Academic year" error={errors.academic_year_id}>
                        <Select value={data.academic_year_id} onValueChange={(v) => setData('academic_year_id', v)}>
                            <SelectTrigger className="w-full">
                                <SelectValue placeholder="Select year" />
                            </SelectTrigger>
                            <SelectContent>
                                {academicYears.map((y) => (
                                    <SelectItem key={y.id} value={String(y.id)}>
                                        {y.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </Field>

                    <Field label="Branch" error={errors.branch_id}>
                        <Select
                            value={data.branch_id}
                            // Changing the branch clears the grade, because grades differ by branch.
                            onValueChange={(v) => setData((prev) => ({ ...prev, branch_id: v, grade_level_id: '' }))}
                        >
                            <SelectTrigger className="w-full">
                                <SelectValue placeholder="Select branch" />
                            </SelectTrigger>
                            <SelectContent>
                                {branches.map((b) => (
                                    <SelectItem key={b.id} value={String(b.id)}>
                                        {b.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </Field>

                    <Field label="Grade applying for" error={errors.grade_level_id}>
                        <Select
                            value={data.grade_level_id}
                            onValueChange={(v) => setData('grade_level_id', v)}
                            disabled={!data.branch_id}
                        >
                            <SelectTrigger className="w-full">
                                <SelectValue placeholder={data.branch_id ? 'Select grade' : 'Choose a branch first'} />
                            </SelectTrigger>
                            <SelectContent>
                                {grades.map((g) => (
                                    <SelectItem key={g.id} value={String(g.id)}>
                                        {g.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </Field>

                    <Field label="Full name" error={errors.full_name}>
                        <Input value={data.full_name} onChange={(e) => setData('full_name', e.target.value)} />
                    </Field>

                    <Field label="Gender" error={errors.gender}>
                        <Select value={data.gender} onValueChange={(v) => setData('gender', v)}>
                            <SelectTrigger className="w-full">
                                <SelectValue placeholder="Select gender" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="male">Male</SelectItem>
                                <SelectItem value="female">Female</SelectItem>
                            </SelectContent>
                        </Select>
                    </Field>

                    <Field label="Date of birth" error={errors.date_of_birth}>
                        <Input
                            type="date"
                            value={data.date_of_birth}
                            onChange={(e) => setData('date_of_birth', e.target.value)}
                        />
                    </Field>

                    <Field
                        label={assessmentRequired ? 'Assessment number (required)' : 'Assessment number'}
                        error={errors.assessment_number}
                    >
                        <Input
                            value={data.assessment_number}
                            onChange={(e) => setData('assessment_number', e.target.value)}
                        />
                    </Field>

                    <Field label="Parent / guardian name" error={errors.parent_name}>
                        <Input value={data.parent_name} onChange={(e) => setData('parent_name', e.target.value)} />
                    </Field>

                    <Field label="Relationship" error={errors.parent_type}>
                        <Select value={data.parent_type} onValueChange={(v) => setData('parent_type', v)}>
                            <SelectTrigger className="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="mother">Mother</SelectItem>
                                <SelectItem value="father">Father</SelectItem>
                                <SelectItem value="guardian">Guardian</SelectItem>
                            </SelectContent>
                        </Select>
                    </Field>

                    <Field label="Phone number" error={errors.parent_phone}>
                        <Input
                            inputMode="tel"
                            placeholder="0712345678"
                            value={data.parent_phone}
                            onChange={(e) => setData('parent_phone', e.target.value)}
                        />
                    </Field>

                    <Field label="M-Pesa code (admission fee)" error={errors.mpesa_code}>
                        <Input
                            className="uppercase"
                            value={data.mpesa_code}
                            onChange={(e) => setData('mpesa_code', e.target.value)}
                        />
                    </Field>

                    <Field label="Result" error={errors.result}>
                        <Select value={data.result} onValueChange={(v) => setData('result', v)}>
                            <SelectTrigger className="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="pending">Pending</SelectItem>
                                <SelectItem value="passed">Passed</SelectItem>
                                <SelectItem value="failed">Failed</SelectItem>
                            </SelectContent>
                        </Select>
                    </Field>
                </div>

                <Field label="Notes" error={errors.notes}>
                    <Textarea value={data.notes} onChange={(e) => setData('notes', e.target.value)} />
                </Field>

                <div className="flex gap-3">
                    <Button type="submit" disabled={processing}>
                        {processing ? 'Saving...' : mode === 'create' ? 'Add applicant' : 'Save changes'}
                    </Button>
                    <Button asChild variant="outline">
                        <Link href="/admissions">Cancel</Link>
                    </Button>
                </div>
            </form>
        </>
    );
}

// Add the same breadcrumb pattern you used on the other pages.
