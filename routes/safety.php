<?php

use App\Http\Controllers\Safety\BlockController;
use App\Http\Controllers\Safety\BookmarkController;
use App\Http\Controllers\Safety\MuteController;
use App\Http\Controllers\Safety\SafetyController;
use Illuminate\Support\Facades\Route;

// Stage 8: Safety controls (blocking, muting, bookmarks) for verified members.
Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('/app/sicherheit', [SafetyController::class, 'index'])->name('safety.index');
    Route::post('/app/sicherheit/block/{user}', [BlockController::class, 'store'])->name('safety.block.store');
    Route::delete('/app/sicherheit/block/{user}', [BlockController::class, 'destroy'])->name('safety.block.destroy');

    Route::post('/app/sicherheit/mute/{user}', [MuteController::class, 'store'])->name('safety.mute.store');
    Route::delete('/app/sicherheit/mute/{user}', [MuteController::class, 'destroy'])->name('safety.mute.destroy');

    Route::get('/app/gemerkt', [BookmarkController::class, 'index'])->name('safety.bookmarks');
    Route::post('/app/gemerkt/{post}', [BookmarkController::class, 'store'])->name('safety.bookmarks.store');
    Route::delete('/app/gemerkt/{post}', [BookmarkController::class, 'destroy'])->name('safety.bookmarks.destroy');
});
