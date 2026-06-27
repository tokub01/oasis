<?php

use App\Livewire\Pages\LoginPage;
use Illuminate\Support\Facades\Route;
use App\Livewire\Pages\LandingPage;

Route::group(['middleware' => 'guest'], function () {
    Route::get('/', LandingPage::class)->name('landing');
    Route::get('/login', LoginPage::class)->name('login');
});

Route::group(['middleware' => 'auth'], function () {

});
