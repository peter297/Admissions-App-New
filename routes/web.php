<?php

use App\Http\Controllers\ClassRoomController;
use App\Http\Controllers\LearnerController;
use App\Http\Controllers\TransportController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ClassExportController;

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


