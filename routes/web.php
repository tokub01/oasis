<?php

use Illuminate\Support\Facades\Route;

require __DIR__.'/oasis.php';

Route::redirect('/login', '/anmelden')->name('login');

Route::group(['middleware' => 'auth'], function () {

});
