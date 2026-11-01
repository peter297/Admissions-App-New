<?php

namespace App\Http\Controllers;

use App\Exports\ClassListExport;
use App\Models\AcademicYear;
use App\Models\ClassRoom;
use App\Models\Enrollment;
use App\Support\ClassListColumns;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;

class ClassExportController extends Controller
{
    private const TYPES = [
        'class' => 'Class list',
        'lunch' => 'Lunch list',
        'transport' => 'Transport list',
    ];

    public function __invoke(Request $request, ClassRoom $classRoom)
    {
        abort_unless(
            ClassRoom::visibleTo($request->user())->whereKey($classRoom->id)->exists(),
            403
        );

        $data = $request->validate([
            'format' => ['required', Rule::in(['xlsx', 'pdf'])],
            'type' => ['required', Rule::in(array_keys(self::TYPES))],
            'columns' => ['required', 'array', 'min:1'],
            'columns.*' => ['string', Rule::in(array_keys(ClassListColumns::all()))],
            'academic_year_id' => ['required', 'exists:academic_years,id'],
            'status' => ['required', Rule::in(['all', 'active', 'inactive', 'transferred', 'graduated'])],
            'include_title' => ['nullable', 'boolean'],
        ]);

        $columns = ClassListColumns::normalise($data['columns']);
        $year = AcademicYear::findOrFail($data['academic_year_id']);
        $classRoom->load(['branch:id,name', 'gradeLevel:id,name', 'stream:id,name']);

        $enrollments = Enrollment::query()
            ->where('class_id', $classRoom->id)
            ->where('academic_year_id', $year->id)
            ->when($data['status'] !== 'all', fn ($q) => $q->where('status', $data['status']))
            ->when($data['type'] === 'lunch', fn ($q) => $q->where('takes_lunch', true))
            ->when($data['type'] === 'transport', fn ($q) => $q->where(
                fn ($w) => $w->whereNotNull('bus_id')->orWhereNotNull('transport_route_id')
            ))
            ->with(['learner', 'transportRoute:id,name', 'bus:id,number_plate', 'activities:id,name'])
            ->get()
            ->sortBy(fn (Enrollment $e) => $e->learner->full_name)
            ->values();

        $headings = ClassListColumns::headings($columns);
        $rows = ClassListColumns::rows($enrollments, $columns);

        $typeLabel = self::TYPES[$data['type']];
        $title = "{$classRoom->name} - {$typeLabel}";
        $statusLabel = $data['status'] === 'all' ? 'All statuses' : ucfirst($data['status']).' learners';
        $subtitle = "{$classRoom->branch->name} | {$year->name} | {$statusLabel} | {$enrollments->count()} learners";

        $filename = Str::slug("{$classRoom->name} {$classRoom->branch->name} {$year->name} {$typeLabel}");

        if ($data['format'] === 'xlsx') {
            return Excel::download(
                new ClassListExport(
                    $headings,
                    $rows,
                    $classRoom->name,
                    $request->boolean('include_title', true) ? $title : null,
                    $subtitle,
                ),
                "{$filename}.xlsx"
            );
        }

        return Pdf::loadView('exports.class-list', compact('title', 'subtitle', 'headings', 'rows'))
            ->setPaper('a4', count($columns) > 7 ? 'landscape' : 'portrait')
            ->download("{$filename}.pdf");
    }
}
