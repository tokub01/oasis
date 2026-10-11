<?php

use App\Http\Controllers\Network\ProfileController;
use App\Http\Controllers\Oasis\CancellationController;
use App\Http\Controllers\Oasis\ConfirmationController;
use App\Http\Controllers\Oasis\ContentReportController;
use App\Http\Controllers\Oasis\OrganisationController;
use App\Http\Controllers\Oasis\PageController;
use App\Http\Controllers\Oasis\WaitlistController;
use App\Http\Controllers\Oasis\WithdrawalController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Oasis website (get-oasis.com)
|--------------------------------------------------------------------------
|
| All public pages of the Oasis website. Included from routes/web.php, so every
| route runs in the "web" middleware group (session, CSRF). Route names are
| prefixed with "oasis." to avoid clashes with the future app.
|
| Overview and conventions: docs/oasis-website.md
|
*/

Route::name('oasis.')->group(function () {

    /*
     * Static pages: no logic, values come from config/oasis.php inside the views.
     */
    Route::view('/', 'oasis.home')->name('home');
    Route::view('/funktionen', 'oasis.funktionen')->name('funktionen');
    Route::view('/preise', 'oasis.preise')->name('preise');
    Route::get('/transparenz', [PageController::class, 'transparency'])->name('transparenz');
    Route::view('/manifest', 'oasis.manifest')->name('manifest');
    Route::view('/hilfe', 'oasis.hilfe')->name('hilfe');

    /*
     * Pages behind feature flags (oasis.checkout_enabled, oasis.demo_pages).
     */
    Route::get('/mitglied-werden', [PageController::class, 'membership'])->name('mitglied');
    Route::get('/entdecken', [PageController::class, 'discover'])->name('entdecken');
    // Real member profiles first; sample profiles only for handles no member has (ProfileController::show).
    Route::get('/profil/{handle}', [ProfileController::class, 'show'])
        ->where('handle', '[A-Za-z0-9_]{1,30}')
        ->name('profil');

    /*
     * Legal pages: one URL per document. "Inhalte melden" is the DSA Art. 16 notice form.
     */
    Route::view('/impressum', 'oasis.legal.impressum')->name('impressum');
    Route::view('/datenschutz', 'oasis.legal.datenschutz')->name('datenschutz');
    Route::view('/agb', 'oasis.legal.agb')->name('agb');
    Route::view('/widerrufsbelehrung', 'oasis.legal.widerrufsbelehrung')->name('widerrufsbelehrung');
    Route::view('/community-regeln', 'oasis.legal.regeln')->name('regeln');

    /*
     * Pages with forms (GET shows the form, POST stores it).
     *
     * Each POST route has its own throttle key (third throttle parameter). Without it all forms would share one
     * budget per IP, and a few waitlist attempts could block a cancellation, which § 312k BGB must always allow.
     */
    Route::view('/gruendung', 'oasis.gruendung')->name('gruendung');
    Route::post('/gruendung', [WaitlistController::class, 'store'])
        ->middleware('throttle:6,1,oasis-gruendung')->name('gruendung.store');

    Route::view('/vertraege-kuendigen', 'oasis.kuendigen')->name('kuendigen');
    Route::post('/vertraege-kuendigen', [CancellationController::class, 'store'])
        ->middleware('throttle:6,1,oasis-kuendigen')->name('kuendigen.store');

    Route::view('/vertrag-widerrufen', 'oasis.widerrufen')->name('widerrufen');
    Route::post('/vertrag-widerrufen', [WithdrawalController::class, 'store'])
        ->middleware('throttle:6,1,oasis-widerrufen')->name('widerrufen.store');

    Route::view('/inhalte-melden', 'oasis.legal.melden')->name('melden');
    Route::post('/inhalte-melden', [ContentReportController::class, 'store'])
        ->middleware('throttle:6,1,oasis-melden')->name('melden.store');

    Route::view('/gemeinsam-starten', 'oasis.gemeinsam')->name('gemeinsam');
    Route::post('/gemeinsam-starten', [OrganisationController::class, 'store'])
        ->middleware('throttle:6,1,oasis-gemeinsam')->name('gemeinsam.store');

    /*
     * Confirmation pages after a submission: /bestaetigung/<form>, named oasis.bestaetigung.<form>.
     * They are 404 unless the form controller just flashed `oasis.submitted.<form>`.
     */
    foreach (ConfirmationController::FORMS as $form) {
        Route::get('/bestaetigung/'.$form, ConfirmationController::class)
            ->defaults('form', $form)
            ->name('bestaetigung.'.$form);
    }

    /*
     * Waitlist double opt-in. The links in the mail are signed URLs; unsubscribing needs a second step (GET shows
     * a confirm button, POST deletes), because mail scanners open GET links automatically.
     */
    Route::get('/startliste/bestaetigen/{token}', [WaitlistController::class, 'confirm'])
        ->middleware(['signed', 'throttle:20,1,oasis-waitlist-confirm'])->name('waitlist.confirm');
    Route::get('/startliste/abmelden/{token}', [WaitlistController::class, 'unsubscribe'])
        ->middleware(['signed', 'throttle:20,1,oasis-waitlist-unsubscribe'])->name('waitlist.unsubscribe');
    Route::post('/startliste/abmelden/{token}', [WaitlistController::class, 'destroy'])
        ->middleware(['signed', 'throttle:6,1,oasis-waitlist-destroy'])->name('waitlist.destroy');
});
