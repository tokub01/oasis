<?php

use App\Livewire\Search\Index;
use App\Livewire\Search\Tag;
use Illuminate\Support\Facades\Route;

/** Live search and public topic pages retain their original URLs and initial-request rate limit. */
Route::name('search.')->group(function () {
    Route::livewire('/themen/{tag}', Tag::class)->middleware('throttle:search')->name('tags.show');
    Route::livewire('/app/suche', Index::class)->middleware(['auth', 'verified', 'throttle:search'])->name('index');
});
