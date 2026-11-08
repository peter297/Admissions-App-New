<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Activity;
use App\Models\Bus;
use App\Models\ClassRoom;
use App\Models\Enrollment;
use App\Models\Learner;
use App\Models\Term;
use App\Models\TransportRoute;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class LearnerController extends Controller
{
    private const LEARNER_FIELDS = [
        'full_name', 'assessment_number', 'admission_number', 'gender',
        'date_of_birth', 'birth_certificate_number', 'nationality',
        'kemis_code', 'contact_person', 'admission_date',
    ];

    /* ---------------------------------------------------------------
     |  Create
     * ------------------------------------------------------------- */
    public function create(Request $request, ClassRoom $classRoom): Response
    {
        $this->authorizeClass($request->user(), $classRoom);

        return $this->form('create', $classRoom, [
            'class_id' => (string) $classRoom->id,
            'full_name' => '',
            'assessment_number' => '',
            'admission_number' => '',
            'admission_date' => now()->toDateString(),
            'gender' => '',
            'date_of_birth' => '',
            'birth_certificate_number' => '',
            'nationality' => 'citizen',
            'kemis_code' => '',
            'contact_person' => '',
            'activity_ids' => [],
            'transport_route_id' => '',
            'bus_id' => '',
            'takes_lunch' => false,
            'status' => 'active',
            'status_changed_term_id' => '',
            'status_note' => '',
            'status_changed_at' => '',
        ], route('learners.store', $classRoom), AcademicYear::where('is_current', true)->first());
    }

    public function store(Request $request, ClassRoom $classRoom): RedirectResponse
    {
        $this->authorizeClass($request->user(), $classRoom);

        $year = AcademicYear::where('is_current', true)->firstOrFail();
        $data = $request->validate($this->rules($classRoom, null, $year->id), $this->messages());

        $target = ClassRoom::findOrFail($data['class_id']);

        DB::transaction(function () use ($data, $year, $target) {
            $learner = Learner::create(Arr::only($data, self::LEARNER_FIELDS));

            $enrollment = Enrollment::create([
                'learner_id' => $learner->id,
                'academic_year_id' => $year->id,
                'class_id' => $target->id,
                'branch_id' => $target->branch_id,
                'status' => $data['status'],
                'status_changed_term_id' => $data['status'] === 'active' ? null : ($data['status_changed_term_id'] ?? null),
                'status_changed_at' => $data['status'] === 'active' ? null : ($data['status_changed_at'] ?? now()->toDateString()),
                'status_note' => $data['status'] === 'active' ? null : ($data['status_note'] ?? null),
                'takes_lunch' => $data['takes_lunch'],
                'transport_route_id' => $data['transport_route_id'] ?? null,
                'bus_id' => $data['bus_id'] ?? null,
            ]);

            $enrollment->activities()->sync($data['activity_ids'] ?? []);
        });

        return redirect()
            ->route('classes.show', $target)
            ->with('success', 'Learner added.');
    }

    /* ---------------------------------------------------------------
     |  Edit (always works on the enrollment, so the year is explicit)
     * ------------------------------------------------------------- */
    public function edit(Request $request, Enrollment $enrollment): Response
    {
        $this->authorizeEnrollment($request->user(), $enrollment);

        $enrollment->load(['learner', 'activities:id', 'classRoom']);
        $l = $enrollment->learner;

        return $this->form('edit', $enrollment->classRoom, [
            'class_id' => (string) $enrollment->class_id,
            'full_name' => $l->full_name,
            'assessment_number' => $l->assessment_number ?? '',
            'admission_number' => $l->admission_number,
            'admission_date' => $l->admission_date?->format('Y-m-d') ?? '',
            'gender' => $l->gender,
            'date_of_birth' => $l->date_of_birth->format('Y-m-d'),
            'birth_certificate_number' => $l->birth_certificate_number ?? '',
            'nationality' => $l->nationality,
            'kemis_code' => $l->kemis_code ?? '',
            'contact_person' => $l->contact_person ?? '',
            'activity_ids' => $enrollment->activities->pluck('id')->all(),
            'transport_route_id' => (string) ($enrollment->transport_route_id ?? ''),
            'bus_id' => (string) ($enrollment->bus_id ?? ''),
            'takes_lunch' => $enrollment->takes_lunch,
            'status' => $enrollment->status,
            'status_changed_term_id' => (string) ($enrollment->status_changed_term_id ?? ''),
            'status_note' => $enrollment->status_note ?? '',
            'status_changed_at' => $enrollment->status_changed_at?->format('Y-m-d') ?? '',
        ], route('learners.update', $enrollment), $enrollment->academicYear);
    }

    public function update(Request $request, Enrollment $enrollment): RedirectResponse
    {
        $this->authorizeEnrollment($request->user(), $enrollment);

        $classRoom = $enrollment->classRoom;
        $data = $request->validate(
            $this->rules($classRoom, $enrollment->learner, $enrollment->academic_year_id),
            $this->messages()
        );

        $target = ClassRoom::findOrFail($data['class_id']);
        $active = $data['status'] === 'active';
        $statusChanged = $data['status'] !== $enrollment->status;

        DB::transaction(function () use ($data, $enrollment, $target, $active, $statusChanged) {
            $enrollment->learner->update(Arr::only($data, self::LEARNER_FIELDS));

            $enrollment->update([
                'class_id' => $target->id,
                'branch_id' => $target->branch_id,
                'takes_lunch' => $data['takes_lunch'],
                'transport_route_id' => $data['transport_route_id'] ?? null,
                'bus_id' => $data['bus_id'] ?? null,
                'status' => $data['status'],
                'status_changed_term_id' => $active ? null : ($data['status_changed_term_id'] ?? null),
                'status_changed_at' => $active
                    ? null
                    : ($data['status_changed_at'] ?? ($statusChanged ? now()->toDateString() : $enrollment->status_changed_at)),
                'status_note' => $active ? null : ($data['status_note'] ?? null),
            ]);

            $enrollment->activities()->sync($data['activity_ids'] ?? []);
        });

        return redirect()
            ->route('classes.show', $target)
            ->with('success', 'Learner updated.');
    }

    /* ---------------------------------------------------------------
     |  Shared pieces
     * ------------------------------------------------------------- */
    private function form(string $mode, ClassRoom $classRoom, array $values, string $submitUrl, ?AcademicYear $year): Response
    {
        $classRoom->loadMissing(['branch:id,name', 'gradeLevel:id,name', 'stream:id,name']);

        // "Stream selection" = the classes of the same grade in the same branch.
        $streamOptions = ClassRoom::query()
            ->where('branch_id', $classRoom->branch_id)
            ->where('grade_level_id', $classRoom->grade_level_id)
            ->with('stream:id,name')
            ->get()
            ->sortBy(fn ($c) => $c->stream->name)
            ->values()
            ->map(fn ($c) => ['id' => $c->id, 'label' => $c->stream->name]);

        return Inertia::render('learners/form', [
            'mode' => $mode,
            'classRoom' => [
                'id' => $classRoom->id,
                'name' => $classRoom->name,
                'branch' => $classRoom->branch->name,
                'grade' => $classRoom->gradeLevel->name,
            ],
            'streamOptions' => $streamOptions,
            'activities' => Activity::orderBy('name')->get(['id', 'name']),
            'routes' => TransportRoute::orderBy('name')->get(['id', 'name']),
            'buses' => Bus::where('branch_id', $classRoom->branch_id)
                ->orderBy('number_plate')
                ->get(['id', 'number_plate', 'transport_route_id']),
            'terms' => Term::where('academic_year_id', $year?->id)
                ->orderBy('number')
                ->get()
                ->map(fn ($t) => ['id' => $t->id, 'label' => ($year->name).' Term '.$t->number]),
            'values' => $values,
            'submitUrl' => $submitUrl,
            'backUrl' => route('classes.show', $classRoom),
        ]);
    }

    private function rules(ClassRoom $classRoom, ?Learner $learner, int $yearId): array
    {
        $rules = [
            'class_id' => [
                'required',
                Rule::exists('classes', 'id')
                    ->where('branch_id', $classRoom->branch_id)
                    ->where('grade_level_id', $classRoom->grade_level_id),
            ],
            'full_name' => ['required', 'string', 'max:255'],
            'assessment_number' => ['nullable', 'string', 'max:50',
                Rule::unique('learners', 'assessment_number')->ignore($learner?->id)],
            'admission_number' => ['required', 'string', 'max:50',
                Rule::unique('learners', 'admission_number')->ignore($learner?->id)],
            'admission_date' => ['nullable', 'date', 'before_or_equal:today'],
            'gender' => ['required', Rule::in(['male', 'female'])],
            'date_of_birth' => ['required', 'date', 'before:today'],
            'birth_certificate_number' => ['nullable', 'string', 'max:50'],
            'nationality' => ['required', Rule::in(['citizen', 'non_citizen'])],
            'kemis_code' => ['nullable', 'string', 'max:50',
                Rule::unique('learners', 'kemis_code')->ignore($learner?->id)],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'activity_ids' => ['nullable', 'array'],
            'activity_ids.*' => ['integer', 'exists:activities,id'],
            'transport_route_id' => ['nullable', 'exists:transport_routes,id'],
            'bus_id' => ['nullable',
                Rule::exists('buses', 'id')->where('branch_id', $classRoom->branch_id)],
            'takes_lunch' => ['required', 'boolean'],
        ];

        $rules['status'] = ['required', Rule::in(['active', 'inactive', 'transferred'])];
        $rules['status_changed_term_id'] = [
            'nullable', 'required_unless:status,active',
            Rule::exists('terms', 'id')->where('academic_year_id', $yearId),
        ];
        $rules['status_note'] = ['required_unless:status,active', 'nullable', 'string', 'max:1000'];
        $rules['status_changed_at'] = ['required_unless:status,active', 'nullable', 'date', 'before_or_equal:today'];

        return $rules;
    }

    private function messages(): array
    {
        return [
            'status_note.required_unless' => 'Please give the reason the learner left.',
            'status_changed_at.required_unless' => 'Please enter the date the learner left.',
            'status_changed_term_id.required_unless' => 'Please choose the term the learner left in.',
        ];
    }

    private function authorizeClass(User $user, ClassRoom $classRoom): void
    {
        abort_unless(
            ClassRoom::visibleTo($user)->whereKey($classRoom->id)->exists(),
            403
        );
    }

    private function authorizeEnrollment(User $user, Enrollment $enrollment): void
    {
        $this->authorizeClass($user, $enrollment->classRoom);

        // Past years are read-only history.
        abort_unless($enrollment->academicYear->is_current, 403);
    }
}
