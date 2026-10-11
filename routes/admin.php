<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\TransparencyReportController;
use App\Http\Controllers\Admin\VoteController;
use App\Http\Controllers\Governance\BallotController;
use Illuminate\Support\Facades\Route;

/* Stage 6 (docs/oasis-admin.md): admin area behind staff 2FA + the admin role, and member votes. */
Route::prefix('admin')->name('admin.')->middleware(['auth', 'verified', 'staff', 'can:administrate'])->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');
    Route::get('/transparenz', [TransparencyReportController::class, 'index'])->name('transparency.index');
    Route::post('/transparenz', [TransparencyReportController::class, 'store'])->name('transparency.store');
    Route::get('/abstimmungen', [VoteController::class, 'index'])->name('votes.index');
    Route::post('/abstimmungen', [VoteController::class, 'store'])->name('votes.store');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/app/abstimmungen', [BallotController::class, 'index'])->name('app.votes');
    Route::post('/app/abstimmungen/{vote}', [BallotController::class, 'store'])->whereNumber('vote')->name('app.votes.store');
});
