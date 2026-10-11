<?php

use App\Providers\AppServiceProvider;
use App\Providers\ChatServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\GovernanceServiceProvider;
use App\Providers\ModerationServiceProvider;
use App\Providers\NetworkServiceProvider;

return [
    AppServiceProvider::class,
    ChatServiceProvider::class,
    ModerationServiceProvider::class,
    NetworkServiceProvider::class,
    GovernanceServiceProvider::class,
    FortifyServiceProvider::class,
];
