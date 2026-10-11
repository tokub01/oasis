<?php

require __DIR__.'/oasis.php';

require __DIR__.'/app.php';

// Stage 4a: authorized staff moderation and public signed complaints.
require __DIR__.'/moderation.php';

// Stage 3: ciphertext-only chat APIs (web sessions, CSRF and verified email).
require __DIR__.'/chat.php';

// Stage 2: core network (Tropfen, profiles, oases, follows, statistics).
require __DIR__.'/network.php';

// Stage 1 & 5: Membership, Stripe Checkout, portal and cancellation.
require __DIR__.'/membership.php';

// Stage 6: admin statistics, transparency reports and member votes.
require __DIR__.'/admin.php';
