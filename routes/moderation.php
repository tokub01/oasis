<?php

use App\Http\Controllers\Moderation\CaseController;
use App\Http\Controllers\Moderation\ComplaintController;
use App\Http\Controllers\Moderation\RoleController;
use Illuminate\Support\Facades\Route;

/* Staff workflow lives behind verified authentication and confirmed 2FA. */
Route::prefix('moderation')->name('moderation.')->middleware(['auth', 'verified', 'staff'])->group(function () {
    Route::get('/', [CaseController::class, 'index'])->name('index');
    Route::get('/cases/{case}', [CaseController::class, 'show'])->name('cases.show');
    Route::post('/cases/{case}/assign', [CaseController::class, 'assign'])->name('cases.assign');
    Route::post('/cases/{case}/decide', [CaseController::class, 'decide'])->name('cases.decide');
    Route::get('/complaints/{complaint}', [ComplaintController::class, 'show'])->name('complaints.show');
    Route::post('/complaints/{complaint}/decide', [ComplaintController::class, 'decide'])->name('complaints.decide');
    Route::get('/roles', [RoleController::class, 'index'])->name('roles.index');
    Route::put('/roles/{user}', [RoleController::class, 'update'])->name('roles.update');
});

/* Public signed capability links work without an account; recipient identity is derived server-side. */
Route::prefix('moderation/complaint')->name('moderation.complaint.')->middleware('signed')->group(function () {
    Route::get('/{decision}', [ComplaintController::class, 'create'])->name('create');
    Route::post('/{decision}', [ComplaintController::class, 'store'])->middleware('throttle:moderation-complaints')->name('store');
});
Route::prefix('app/moderation/complaint')->name('moderation.complaint.account')->middleware('auth')->group(function () {
    Route::get('/{decision}', [ComplaintController::class, 'create']);
    Route::post('/{decision}', [ComplaintController::class, 'store'])->middleware('throttle:moderation-complaints')->name('.store');
});
