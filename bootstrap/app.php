<?php

use App\Http\Middleware\ChangeLang;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Console\Scheduling\Schedule;

return Application::configure(basePath: dirname(__DIR__))

    ->withSchedule(function (Schedule $schedule) {
        $schedule->command('lessons:generate')->everyMinute();
    })

    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        api: __DIR__.'/../routes/api.php',
        health: '/up',
    )

    ->withMiddleware(function (Middleware $middleware) {

        $middleware->appendToGroup('api', ChangeLang::class);
 $middleware->alias([
        'role' => \App\Http\Middleware\RoleMiddleware::class,
    ]);
        $middleware->web(append: [
            \App\Http\Middleware\ChangeLang::class,
        ]);

    })

->withExceptions(function (Exceptions $exceptions): void {

    $exceptions->render(function (
        \Illuminate\Auth\AuthenticationException $e,
        \Illuminate\Http\Request $request
    ) {

        return response()->json([
            'status' => false,
            'message' => __('messages.unauthorized'),
            'data' => null
        ], 401);

    });

})
    ->create();