<?php

use App\Livewire\Admin\Dashboard;
use App\Livewire\Admin\Payments;
use App\Livewire\Admin\Votes;
use App\Livewire\Governance\Votes as MemberVotes;
use Illuminate\Support\Facades\Route;

/* Stage 6 (docs/oasis-admin.md): admin area behind staff 2FA + the admin role, and member votes. Admin pages are Livewire components. */
Route::prefix('admin')->name('admin.')->middleware(['auth', 'verified', 'staff', 'can:administrate'])->group(function () {
    Route::livewire('/', Dashboard::class)->name('dashboard');
    Route::livewire('/abstimmungen', Votes::class)->name('votes.index');
    Route::livewire('/zahlungen', Payments::class)->name('payments');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('/app/abstimmungen', MemberVotes::class)->name('app.votes');
});
