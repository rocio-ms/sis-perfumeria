
<?php

use App\Http\Controllers\AjusteController;
use App\Http\Controllers\AlertaController;
use App\Http\Controllers\CajaController;
use App\Http\Controllers\CategoriaController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\SubcategoriaController;
use App\Http\Controllers\KardexController;
use App\Http\Controllers\LoteController;
use App\Http\Controllers\MarcaController;
use App\Http\Controllers\MovimientoCajaController;
use App\Http\Controllers\PagoController;
use App\Http\Controllers\ProductoController;
use App\Http\Controllers\ProveedorController;
use App\Http\Controllers\SerieComprobanteController;
use App\Http\Controllers\SesionCajaController;
use App\Http\Controllers\TiendaController;
use App\Http\Controllers\ReporteController;
use App\Http\Controllers\TipoPagoController;
use App\Http\Controllers\VentaController;
use App\Http\Controllers\EmpresaController;
use App\Http\Controllers\EntradaController;
use App\Http\Controllers\SalidaController;
use App\Http\Controllers\UsuarioController;
use App\Http\Controllers\NotificacionController;
use App\Http\Controllers\DashboardController;
use App\Models\Categoria;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;


/*
|--------------------------------------------------------------------------
| INICIO
|--------------------------------------------------------------------------
*/

Route::get('/', function () {

    if (Auth::check()) {
        return redirect()->route('dashboard');
    }

    return redirect()->route('login');
});


/*
|--------------------------------------------------------------------------
| LOGIN
|--------------------------------------------------------------------------
*/

Route::get('/login', function () {
    return Inertia::render('Auth/Login');
})
    ->middleware('guest')
    ->name('login');


/*
|--------------------------------------------------------------------------
| RUTAS AUTENTICADAS
|--------------------------------------------------------------------------
*/

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {


    /*
    |--------------------------------------------------------------------------
    | DASHBOARD
    |--------------------------------------------------------------------------
    */

    Route::get('/dashboard', [
        DashboardController::class,
        'index'
    ])->name('dashboard');


    /*
    |--------------------------------------------------------------------------
    | CONFIGURACIÓN DE TIENDA
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/configuracion/tienda',
        [TiendaController::class, 'index']
    )->name('tiendas.index');

    Route::post(
        '/configuracion/tienda',
        [TiendaController::class, 'store']
    )->name('tiendas.store');

    Route::put(
        '/configuracion/tienda/{tienda}',
        [TiendaController::class, 'update']
    )->name('tiendas.update');


    /*
    |--------------------------------------------------------------------------
    | CLIENTES
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/clientes/buscar-documento',
        [ClienteController::class, 'buscarDocumento']
    )
        ->middleware('permission:clientes.index')
        ->name('clientes.buscar-documento');

    Route::post(
        '/clientes/consultar-dni',
        [ClienteController::class, 'consultarDni']
    )
        ->middleware('permission:clientes.index')
        ->name('clientes.consultar-dni');


    // Listar clientes
    Route::get(
        '/clientes',
        [ClienteController::class, 'index']
    )
        ->middleware('permission:clientes.index')
        ->name('clientes.index');


    // Ver cliente
    Route::get(
        '/clientes/{cliente}',
        [ClienteController::class, 'show']
    )
        ->middleware('permission:clientes.ver')
        ->name('clientes.show');


    /*
    |--------------------------------------------------------------------------
    | PRODUCTOS
    |--------------------------------------------------------------------------
    */

    // Listar productos
    Route::get(
        '/productos',
        [ProductoController::class, 'index']
    )
        ->middleware('permission:productos.index')
        ->name('productos.index');


    // Ver producto
    Route::get(
        '/productos/{producto}',
        [ProductoController::class, 'show']
    )
        ->middleware('permission:productos.ver')
        ->name('productos.show');

    Route::post('/productos', [ProductoController::class, 'store'])
        ->middleware('permission:productos.crear')
        ->name('productos.store');

    Route::put('/productos/{producto}', [ProductoController::class, 'update'])
        // ->middleware('permission:productos.update')
        ->name('productos.update');


    /*

    |--------------------------------------------------------------------------
    | CATEGORÍAS
    |--------------------------------------------------------------------------
    */

    Route::resource('/categorias', CategoriaController::class);

    Route::resource('/subcategorias', SubcategoriaController::class)
        ->except(['show', 'destroy']);

    /*
    |--------------------------------------------------------------------------
    | MARCAS
    |--------------------------------------------------------------------------
    */

    Route::resource('/marcas', MarcaController::class);


    /*
    |--------------------------------------------------------------------------
    | PROVEEDORES
    |--------------------------------------------------------------------------
    */

    Route::resource('/proveedores', ProveedorController::class);


    /*
    |--------------------------------------------------------------------------
    | LOTES
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/lotes',
        [LoteController::class, 'index']
    )->name('lotes.index');


    /*
    |--------------------------------------------------------------------------
    | CAJAS
    |--------------------------------------------------------------------------
    */

    Route::resource('/cajas', CajaController::class)
        ->except(['show', 'edit']);


    /*
    |--------------------------------------------------------------------------
    | SESIONES DE CAJA
    |--------------------------------------------------------------------------
    */

    // Ver/listar sesiones
    Route::get(
        '/sesiones-caja',
        [SesionCajaController::class, 'index']
    )
        ->middleware('permission:sesiones-caja.index')
        ->name('sesiones-caja.index');


    // Sesión actual
    Route::get(
        '/sesiones-caja/actual',
        [SesionCajaController::class, 'actual']
    )
        ->middleware('permission:sesiones-caja.ver')
        ->name('sesiones-caja.actual');


    // Abrir caja
    Route::post(
        '/sesiones-caja/abrir',
        [SesionCajaController::class, 'abrir']
    )
        ->middleware('permission:sesiones-caja.abrir')
        ->name('sesiones-caja.abrir');


    // Cerrar caja
    Route::put(
        '/sesiones-caja/{sesionCaja}/cerrar',
        [SesionCajaController::class, 'cerrar']
    )
        ->middleware('permission:sesiones-caja.cerrar')
        ->name('sesiones-caja.cerrar');


    // Movimientos de una sesión
    Route::get(
        '/sesiones-caja/{sesionCaja}/movimientos',
        [SesionCajaController::class, 'movimientos']
    )
        ->middleware('permission:sesiones-caja.ver')
        ->name('sesiones-caja.movimientos');


    /*
    |--------------------------------------------------------------------------
    | MOVIMIENTOS DE CAJA
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/movimientos-caja',
        [MovimientoCajaController::class, 'index']
    )->name('movimientos-caja.index');

    Route::post(
        '/movimientos-caja',
        [MovimientoCajaController::class, 'store']
    )->name('movimientos-caja.store');


    /*
    |--------------------------------------------------------------------------
    | NOTIFICACIONES
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/notificaciones',
        [NotificacionController::class, 'index']
    )
        ->middleware('permission:notificaciones.index')
        ->name('notificaciones.index');

    Route::patch(
        '/notificaciones/{notificacion}/leida',
        [NotificacionController::class, 'marcarLeida']
    )
        ->middleware('permission:notificaciones.index')
        ->name('notificaciones.leida');

    Route::patch(
        '/notificaciones/marcar-todas-leidas',
        [NotificacionController::class, 'marcarTodasLeidas']
    )
        ->middleware('permission:notificaciones.index')
        ->name('notificaciones.marcarTodasLeidas');

    Route::patch(
        '/notificaciones/{notificacion}/solucionar',
        [NotificacionController::class, 'solucionar']
    )
        ->middleware('permission:notificaciones.index')
        ->name('notificaciones.solucionar');

    Route::delete(
        '/notificaciones/{notificacion}',
        [NotificacionController::class, 'destroy']
    )
        ->middleware('permission:notificaciones.index')
        ->name('notificaciones.destroy');


    /*
    |--------------------------------------------------------------------------
    | VENTAS
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/ventas/productos/buscar',
        [VentaController::class, 'buscarProductos']
    )
        ->middleware('permission:ventas.crear')
        ->name('ventas.productos.buscar');

    // Listar ventas
    Route::get(
        '/ventas',
        [VentaController::class, 'index']
    )
        ->middleware('permission:ventas.index')
        ->name('ventas.index');


    // Crear venta - formulario
    Route::get(
        '/ventas/create',
        [VentaController::class, 'create']
    )
        ->middleware('permission:ventas.crear')
        ->name('ventas.create');


    // Registrar venta
    Route::post(
        '/ventas',
        [VentaController::class, 'store']
    )
        ->middleware('permission:ventas.crear')
        ->name('ventas.store');


    // Ver venta
    Route::get(
        '/ventas/{venta}',
        [VentaController::class, 'show']
    )
        ->middleware('permission:ventas.ver')
        ->name('ventas.show');


    // Comprobante
    Route::get(
        '/ventas/{venta}/comprobante',
        [VentaController::class, 'comprobante']
    )
        ->middleware('permission:ventas.ver')
        ->name('ventas.comprobante');


    /*
    |--------------------------------------------------------------------------
    | KARDEX
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/kardex',
        [KardexController::class, 'index']
    )->name('kardex.index');


    /*
    |--------------------------------------------------------------------------
    | USUARIOS
    |--------------------------------------------------------------------------
    */

    Route::resource('/usuarios', UsuarioController::class);


    /*
    |--------------------------------------------------------------------------
    | ENTRADAS
    |--------------------------------------------------------------------------
    */

    Route::resource('/entradas', EntradaController::class);

    Route::get(
        '/entradas/{entrada}/edit-data',
        [EntradaController::class, 'editData']
    )->name('entradas.editData');


    /*
    |--------------------------------------------------------------------------
    | SALIDAS
    |--------------------------------------------------------------------------
    */
    Route::get('/salidas', [SalidaController::class, 'index'])
        ->name('salidas.index');

    Route::post('/salidas', [SalidaController::class, 'store'])
        ->name('salidas.store');

    // Empresas

    Route::resource('/empresas', EmpresaController::class);
    Route::get(
        '/sesiones-caja/verificar-abierta',
        function () {
            $abierta = \App\Models\SesionCaja::where('user_id', Auth::id())
                ->where('estado', 'abierta')
                ->exists();

            return response()->json([
                'abierta' => $abierta,
            ]);
        }
    )->name('sesiones-caja.verificar-abierta');
    // Página principal de reportes
    Route::get('/reportes', [ReporteController::class, 'index'])
        ->name('reportes.index');
    Route::get('/reportes/productos-empresa/pdf', [ReporteController::class, 'productosEmpresaPdf'])
        ->name('reportes.productos-empresa.pdf');
    Route::get('/reportes/ventas', [ReporteController::class, 'ventas'])->name('reportes.ventas');

    Route::get(
        '/reportes/empresa/{empresa}/ventas-pdf',
        [ReporteController::class, 'ventasEmpresaPdf']
    )->name('reportes.empresa.ventas.pdf');
});
