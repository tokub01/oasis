<?php

use App\Http\Controllers\Search\SearchController;
use Illuminate\Support\Facades\Route;

/* Stage 8 search (docs/oasis-search.md): member search and public #Thema pages. */
Route::name('search.')->group(function () {
    Route::get('/themen/{tag}', [SearchController::class, 'tag'])->middleware('throttle:search')->name('tags.show');
    Route::get('/app/suche', [SearchController::class, 'index'])->middleware(['auth', 'verified', 'throttle:search'])->name('index');
});
