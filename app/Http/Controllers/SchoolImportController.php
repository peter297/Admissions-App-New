<?php

namespace App\Http\Controllers;

use App\Exports\LearnerImportTemplate;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\Learner;
use App\Support\ClassListImporter;
use App\Support\LearnerImportLookups;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;

/** One file for the whole school (or a whole branch): every row says which class it belongs to. */
class SchoolImportController extends Controller
{
    private const MAX_ROWS = 5000;
    private const MAX_ERROR_LINES = 40;

    public function index(): Response
    {
        return Inertia::render('import/index');
    }

    public function template()
    {
        return Excel::download(new LearnerImportTemplate(true), 'school-learner-import-template.xlsx');
    }

    public function store(Request $request, ClassListImporter $importer): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'extensions:xlsx,xls,csv', 'max:10240'],
        ]);

        // Thousands of rows take longer than the default 30 seconds.
        set_time_limit(300);

        $year = AcademicYear::where('is_current', true)->firstOrFail();
        $result = $importer->read($request->file('file'), new LearnerImportLookups($request->user()), self::MAX_ROWS);

        if ($result['fatal']) {
            throw ValidationException::withMessages(['file' => $result['fatal']]);
        }

        if ($result['errors']) {
            $total = count($result['errors']);
            $lines = array_slice($result['errors'], 0, self::MAX_ERROR_LINES);
            if ($total > self::MAX_ERROR_LINES) {
                $lines[] = '...and '.($total - self::MAX_ERROR_LINES).' more rows with errors.';
            }

            throw ValidationException::withMessages([
                'file' => "Nothing was imported ({$total} rows have problems). Fix these and upload again:\n".implode("\n", $lines),
            ]);
        }

        DB::transaction(function () use ($result, $year) {
            foreach ($result['records'] as $r) {
                $learner = Learner::create(Arr::only($r, ClassListImporter::LEARNER_FIELDS));

                Enrollment::create([
                    'learner_id' => $learner->id,
                    'academic_year_id' => $year->id,
                    'class_id' => $r['class_id'],
                    'branch_id' => $r['branch_id'],
                    'status' => $r['status'],
                    'takes_lunch' => $r['takes_lunch'],
                    'transport_route_id' => $r['transport_route_id'],
                    'bus_id' => $r['bus_id'],
                ]);
            }
        });

        $count = count($result['records']);
        $classes = collect($result['records'])->pluck('class_id')->unique()->count();

        return redirect()
            ->route('import.index')
            ->with('success', "{$count} learners imported into {$classes} classes for academic year {$year->name}.");
    }
}
