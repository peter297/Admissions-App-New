<?php

namespace App\Http\Controllers;

use App\Exports\LearnerImportTemplate;
use App\Models\AcademicYear;
use App\Models\ClassRoom;
use App\Models\Enrollment;
use App\Models\Learner;
use App\Support\ClassListImporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

class ClassImportController extends Controller
{
    private const MAX_ERROR_LINES = 25;

    public function template()
    {
        return Excel::download(new LearnerImportTemplate(false), 'learner-import-template.xlsx');
    }

    public function store(Request $request, ClassRoom $classRoom, ClassListImporter $importer): RedirectResponse
    {
        abort_unless(
            ClassRoom::visibleTo($request->user())->whereKey($classRoom->id)->exists(),
            403
        );

        $request->validate([
            'file' => ['required', 'file', 'extensions:xlsx,xls,csv', 'max:5120'],
        ]);

        $year = AcademicYear::where('is_current', true)->firstOrFail();
        $result = $importer->read($request->file('file'));

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
                'file' => "Nothing was imported. Fix these rows and upload again:\n".implode("\n", $lines),
            ]);
        }

        DB::transaction(function () use ($result, $classRoom, $year) {
            foreach ($result['records'] as $r) {
                $learner = Learner::create(Arr::only($r, ClassListImporter::LEARNER_FIELDS));

                Enrollment::create([
                    'learner_id' => $learner->id,
                    'academic_year_id' => $year->id,
                    'class_id' => $classRoom->id,
                    'branch_id' => $classRoom->branch_id,
                    'status' => 'active',
                    'takes_lunch' => $r['takes_lunch'],
                ]);
            }
        });

        $count = count($result['records']);

        return redirect()
            ->route('classes.show', $classRoom)
            ->with('success', "{$count} learner".($count === 1 ? '' : 's')." imported into {$classRoom->name}.");
    }
}
