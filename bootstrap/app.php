
<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

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

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson()
        );

        $exceptions->respond(function (Response $response, \Throwable $exception, Request $request) {
            $status = $response->getStatusCode();
            $message = match ($status) {
                419 => 'La sesión o el formulario venció. Actualiza la página e inténtalo nuevamente.',
                429 => 'Hay demasiadas solicitudes. Espera unos segundos e inténtalo nuevamente.',
                503 => 'El sistema no está disponible en este momento. Intenta nuevamente en unos minutos.',
                default => $status >= 500
                    ? 'Ocurrió un error al procesar la solicitud. Actualiza la página si el problema continúa.'
                    : null,
            };

            if ($message !== null) {
                $headers = ['Cache-Control' => 'no-store'];
                if ($response->headers->has('Retry-After')) {
                    $headers['Retry-After'] = $response->headers->get('Retry-After');
                }

                return $request->expectsJson() || $request->is('api/*') || $request->headers->has('X-Inertia')
                    ? response()->json(['message' => $message], $status, $headers)
                    : response()->view('errors.solicitud', compact('status', 'message'), $status, $headers);
            }

            if (in_array($status, [403, 404], true)
                && !$request->expectsJson() && !$request->is('api/*')) {
                return Inertia::render("Errors/{$status}")->toResponse($request)->setStatusCode($status);
            }

            return $response;
        });
    })->create();
