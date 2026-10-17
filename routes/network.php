<?php

use App\Http\Controllers\Network\PostImageController;
use App\Livewire\Network\Discover;
use App\Livewire\Network\Friends;
use App\Livewire\Network\InvitationLanding;
use App\Livewire\Network\Invitations;
use App\Livewire\Network\OasisClose;
use App\Livewire\Network\OasisForm;
use App\Livewire\Network\OasisIndex;
use App\Livewire\Network\OasisShow;
use App\Livewire\Network\OasisStatistics;
use App\Livewire\Network\PostCarry;
use App\Livewire\Network\PostEdit;
use App\Livewire\Network\PostShow;
use App\Livewire\Network\ProfileEdit;
use App\Livewire\Network\Statistics;
use Illuminate\Support\Facades\Route;

/** Full-page network components retain all existing GET URIs and names; mutations are component actions. */
Route::name('network.')->group(function () {
    Route::livewire('/tropfen/{post}', PostShow::class)->whereNumber('post')->name('posts.show');
    Route::get('/tropfen/{post}/bild/{image}', PostImageController::class)->whereNumber(['post', 'image'])->name('images.show');
    Route::livewire('/oasen/{oasis}', OasisShow::class)->name('oases.show');
    Route::livewire('/einladung/{token}', InvitationLanding::class)->name('invitations.show');

    Route::middleware(['auth', 'verified'])->group(function () {
        Route::livewire('/app/entdecken', Discover::class)->name('discover');
        Route::livewire('/app/freunde', Friends::class)->name('friends');
        Route::livewire('/tropfen/{post}/weitertragen', PostCarry::class)->whereNumber('post')->name('posts.carry');
        Route::livewire('/tropfen/{post}/bearbeiten', PostEdit::class)->whereNumber('post')->name('posts.edit');
        Route::livewire('/app/oasen', OasisIndex::class)->name('oases.index');
        Route::livewire('/app/oasen/neu', OasisForm::class)->name('oases.create');
        Route::livewire('/oasen/{oasis}/bearbeiten', OasisForm::class)->name('oases.edit');
        Route::livewire('/oasen/{oasis}/schliessen', OasisClose::class)->name('oases.close');
        Route::livewire('/oasen/{oasis}/statistik', OasisStatistics::class)->name('oases.stats');
        Route::livewire('/app/profil', ProfileEdit::class)->name('profile.edit');
        Route::livewire('/app/statistik', Statistics::class)->name('stats');
        Route::livewire('/app/einladen', Invitations::class)->name('invitations.index');
    });
});
