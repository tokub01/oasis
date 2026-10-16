<?php

use App\Livewire\Moderation\CaseDetail;
use App\Livewire\Moderation\ComplaintForm;
use App\Livewire\Moderation\ComplaintReview;
use App\Livewire\Moderation\Overview;
use App\Livewire\Moderation\Roles;
use Illuminate\Support\Facades\Route;

/*
 * Staff workflow lives behind verified authentication and confirmed 2FA. All pages are Livewire components; their
 * actions (assign, decide, review, change a role) run as Livewire requests, where the persistent `staff` middleware
 * and the policy checks inside each component action apply again.
 */
Route::prefix('moderation')->name('moderation.')->middleware(['auth', 'verified', 'staff'])->group(function () {
    Route::livewire('/', Overview::class)->name('index');
    Route::livewire('/cases/{case}', CaseDetail::class)->name('cases.show');
    Route::livewire('/complaints/{complaint}', ComplaintReview::class)->name('complaints.show');
    Route::livewire('/roles', Roles::class)->name('roles.index');
});

/*
 * Complaints against a decision: the signed link from the decision mail works without an account (the recipient
 * is derived server-side from the link), the account route is for the signed-in affected member.
 */
Route::livewire('/moderation/complaint/{decision}', ComplaintForm::class)->middleware('signed')->name('moderation.complaint.create');
Route::livewire('/app/moderation/complaint/{decision}', ComplaintForm::class)->middleware('auth')
    ->defaults('via', 'account')->name('moderation.complaint.account');
