<?php

namespace App\Http\Middleware;

// use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Middleware;
// use App\Models\Producto;
// use App\Models\Lote;
use App\Models\Notificacion;
use App\Services\NotificacionService;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),

            'auth' => [
                'user' => $request->user(),
                'roles' => $request->user()?->getRoleNames() ?? [],
                'permissions' => $request->user()
                    ? $request->user()
                    ->getAllPermissions()
                    ->pluck('name')
                    ->values()
                    ->toArray()
                    : [],
            ],

            'notificaciones' => function () {
                app(NotificacionService::class)->sincronizar();

                $items = Notificacion::where('solucionada', false)
                    ->orderByDesc('created_at')
                    ->limit(10)
                    ->get()
                    ->map(function ($notificacion) {

                        $icon = 'pi pi-bell';
                        $severity = 'info';

                        if (str_contains($notificacion->tipo, 'stock')) {
                            $icon = 'pi pi-exclamation-triangle';
                            $severity = 'danger';
                        }

                        if ($notificacion->tipo === 'vencimiento') {
                            $icon = 'pi pi-clock';
                            $severity = 'warning';
                        }

                        if ($notificacion->tipo === 'vencido') {
                            $icon = 'pi pi-times-circle';
                            $severity = 'danger';
                        }

                        return [
                            'id' => $notificacion->id,
                            'tipo' => $notificacion->tipo,
                            'titulo' => $notificacion->titulo,
                            'mensaje' => $notificacion->mensaje,
                            'icon' => $icon,
                            'severity' => $severity,
                            'leida' => $notificacion->leida,
                            'solucionada' => $notificacion->solucionada,
                            'created_at' => $notificacion->created_at,
                        ];
                    });

                return [
                    'items' => $items,
                    'total' => $items->count(),
                ];
            },
        ];
    }
}
