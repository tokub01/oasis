<?php

use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\ModerationServiceProvider;

return [
    AppServiceProvider::class,
    ModerationServiceProvider::class,
    FortifyServiceProvider::class,
];
