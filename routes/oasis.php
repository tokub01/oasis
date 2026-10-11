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
});
