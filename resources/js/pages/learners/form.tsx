import { Head, Link, useForm } from '@inertiajs/react';
import { type ReactNode, useMemo } from 'react';

import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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

type Option = { id: number; name: string };

type FormValues = {
    class_id: string;
    full_name: string;
    assessment_number: string;
    admission_number: string;
    admission_date: string;
    gender: string;
    date_of_birth: string;
    birth_certificate_number: string;
    nationality: string;
    kemis_code: string;
    contact_person: string;
    activity_ids: number[];
    transport_route_id: string;
    bus_id: string;
    takes_lunch: boolean;
    status: string;
    status_changed_term_id: string;
    status_note: string;
    status_changed_at: string;
};

type Props = {
    mode: 'create' | 'edit';
    classRoom: { id: number; name: string; branch: string; grade: string };
    streamOptions: { id: number; label: string }[];
    activities: Option[];
    routes: Option[];
    buses: { id: number; number_plate: string; transport_route_id: number | null }[];
    terms: { id: number; label: string }[];
    values: FormValues;
    submitUrl: string;
    backUrl: string;
};

const NONE = 'none'; // Select items cannot have an empty value, so we use a marker.

// A small reusable wrapper: label + input + error message.
function Field({ label, error, children }: { label: string; error?: string; children: ReactNode }) {
    return (
        <div className="flex flex-col gap-2">
            <Label>{label}</Label>
            {children}
            {error && <p className="text-sm text-destructive">{error}</p>}
        </div>
    );
}

export default function LearnerForm({
    mode,
    classRoom,
    streamOptions,
    activities,
    routes,
    buses,
    terms,
    values,
    submitUrl,
    backUrl,
}: Props) {
    // useForm holds all field values, the validation errors and the submit state.
    const form = useForm<FormValues>(values);
    const { data, setData, errors, processing } = form;

    // Only show buses on the chosen route (or all buses when no route is chosen).
    const visibleBuses = useMemo(
        () =>
            data.transport_route_id
                ? buses.filter((b) => b.transport_route_id === Number(data.transport_route_id))
                : buses,
        [buses, data.transport_route_id],
    );

    function toggleActivity(id: number, checked: boolean) {
        setData(
            'activity_ids',
            checked ? [...data.activity_ids, id] : data.activity_ids.filter((x) => x !== id),
        );
    }

    function submit(e: React.FormEvent) {
        e.preventDefault();
        if (mode === 'create') form.post(submitUrl);
        else form.put(submitUrl);
    }

    const title = mode === 'create' ? `Add learner: ${classRoom.grade}` : `Edit learner: ${data.full_name}`;

    return (
        <>
            <Head title={title} />

            <form onSubmit={submit} className="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4">
                <div>
                    <h1 className="text-xl font-semibold">{title}</h1>
                    <p className="text-sm text-muted-foreground">{classRoom.branch}</p>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <Field label="Stream" error={errors.class_id}>
                        <Select value={data.class_id} onValueChange={(v) => setData('class_id', v)}>
                            <SelectTrigger className="w-full">
                                <SelectValue placeholder="Select stream" />
                            </SelectTrigger>
                            <SelectContent>
                                {streamOptions.map((s) => (
                                    <SelectItem key={s.id} value={String(s.id)}>
                                        {classRoom.grade} {s.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </Field>

                    <Field label="Full name (as per birth certificate)" error={errors.full_name}>
                        <Input value={data.full_name} onChange={(e) => setData('full_name', e.target.value)} />
                    </Field>

                    <Field label="Assessment number" error={errors.assessment_number}>
                        <Input
                            value={data.assessment_number}
                            onChange={(e) => setData('assessment_number', e.target.value)}
                        />
                    </Field>

                    <Field label="Admission number" error={errors.admission_number}>
                        <Input
                            value={data.admission_number}
                            onChange={(e) => setData('admission_number', e.target.value)}
                        />
                    </Field>

                    <Field label="Admission date" error={errors.admission_date}>
                        <Input
                            type="date"
                            value={data.admission_date}
                            onChange={(e) => setData('admission_date', e.target.value)}
                        />
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

                    <Field label="Birth certificate entry number" error={errors.birth_certificate_number}>
                        <Input
                            value={data.birth_certificate_number}
                            onChange={(e) => setData('birth_certificate_number', e.target.value)}
                        />
                    </Field>

                    <Field label="Nationality" error={errors.nationality}>
                        <Select value={data.nationality} onValueChange={(v) => setData('nationality', v)}>
                            <SelectTrigger className="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="citizen">Citizen</SelectItem>
                                <SelectItem value="non_citizen">Non citizen</SelectItem>
                            </SelectContent>
                        </Select>
                    </Field>

                    <Field label="Kemis code" error={errors.kemis_code}>
                        <Input value={data.kemis_code} onChange={(e) => setData('kemis_code', e.target.value)} />
                    </Field>

                    <Field label="Contact person" error={errors.contact_person}>
                        <Input
                            value={data.contact_person}
                            onChange={(e) => setData('contact_person', e.target.value)}
                        />
                    </Field>

                    <Field label="Transport route" error={errors.transport_route_id}>
                        <Select
                            value={data.transport_route_id || NONE}
                            onValueChange={(v) =>
                                // Changing the route clears the bus, because the old bus may not be on it.
                                setData((prev) => ({
                                    ...prev,
                                    transport_route_id: v === NONE ? '' : v,
                                    bus_id: '',
                                }))
                            }
                        >
                            <SelectTrigger className="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={NONE}>Not transported</SelectItem>
                                {routes.map((r) => (
                                    <SelectItem key={r.id} value={String(r.id)}>
                                        {r.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </Field>

                    <Field label="Bus" error={errors.bus_id}>
                        <Select
                            value={data.bus_id || NONE}
                            onValueChange={(v) => setData('bus_id', v === NONE ? '' : v)}
                        >
                            <SelectTrigger className="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={NONE}>No bus</SelectItem>
                                {visibleBuses.map((b) => (
                                    <SelectItem key={b.id} value={String(b.id)}>
                                        {b.number_plate}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </Field>

                    <Field label="School lunch" error={errors.takes_lunch}>
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
                    </Field>

                    <Field label="Status" error={errors.status}>
                        <Select
                            value={data.status}
                            onValueChange={(v) =>
                                // Leaving today is the usual case, so the date starts as today.
                                setData((prev) => ({
                                    ...prev,
                                    status: v,
                                    status_changed_at:
                                        v !== 'active' && !prev.status_changed_at
                                            ? new Date().toLocaleDateString('en-CA')
                                            : prev.status_changed_at,
                                }))
                            }
                        >
                            <SelectTrigger className="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="active">Active</SelectItem>
                                <SelectItem value="inactive">Inactive</SelectItem>
                                <SelectItem value="transferred">Transferred</SelectItem>
                            </SelectContent>
                        </Select>
                    </Field>
                </div>

                <Field label="Co-curricular activities" error={errors.activity_ids}>
                    <div className="flex flex-wrap gap-4">
                        {activities.map((a) => (
                            <label key={a.id} className="flex items-center gap-2 text-sm">
                                <Checkbox
                                    checked={data.activity_ids.includes(a.id)}
                                    onCheckedChange={(c) => toggleActivity(a.id, c === true)}
                                />
                                {a.name}
                            </label>
                        ))}
                        {activities.length === 0 && (
                            <span className="text-sm text-muted-foreground">No activities set up yet.</span>
                        )}
                    </div>
                </Field>

                {/* Term and note appear only when the status is not Active. */}
                {data.status !== 'active' && (
                    <div className="grid gap-4 rounded-lg border p-4 sm:grid-cols-2 lg:grid-cols-3">
                        <Field label="Term left in" error={errors.status_changed_term_id}>
                            <Select
                                value={data.status_changed_term_id}
                                onValueChange={(v) => setData('status_changed_term_id', v)}
                            >
                                <SelectTrigger className="w-full">
                                    <SelectValue placeholder="Select term" />
                                </SelectTrigger>
                                <SelectContent>
                                    {terms.map((t) => (
                                        <SelectItem key={t.id} value={String(t.id)}>
                                            {t.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </Field>

                        <Field label="Date left" error={errors.status_changed_at}>
                            <Input
                                type="date"
                                value={data.status_changed_at}
                                onChange={(e) => setData('status_changed_at', e.target.value)}
                            />
                        </Field>

                        <div className="col-span-full">
                            <Field label="Reason for leaving" error={errors.status_note}>
                                <Textarea
                                    value={data.status_note}
                                    onChange={(e) => setData('status_note', e.target.value)}
                                />
                            </Field>
                        </div>
                    </div>
                )}

                <div className="flex gap-3">
                    <Button type="submit" disabled={processing}>
                        {processing ? 'Saving...' : mode === 'create' ? 'Add learner' : 'Save changes'}
                    </Button>
                    <Button asChild variant="outline">
                        <Link href={backUrl}>Cancel</Link>
                    </Button>
                </div>
            </form>
        </>
    );
}

// Add the same breadcrumb pattern you used on the other pages, for example:
// LearnerForm.layout = { breadcrumbs: [{ title: 'Classes', href: '/classes' }] };
