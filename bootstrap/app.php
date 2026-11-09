<?php

use App\Http\Middleware\SetApiLocale;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        health: '/up',
        apiPrefix: 'api',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->appendToGroup('api', SetApiLocale::class);
        $middleware->redirectGuestsTo(
            fn (Request $request) => $request->is('api/*')
                ? null
                : route('filament.admin.auth.login'),
        );
    })
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('instagram:refresh-tokens')->daily()->onOneServer();
        // Empties the Trash of automations past Automation::TRASH_DAYS
        $schedule->command('model:prune')->daily()->onOneServer();
        $schedule->command('conversations:send-reminders')->everyMinute()->onOneServer()->withoutOverlapping();
        $schedule->command('webhooks:purge --days=30')->daily()->onOneServer();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
