<?php

use App\Http\Controllers\Oasis\ConfirmationController;
use App\Http\Controllers\Oasis\PageController;
use App\Http\Controllers\Oasis\WaitlistController;
use App\Livewire\Membership\Checkout;
use App\Livewire\Network\ProfileShow;
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
    Route::view('/manifest', 'oasis.manifest')->name('manifest');
    Route::view('/sicherheit', 'oasis.sicherheit')->name('sicherheit');
    Route::view('/hilfe', 'oasis.hilfe')->name('hilfe');

    /*
     * Pages behind feature flags (oasis.checkout_enabled, oasis.demo_pages).
     */
    Route::livewire('/mitglied-werden', Checkout::class)->name('mitglied');
    Route::get('/entdecken', [PageController::class, 'discover'])->name('entdecken');
    // Real member profiles first; sample profiles only for handles no member has (App\Livewire\Network\ProfileShow).
    Route::livewire('/profil/{handle}', ProfileShow::class)
        ->where('handle', '[A-Za-z0-9_]{1,30}')
        ->name('profil');

    /*
     * Legal pages: one URL per document.
     */
    Route::view('/impressum', 'oasis.legal.impressum')->name('impressum');
    Route::view('/datenschutz', 'oasis.legal.datenschutz')->name('datenschutz');
    Route::view('/agb', 'oasis.legal.agb')->name('agb');
    Route::view('/widerrufsbelehrung', 'oasis.legal.widerrufsbelehrung')->name('widerrufsbelehrung');
    Route::view('/community-regeln', 'oasis.legal.regeln')->name('regeln');

    /*
     * Pages with forms. Each form is a Livewire component in app/Livewire/Oasis (submitted as a Livewire request) with
     * its own submission budget per IP (SubmitsPublicForm), so a few waitlist attempts can never block a cancellation,
     * which § 312k BGB must always allow. "Inhalte melden" is the DSA Art. 16 notice form.
     */
    Route::view('/gruendung', 'oasis.gruendung')->name('gruendung');
    Route::view('/vertraege-kuendigen', 'oasis.kuendigen')->name('kuendigen');
    Route::view('/vertrag-widerrufen', 'oasis.widerrufen')->name('widerrufen');
    Route::view('/inhalte-melden', 'oasis.legal.melden')->name('melden');
    Route::view('/gemeinsam-starten', 'oasis.gemeinsam')->name('gemeinsam');

    /*
     * Confirmation pages after a submission: /bestaetigung/<form>, named oasis.bestaetigung.<form>.
     * They are 404 unless the form component just flashed `oasis.submitted.<form>`.
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
