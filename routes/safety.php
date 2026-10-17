<?php

use App\Livewire\Safety\Bookmarks;
use App\Livewire\Safety\QuickReport;
use App\Livewire\Safety\SafetyOverview;
use Illuminate\Support\Facades\Route;

// Stage 8 & 12: Safety controls (blocked/muted overview and private bookmarks) as Livewire full-page components.
Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::livewire('/app/sicherheit', SafetyOverview::class)->name('safety.index');
    Route::livewire('/app/gemerkt', Bookmarks::class)->name('safety.bookmarks');
    // Reporting in a few taps for members (guests use the formal form oasis.melden).
    Route::livewire('/app/melden', QuickReport::class)->name('app.report');
});
