<?php

namespace App\Http\Controllers;

use App\Models\Caja;
use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\Marca;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\Subcategoria;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EstadoRegistroController extends Controller
{
    public const MODELOS = [
        'cajas' => Caja::class,
        'categorias' => Categoria::class,
        'clientes' => Cliente::class,
        'empresas' => Empresa::class,
        'marcas' => Marca::class,
        'productos' => Producto::class,
        'proveedores' => Proveedor::class,
        'subcategorias' => Subcategoria::class,
        'usuarios' => User::class,
    ];

    public function __invoke(Request $request, string $modulo, int $registro): RedirectResponse
    {
        $usuario = $request->user();
        abort_unless($usuario->hasRole('Administrador') || $usuario->can("{$modulo}.editar"), 403);

        $estado = $request->validate(['activo' => 'required|boolean']);
        $modelo = self::MODELOS[$modulo];
        $modelo::findOrFail($registro)->update($estado);

        return back(303);
    }
}
