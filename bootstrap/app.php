
<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {

        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        /*
        |--------------------------------------------------------------------------
        | Spatie Permission
        |--------------------------------------------------------------------------
        */

        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {

        // $exceptions->shouldRenderJsonWhen(
        //     fn(Request $request) => $request->is('api/*'),
        // );

        $exceptions->render(function (
            HttpExceptionInterface $exception,
            $request
        ) {
            $status = $exception->getStatusCode();

            $errores = [
                403,
                404,
                419,
                429,
                500,
                503,
            ];

            if (in_array($status, $errores)) {
                return Inertia::render(
                    "Errors/{$status}"
                )->toResponse($request)->setStatusCode($status);
            }
        });
    })->create();
