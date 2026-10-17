<?php

use App\Http\Controllers\Operations\HealthController;
use Illuminate\Support\Facades\Route;

require __DIR__.'/oasis.php';

// Operations: health check for uptime monitors (token-protected, docs/oasis-operations.md).
Route::get('/gesundheit', HealthController::class)->name('operations.health');

require __DIR__.'/app.php';

// Stage 4a: authorized staff moderation and public signed complaints.
require __DIR__.'/moderation.php';

// Stage 3: ciphertext-only chat APIs (web sessions, CSRF and verified email).
require __DIR__.'/chat.php';

// Stage 2: core network (Tropfen, profiles, oases, follows, statistics).
require __DIR__.'/network.php';

// Stage 1 & 5: Membership, Mollie Checkout, portal and cancellation.
require __DIR__.'/membership.php';

// Stage 6: admin statistics, transparency reports and member votes.
require __DIR__.'/admin.php';

// Stage 8: search and #Thema pages.
require __DIR__.'/search.php';

require __DIR__.'/notifications.php';

require __DIR__.'/safety.php';
