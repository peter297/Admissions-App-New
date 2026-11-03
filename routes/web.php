<?php

use App\Http\Controllers\ClassRoomController;
use App\Http\Controllers\LearnerController;
use App\Http\Controllers\TransportController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ClassExportController;
use App\Http\Controllers\ClassImportController;
use App\Http\Controllers\AcademicYearController;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');

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


