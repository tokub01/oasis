<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Oasis website
|--------------------------------------------------------------------------
|
| Public pages of get-oasis.com. Static pages use Route::view, forms use
| controllers. All route names are prefixed with "oasis.".
|
*/

Route::name('oasis.')->group(function () {

    // Pages
    Route::view('/', 'oasis.home')->name('home');
    Route::view('/funktionen', 'oasis.funktionen')->name('funktionen');
    Route::view('/preise', 'oasis.preise')->name('preise');
    Route::view('/transparenz', 'oasis.transparenz')->name('transparenz');
    Route::view('/manifest', 'oasis.manifest')->name('manifest');
    Route::view('/hilfe', 'oasis.hilfe')->name('hilfe');
    Route::view('/testen', 'oasis.testen')->name('testen');
    Route::view('/anmelden', 'oasis.anmelden')->name('anmelden');

    Route::get('/mitglied-werden', function () {
        abort_unless(config('oasis.checkout_enabled'), 404);

        return view('oasis.mitglied');
    })->name('mitglied');

    Route::get('/entdecken', function () {
        abort_unless(config('oasis.demo_pages'), 404);

        return view('oasis.entdecken');
    })->name('entdecken');

    Route::get('/profil/{handle}', function (string $handle) {
        abort_unless(config('oasis.demo_pages'), 404);

        return view('oasis.profil', ['handle' => $handle]);
    })->where('handle', '[A-Za-z0-9_]{1,30}')->name('profil');

    // Pages with forms
    Route::view('/gemeinsam-starten', 'oasis.gemeinsam')->name('gemeinsam');
    Route::view('/gruendung', 'oasis.gruendung')->name('gruendung');
    Route::view('/vertraege-kuendigen', 'oasis.kuendigen')->name('kuendigen');
    Route::view('/vertrag-widerrufen', 'oasis.widerrufen')->name('widerrufen');
    Route::view('/inhalte-melden', 'oasis.legal.melden')->name('melden');

    // Legal pages
    Route::view('/impressum', 'oasis.legal.impressum')->name('impressum');
    Route::view('/datenschutz', 'oasis.legal.datenschutz')->name('datenschutz');
    Route::view('/agb', 'oasis.legal.agb')->name('agb');
    Route::view('/widerrufsbelehrung', 'oasis.legal.widerrufsbelehrung')->name('widerrufsbelehrung');
    Route::view('/community-regeln', 'oasis.legal.regeln')->name('regeln');

    // Form submissions (POST) and confirmation pages

    // Oasis forms: public, CSRF protected by the web middleware. Each form has its own throttle key
    // (third parameter), so using one form can never block another, e.g. a cancellation (§ 312k BGB).
    Route::post('/gruendung', [\App\Http\Controllers\Oasis\WaitlistController::class, 'store'])
        ->middleware('throttle:6,1,oasis-gruendung')->name('gruendung.store');
    Route::post('/vertraege-kuendigen', [\App\Http\Controllers\Oasis\CancellationController::class, 'store'])
        ->middleware('throttle:6,1,oasis-kuendigen')->name('kuendigen.store');
    Route::post('/vertrag-widerrufen', [\App\Http\Controllers\Oasis\WithdrawalController::class, 'store'])
        ->middleware('throttle:6,1,oasis-widerrufen')->name('widerrufen.store');
    Route::post('/inhalte-melden', [\App\Http\Controllers\Oasis\ContentReportController::class, 'store'])
        ->middleware('throttle:6,1,oasis-melden')->name('melden.store');
    Route::post('/gemeinsam-starten', [\App\Http\Controllers\Oasis\OrganisationController::class, 'store'])
        ->middleware('throttle:6,1,oasis-gemeinsam')->name('gemeinsam.store');

    Route::get('/bestaetigung/gruendung', function (): \Illuminate\View\View {
        abort_unless(session('oasis.submitted.gruendung'), 404);

        return view('oasis.bestaetigung.gruendung');
    })->name('bestaetigung.gruendung');

    Route::get('/bestaetigung/kuendigen', function (): \Illuminate\View\View {
        abort_unless(session('oasis.submitted.kuendigen'), 404);

        return view('oasis.bestaetigung.kuendigen');
    })->name('bestaetigung.kuendigen');

    Route::get('/bestaetigung/widerrufen', function (): \Illuminate\View\View {
        abort_unless(session('oasis.submitted.widerrufen'), 404);

        return view('oasis.bestaetigung.widerrufen');
    })->name('bestaetigung.widerrufen');

    Route::get('/bestaetigung/melden', function (): \Illuminate\View\View {
        abort_unless(session('oasis.submitted.melden'), 404);

        return view('oasis.bestaetigung.melden');
    })->name('bestaetigung.melden');

    Route::get('/bestaetigung/gemeinsam', function (): \Illuminate\View\View {
        abort_unless(session('oasis.submitted.gemeinsam'), 404);

        return view('oasis.bestaetigung.gemeinsam');
    })->name('bestaetigung.gemeinsam');

    Route::get('/startliste/bestaetigen/{token}', [\App\Http\Controllers\Oasis\WaitlistController::class, 'confirm'])
        ->middleware(['signed', 'throttle:20,1,oasis-waitlist-confirm'])->name('waitlist.confirm');
    Route::get('/startliste/abmelden/{token}', [\App\Http\Controllers\Oasis\WaitlistController::class, 'unsubscribe'])
        ->middleware(['signed', 'throttle:20,1,oasis-waitlist-unsubscribe'])->name('waitlist.unsubscribe');
    Route::post('/startliste/abmelden/{token}', [\App\Http\Controllers\Oasis\WaitlistController::class, 'destroy'])
        ->middleware(['signed', 'throttle:6,1,oasis-waitlist-destroy'])->name('waitlist.destroy');
    // End Oasis forms.

});
