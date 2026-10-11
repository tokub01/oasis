<?php

use App\Providers\AppServiceProvider;
use App\Providers\ChatServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\ModerationServiceProvider;

return [
    AppServiceProvider::class,
    ChatServiceProvider::class,
    ModerationServiceProvider::class,
    FortifyServiceProvider::class,
];
