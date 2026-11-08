<?php

use App\Http\Controllers\AcademicYearController;
use App\Http\Controllers\ClassExportController;
use App\Http\Controllers\ClassImportController;
use App\Http\Controllers\ClassRoomController;
use App\Http\Controllers\LearnerController;
use App\Http\Controllers\TransportController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AlumniController;
use App\Http\Controllers\AlumniExportController;
use App\Http\Controllers\AdmissionController;
use App\Http\Controllers\AdmissionsExportController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LearnerSearchController;
use App\Http\Controllers\SchoolImportController;
use App\Http\Controllers\Settings\ClassSettingsController;
use App\Http\Controllers\Settings\RoleController;
use App\Http\Controllers\Settings\UserController;
use App\Http\Controllers\HistoryController;
use App\Http\Controllers\ReportController;
use Inertia\Inertia;

// Route::inertia('/', 'welcome')->name('home');


Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }
    return redirect()->route('login');
});


Route::middleware(['auth', 'verified'])->group(function () {
           Route::get('dashboard', DashboardController::class)
    ->middleware(['auth', 'verified', 'permission:dashboard.view'])
    ->name('dashboard');

    Route::get('classes', [ClassRoomController::class, 'index'])
        ->middleware('permission:classes.view')
        ->name('classes.index');

    Route::get('classes/{classRoom}', [ClassRoomController::class, 'show'])
        ->middleware('permission:classes.view')
        ->name('classes.show');

    Route::post('classes/{classRoom}/move', [ClassRoomController::class, 'move'])
        ->middleware('permission:learners.move')
        ->name('classes.move');

    Route::get('classes/{classRoom}/learners/create', [LearnerController::class, 'create'])
        ->middleware('permission:learners.create')->name('learners.create');
    Route::post('classes/{classRoom}/learners', [LearnerController::class, 'store'])
        ->middleware('permission:learners.create')->name('learners.store');
    Route::get('enrollments/{enrollment}/edit', [LearnerController::class, 'edit'])
        ->middleware('permission:learners.edit')->name('learners.edit');
    Route::put('enrollments/{enrollment}', [LearnerController::class, 'update'])
        ->middleware('permission:learners.edit')->name('learners.update');

    Route::middleware('permission:transport.view')->group(function () {
        Route::get('transport', [TransportController::class, 'index'])->name('transport.index');
    });

    Route::get('classes/{classRoom}/export', ClassExportController::class)
        ->middleware('permission:learners.export')
        ->name('classes.export');

    Route::get('learners/import-template', [ClassImportController::class, 'template'])
        ->middleware('permission:learners.import')->name('learners.import-template');
    Route::post('classes/{classRoom}/import', [ClassImportController::class, 'store'])
        ->middleware('permission:learners.import')->name('classes.import');

    Route::middleware('permission:academic-years.manage')->group(function () {
        Route::get('academic-years', [AcademicYearController::class, 'index'])->name('academic-years.index');
        Route::post('academic-years', [AcademicYearController::class, 'store']);
        Route::post('academic-years/rollover', [AcademicYearController::class, 'rollover']);
        Route::post('academic-years/terms/{term}/current', [AcademicYearController::class, 'setTerm']);
        Route::post('academic-years/{academicYear}/current', [AcademicYearController::class, 'setCurrent']);
    });

    Route::get('admissions', [AdmissionController::class, 'index'])
    ->middleware('permission:admissions.view')->name('admissions.index');
Route::get('admissions/export', AdmissionsExportController::class)
    ->middleware('permission:admissions.export')->name('admissions.export');

Route::middleware('permission:admissions.manage')->group(function () {
    Route::get('admissions/create', [AdmissionController::class, 'create'])->name('admissions.create');
    Route::post('admissions', [AdmissionController::class, 'store'])->name('admissions.store');
    Route::get('admissions/{applicant}/edit', [AdmissionController::class, 'edit'])->name('admissions.edit');
    Route::put('admissions/{applicant}', [AdmissionController::class, 'update'])->name('admissions.update');
    Route::delete('admissions/{applicant}', [AdmissionController::class, 'destroy'])->name('admissions.destroy');
});

Route::post('admissions/{applicant}/promote', [AdmissionController::class, 'promote'])
    ->middleware('permission:admissions.promote')->name('admissions.promote');

Route::middleware('permission:alumni.view')->group(function () {
    Route::get('alumni', [AlumniController::class, 'index'])->name('alumni.index');
    Route::get('alumni/{academicYear}/{branch}', [AlumniController::class, 'show'])->name('alumni.show');
    Route::get('alumni/{academicYear}/{branch}/export', AlumniExportController::class)
        ->middleware('permission:learners.export')->name('alumni.export');


});



Route::get('admissions', [AdmissionController::class, 'index'])->name('admissions.index');
Route::get('admissions/{admission}', [AdmissionController::class, 'show'])->name('admissions.show');
Route::post('admissions/{admission}/accept', [AdmissionController::class, 'accept'])
    ->middleware('permission:admissions.accept')->name('admissions.accept');


//  Route::get('php-check', fn () => response()->json([
//     'php_ini_file' => php_ini_loaded_file(),
//     'upload_tmp_dir' => ini_get('upload_tmp_dir'),
//     'system_temp_dir' => sys_get_temp_dir(),
//     'system_temp_writable' => is_writable(sys_get_temp_dir()),
//     'file_uploads' => ini_get('file_uploads'),
//     'upload_max_filesize' => ini_get('upload_max_filesize'),
//     'post_max_size' => ini_get('post_max_size'),
// ]));

Route::get('learners', [LearnerSearchController::class, 'index'])
    ->middleware('permission:learners.view')->name('learners.search');

    Route::middleware('permission:learners.import')->group(function () {
    Route::get('import', [SchoolImportController::class, 'index'])->name('import.index');
    Route::get('import/template', [SchoolImportController::class, 'template'])->name('import.template');
    Route::post('import', [SchoolImportController::class, 'store'])->name('import.store');
});

// Route::get('settings', fn () => redirect(
//     auth()->user()->hasRole('Super Admin') ? '/settings/roles' : '/settings/classes'
// ))->middleware('permission:settings.manage');

//  Roles and users: Super Admin ONLY, because they can grant any permission to anyone.
Route::middleware('role:Super Admin')->prefix('settings')->group(function () {
    Route::get('roles', [RoleController::class, 'index']);
    Route::post('roles', [RoleController::class, 'store']);
    Route::put('roles/{role}', [RoleController::class, 'update']);
    Route::delete('roles/{role}', [RoleController::class, 'destroy']);

    Route::get('users', [UserController::class, 'index']);
    Route::post('users', [UserController::class, 'store']);
    Route::put('users/{user}', [UserController::class, 'update']);
    Route::delete('users/{user}', [UserController::class, 'destroy']);
});

// Classes and streams: anyone with the settings.manage permission.
Route::middleware('permission:settings.manage')->prefix('settings')->group(function () {
    Route::get('classes', [ClassSettingsController::class, 'index']);
    Route::post('streams', [ClassSettingsController::class, 'storeStream']);
    Route::put('streams/{stream}', [ClassSettingsController::class, 'updateStream']);
    Route::delete('streams/{stream}', [ClassSettingsController::class, 'destroyStream']);
    Route::post('classes', [ClassSettingsController::class, 'storeClass']);
    Route::delete('classes/{classRoom}', [ClassSettingsController::class, 'destroyClass']);
    Route::put('branches/{branch}/final-grade', [ClassSettingsController::class, 'updateFinalGrade']);
    Route::put('branches/{branch}/first-grade', [ClassSettingsController::class, 'updateFirstGrade']);
    Route::post('branches/{branch}/fill-classes', [ClassSettingsController::class, 'fillClasses']);
});

Route::middleware('permission:history.view')->group(function () {
    Route::get('history', [HistoryController::class, 'index'])->name('history.index');
    Route::get('history/export', [HistoryController::class, 'export'])->name('history.export');
});

Route::middleware('permission:reports.view')->group(function () {
    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('reports/learners/pdf', [ReportController::class, 'pdf'])->name('reports.learners.pdf');
});

});

Route::middleware('permission:transport.manage')->group(function () {
    Route::post('transport/routes', [TransportController::class, 'storeRoute']);
    Route::put('transport/routes/{transportRoute}', [TransportController::class, 'updateRoute']);
    Route::delete('transport/routes/{transportRoute}', [TransportController::class, 'destroyRoute']);
    Route::post('transport/buses', [TransportController::class, 'storeBus']);
    Route::put('transport/buses/{bus}', [TransportController::class, 'updateBus']);
    Route::delete('transport/buses/{bus}', [TransportController::class, 'destroyBus']);
});

require __DIR__ . '/settings.php';
