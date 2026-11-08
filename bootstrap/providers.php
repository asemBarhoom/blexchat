<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\InstagramServiceProvider;
use App\Providers\PaymentServiceProvider;
use L5Swagger\L5SwaggerServiceProvider;

return [
    AppServiceProvider::class,
    AdminPanelProvider::class,
    InstagramServiceProvider::class,
    PaymentServiceProvider::class,
    L5SwaggerServiceProvider::class,
];
