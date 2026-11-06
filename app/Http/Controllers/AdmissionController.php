<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Applicant;
use App\Models\Branch;
use App\Models\ClassRoom;
use App\Models\Enrollment;
use App\Models\GradeLevel;
use App\Models\Learner;
use App\Models\User;
use App\Support\ApplicantColumns;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AdmissionController extends Controller
{
    private const FIELDS = [
        'academic_year_id', 'branch_id', 'grade_level_id', 'full_name', 'assessment_number',
        'date_of_birth', 'gender', 'parent_name', 'parent_type', 'parent_phone',
        'mpesa_code', 'result', 'notes',
    ];

    /* ---------------------------------------------------------------
     |  List
     * ------------------------------------------------------------- */
    public function index(Request $request): Response
    {
        $user = $request->user();

        $years = AcademicYear::orderByDesc('name')->get(['id', 'name', 'is_current']);
        $year = $years->firstWhere('id', (int) $request->query('academic_year_id'))
            ?? $years->firstWhere('is_current', true)
            ?? $years->first();

        $branches = $this->visibleBranches($user);
        $branchId = $request->integer('branch_id') ?: null;
        if ($branchId && ! $branches->contains('id', $branchId)) {
            $branchId = null;
        }

        $gradeId = $request->integer('grade_level_id') ?: null;
        $result = in_array($request->query('result'), ['pending', 'passed', 'failed', 'admitted'], true)
            ? $request->query('result')
            : 'all';

        $base = Applicant::query()->visibleTo($user)->filter([
            'academic_year_id' => $year?->id,
            'branch_id' => $branchId,
            'grade_level_id' => $gradeId,
        ]);

        $summary = (clone $base)->selectRaw(
            "count(*) as total,
             sum(result = 'pending') as pending,
             sum(result = 'passed' and promoted_at is null) as passed,
             sum(result = 'failed') as failed,
             sum(promoted_at is not null) as admitted"
        )->first();

        $applicants = (clone $base)
            ->filter(['result' => $result])
            ->with(['branch:id,name', 'gradeLevel:id,name'])
            ->orderBy('full_name')
            ->get()
            ->map(fn (Applicant $a) => [
                'id' => $a->id,
                'full_name' => $a->full_name,
                'branch' => $a->branch->name,
                'branch_id' => $a->branch_id,
                'grade' => $a->gradeLevel->name,
                'grade_level_id' => $a->grade_level_id,
                'assessment_number' => $a->assessment_number,
                'date_of_birth' => $a->date_of_birth?->format('d/m/Y'),
                'parent_name' => $a->parent_name,
                'parent_type' => $a->parent_type,
                'parent_phone' => $a->parent_phone,
                'mpesa_code' => $a->mpesa_code,
                'result' => $a->result,
                'notes' => $a->notes,
                'admitted' => $a->promoted_at !== null,
            ]);

        // Classes an applicant can be placed in, keyed "branchId-gradeId".
        $classOptions = ClassRoom::visibleTo($user)
            ->with(['gradeLevel:id,name', 'stream:id,name'])
            ->get()
            ->groupBy(fn (ClassRoom $c) => $c->branch_id.'-'.$c->grade_level_id)
            ->map(fn (Collection $g) => $g
                ->sortBy(fn (ClassRoom $c) => $c->stream->name)
                ->values()
                ->map(fn (ClassRoom $c) => ['id' => $c->id, 'label' => $c->name])
                ->all());

        return Inertia::render('admissions/index', [
            'applicants' => $applicants,
            'summary' => [
                'total' => (int) $summary->total,
                'pending' => (int) $summary->pending,
                'passed' => (int) $summary->passed,
                'failed' => (int) $summary->failed,
                'admitted' => (int) $summary->admitted,
            ],
            'years' => $years,
            'branches' => $branches,
            'grades' => GradeLevel::orderBy('level')->get(['id', 'name']),
            'filters' => [
                'academic_year_id' => $year?->id,
                'branch_id' => $branchId,
                'grade_level_id' => $gradeId,
                'result' => $result,
            ],
            'classOptions' => $classOptions,
            'exportColumns' => ApplicantColumns::options(),
            'can' => [
                'manage' => $user->can('admissions.manage'),
                'promote' => $user->can('admissions.promote'),
                'export' => $user->can('admissions.export'),
            ],
            'canPickBranch' => $branches->count() > 1,
        ]);
    }

    /* ---------------------------------------------------------------
     |  Create / edit
     * ------------------------------------------------------------- */
    public function create(Request $request): Response
    {
        $branches = $this->branchOptions($request->user());
        $current = AcademicYear::where('is_current', true)->first();

        return $this->form('create', [
            'academic_year_id' => (string) ($current?->id ?? ''),
            'branch_id' => $branches->count() === 1 ? (string) $branches->first()['id'] : '',
            'grade_level_id' => '',
            'full_name' => '',
            'assessment_number' => '',
            'date_of_birth' => '',
            'gender' => '',
            'parent_name' => '',
            'parent_type' => 'mother',
            'parent_phone' => '',
            'mpesa_code' => '',
            'result' => 'pending',
            'notes' => '',
        ], '/admissions', $branches);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->normalise($request);
        $data = $request->validate($this->rules($request, null), $this->messages());

        Applicant::create(Arr::only($data, self::FIELDS));

        return redirect()->route('admissions.index', ['academic_year_id' => $data['academic_year_id']])
            ->with('success', "{$data['full_name']} added to admissions.");
    }

    public function edit(Request $request, Applicant $applicant): Response
    {
        $this->authorizeApplicant($request, $applicant);
        $this->abortIfAdmitted($applicant);

        return $this->form('edit', [
            'academic_year_id' => (string) $applicant->academic_year_id,
            'branch_id' => (string) $applicant->branch_id,
            'grade_level_id' => (string) $applicant->grade_level_id,
            'full_name' => $applicant->full_name,
            'assessment_number' => $applicant->assessment_number ?? '',
            'date_of_birth' => $applicant->date_of_birth->format('Y-m-d'),
            'gender' => $applicant->gender ?? '',
            'parent_name' => $applicant->parent_name,
            'parent_type' => $applicant->parent_type,
            'parent_phone' => $applicant->parent_phone,
            'mpesa_code' => $applicant->mpesa_code ?? '',
            'result' => $applicant->result,
            'notes' => $applicant->notes ?? '',
        ], "/admissions/{$applicant->id}", $this->branchOptions($request->user()));
    }

    public function update(Request $request, Applicant $applicant): RedirectResponse
    {
        $this->authorizeApplicant($request, $applicant);
        $this->abortIfAdmitted($applicant);

        $this->normalise($request);
        $data = $request->validate($this->rules($request, $applicant), $this->messages());

        $applicant->update(Arr::only($data, self::FIELDS));

        return redirect()->route('admissions.index', ['academic_year_id' => $applicant->academic_year_id])
            ->with('success', 'Applicant updated.');
    }

    public function destroy(Request $request, Applicant $applicant): RedirectResponse
    {
        $this->authorizeApplicant($request, $applicant);
        $this->abortIfAdmitted($applicant);

        $applicant->delete();

        return back()->with('success', 'Applicant removed.');
    }

    /* ---------------------------------------------------------------
     |  Promote an applicant who passed into a class
     * ------------------------------------------------------------- */
    public function promote(Request $request, Applicant $applicant): RedirectResponse
    {
        $this->authorizeApplicant($request, $applicant);

        if ($applicant->promoted_at) {
            throw ValidationException::withMessages(['class_id' => 'This applicant has already been admitted.']);
        }
        if ($applicant->result !== 'passed') {
            throw ValidationException::withMessages(['class_id' => 'Only applicants who passed can be admitted.']);
        }

        // New learners can join the current year or a later one, never a year that has passed.
        $year = $applicant->academicYear;
        $current = AcademicYear::where('is_current', true)->first();
        if ($current && ! $year->is_current && strcmp($year->name, $current->name) < 0) {
            throw ValidationException::withMessages(['class_id' => "The academic year {$year->name} has already passed."]);
        }

        $data = $request->validate([
            'class_id' => ['required', Rule::exists('classes', 'id')
                ->where('branch_id', $applicant->branch_id)
                ->where('grade_level_id', $applicant->grade_level_id)],
            'admission_number' => ['required', 'string', 'max:50', 'unique:learners,admission_number'],
            'nationality' => ['required', Rule::in(['citizen', 'non_citizen'])],
            'kemis_code' => ['nullable', 'string', 'max:50', 'unique:learners,kemis_code'],
            'takes_lunch' => ['required', 'boolean'],
        ]);

        if ($applicant->assessment_number
            && Learner::withTrashed()->where('assessment_number', $applicant->assessment_number)->exists()) {
            throw ValidationException::withMessages([
                'class_id' => "Assessment number {$applicant->assessment_number} already belongs to a learner.",
            ]);
        }

        $class = ClassRoom::findOrFail($data['class_id']);

        DB::transaction(function () use ($applicant, $data, $class) {
            // Re-check under a lock so a double click cannot admit the same applicant twice.
            $locked = Applicant::whereKey($applicant->id)->lockForUpdate()->first();
            if ($locked->promoted_at) {
                throw ValidationException::withMessages(['class_id' => 'This applicant has already been admitted.']);
            }

            $learner = Learner::create([
                'full_name' => $applicant->full_name,
                'assessment_number' => $applicant->assessment_number,
                'admission_number' => $data['admission_number'],
                'gender' => $applicant->gender,
                'date_of_birth' => $applicant->date_of_birth,
                'nationality' => $data['nationality'],
                'kemis_code' => $data['kemis_code'] ?? null,
                'contact_person' => $applicant->parent_name,
            ]);

            Enrollment::create([
                'learner_id' => $learner->id,
                'academic_year_id' => $applicant->academic_year_id,
                'class_id' => $class->id,
                'branch_id' => $class->branch_id,
                'status' => 'active',
                'takes_lunch' => $data['takes_lunch'],
            ]);

            $applicant->update(['learner_id' => $learner->id, 'promoted_at' => now()]);
        });

        return back()->with('success', "{$applicant->full_name} admitted to {$class->name}.");
    }

    /* ---------------------------------------------------------------
     |  Helpers
     * ------------------------------------------------------------- */
    private function form(string $mode, array $values, string $submitUrl, Collection $branches): Response
    {
        return Inertia::render('admissions/form', [
            'mode' => $mode,
            'values' => $values,
            'submitUrl' => $submitUrl,
            'branches' => $branches,
            'academicYears' => AcademicYear::orderByDesc('name')->get(['id', 'name']),
        ]);
    }

    private function rules(Request $request, ?Applicant $applicant): array
    {
        $grade = GradeLevel::find($request->input('grade_level_id'));
        $needsAssessment = $grade && $grade->level >= 4;

        return [
            'academic_year_id' => ['required', 'exists:academic_years,id'],
            'branch_id' => ['required', Rule::in($this->visibleBranches($request->user())->pluck('id')->all())],
            'grade_level_id' => ['required', Rule::exists('classes', 'grade_level_id')
                ->where('branch_id', $request->input('branch_id'))],
            'full_name' => ['required', 'string', 'max:255'],
            'assessment_number' => [
                $needsAssessment ? 'required' : 'nullable', 'string', 'max:50',
                Rule::unique('applicants', 'assessment_number')->ignore($applicant?->id),
                Rule::unique('learners', 'assessment_number'),
            ],
            'date_of_birth' => ['required', 'date', 'before:today'],
            'gender' => ['required', Rule::in(['male', 'female'])],
            'parent_name' => ['required', 'string', 'max:255'],
            'parent_type' => ['required', Rule::in(['mother', 'father', 'guardian'])],
            'parent_phone' => ['required', 'regex:/^\+?[0-9]{9,15}$/'],
            'mpesa_code' => ['nullable', 'regex:/^[A-Za-z0-9]{8,12}$/',
                Rule::unique('applicants', 'mpesa_code')->ignore($applicant?->id)],
            'result' => ['required', Rule::in(['pending', 'passed', 'failed'])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    private function messages(): array
    {
        return [
            'assessment_number.required' => 'The assessment number is required from Grade 4 to Grade 9.',
            'grade_level_id.exists' => 'This branch does not have that grade.',
            'parent_phone.regex' => 'Enter a valid phone number, for example 0712345678.',
            'mpesa_code.regex' => 'The M-Pesa code should be 8 to 12 letters and numbers.',
            'mpesa_code.unique' => 'This M-Pesa code has already been used for another applicant.',
        ];
    }

    /** Tidy the phone number and M-Pesa code before validating. */
    private function normalise(Request $request): void
    {
        $mpesa = trim((string) $request->input('mpesa_code'));

        $request->merge([
            'parent_phone' => preg_replace('/[\s\-()]/', '', (string) $request->input('parent_phone')),
            'mpesa_code' => $mpesa === '' ? null : strtoupper($mpesa),
        ]);
    }

    private function visibleBranches(User $user): Collection
    {
        return Branch::query()
            ->when($user->branch_id, fn ($q) => $q->where('id', $user->branch_id))
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /** Branches plus the grades each one actually has classes for. */
    private function branchOptions(User $user): Collection
    {
        $classes = ClassRoom::with('gradeLevel:id,name,level')->get()->groupBy('branch_id');

        return $this->visibleBranches($user)->map(fn (Branch $b) => [
            'id' => $b->id,
            'name' => $b->name,
            'grades' => $classes->get($b->id, collect())
                ->map(fn (ClassRoom $c) => $c->gradeLevel)
                ->unique('id')
                ->sortBy('level')
                ->values()
                ->map(fn ($g) => ['id' => $g->id, 'name' => $g->name, 'level' => $g->level])
                ->all(),
        ])->values();
    }

    private function authorizeApplicant(Request $request, Applicant $applicant): void
    {
        $branchId = $request->user()->branch_id;
        abort_if($branchId && $branchId !== $applicant->branch_id, 403);
    }

    private function abortIfAdmitted(Applicant $applicant): void
    {
        abort_if($applicant->promoted_at, 403, 'This applicant has already been admitted. Edit them as a learner instead.');
    }
}
