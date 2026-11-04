<?php

use App\Providers\Filament\AdminPanelProvider;
use App\Providers\PaymentServiceProvider;
use L5Swagger\L5SwaggerServiceProvider;

return [
    AdminPanelProvider::class,
    PaymentServiceProvider::class,
    L5SwaggerServiceProvider::class,
];
