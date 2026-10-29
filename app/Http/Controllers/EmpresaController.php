<?php

namespace App\Http\Controllers;

use App\Models\Empresa;
use App\Models\Tienda;
use App\Models\SerieComprobante;
use App\Models\TipoComprobante;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

use Inertia\Inertia;

class EmpresaController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Listar empresas
    |--------------------------------------------------------------------------
    */

    public function index()
    {
        $empresas = Empresa::with([
                'tienda',
                'marcas',
                'seriesComprobantes.tipoComprobante',
            ])
            ->orderBy('id', 'desc')
            ->get();

        $tiendas = Tienda::orderBy(
                'nombre_comercial'
            )
            ->get();
        return Inertia::render(
            'Empresas/Index',
            [
                'empresas' => $empresas,
                'tiendas' => $tiendas,
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Mostrar formulario de creación
    |--------------------------------------------------------------------------
    */

    public function create()
    {
        $tiendas = Tienda::orderBy(
                'nombre_comercial'
            )
            ->get();
        return Inertia::render(
            'Empresas/Form',
            [
                'tiendas' => $tiendas,
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Guardar empresa
    |--------------------------------------------------------------------------
    */

    public function store(Request $request)
    {
        $validated = $request->validate(
            [
                'tienda_id' => [
                    'required',
                    'exists:tiendas,id',
                ],

                'ruc' => [
                    'required',
                    'string',
                    'digits:11',
                    'unique:empresas,ruc',
                ],

                'razon_social' => [
                    'required',
                    'string',
                    'max:255',
                ],

                'activo' => [
                    'required',
                    'boolean',
                ],

                /*
                |--------------------------------------------------------------------------
                | SERIES
                |--------------------------------------------------------------------------
                */

                'series' => [
                    'required',
                    'array',
                ],

                'series.boleta.serie' => [
                    'required',
                    'string',
                    'size:4',
                    'regex:/^[A-Z0-9]+$/',
                ],

                'series.factura.serie' => [
                    'required',
                    'string',
                    'size:4',
                    'regex:/^[A-Z0-9]+$/',
                ],
            ],

            [
                'tienda_id.required' =>
                    'Debes seleccionar una tienda.',

                'tienda_id.exists' =>
                    'La tienda seleccionada no es válida.',

                'ruc.required' =>
                    'El RUC es obligatorio.',

                'ruc.digits' =>
                    'El RUC debe tener exactamente 11 dígitos.',

                'ruc.unique' =>
                    'El RUC ingresado ya está registrado.',

                'razon_social.required' =>
                    'La razón social es obligatoria.',

                'activo.required' =>
                    'Debes indicar el estado de la empresa.',

                'series.required' =>
                    'Debes configurar las series de comprobantes.',

                'series.boleta.serie.required' =>
                    'La serie de Boleta es obligatoria.',

                'series.boleta.serie.size' =>
                    'La serie de Boleta debe tener exactamente 4 caracteres.',

                'series.boleta.serie.regex' =>
                    'La serie de Boleta solo puede contener letras y números.',

                'series.factura.serie.required' =>
                    'La serie de Factura es obligatoria.',

                'series.factura.serie.size' =>
                    'La serie de Factura debe tener exactamente 4 caracteres.',

                'series.factura.serie.regex' =>
                    'La serie de Factura solo puede contener letras y números.',
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | TRANSACCIÓN
        |--------------------------------------------------------------------------
        |
        | La empresa y sus series se crean juntas.
        |
        */

        DB::transaction(function () use ($validated) {

            /*
            |--------------------------------------------------------------------------
            | Crear empresa
            |--------------------------------------------------------------------------
            */

            $empresa = Empresa::create([
                'tienda_id' =>
                    $validated['tienda_id'],

                'ruc' =>
                    $validated['ruc'],

                'razon_social' =>
                    $validated['razon_social'],

                'activo' =>
                    $validated['activo'],
            ]);

            /*
            |--------------------------------------------------------------------------
            | Buscar tipos de comprobante
            |--------------------------------------------------------------------------
            */

            $tipoBoleta = TipoComprobante::whereRaw(
                'LOWER(nombre) LIKE ?',
                ['%boleta%']
            )->first();

            $tipoFactura = TipoComprobante::whereRaw(
                'LOWER(nombre) LIKE ?',
                ['%factura%']
            )->first();

            if (!$tipoBoleta) {
                throw new \Exception(
                    'No existe el tipo de comprobante Boleta.'
                );
            }

            if (!$tipoFactura) {
                throw new \Exception(
                    'No existe el tipo de comprobante Factura.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Crear serie de BOLETA
            |--------------------------------------------------------------------------
            */

            SerieComprobante::create([
                'tienda_id' =>
                    $empresa->tienda_id,

                'empresa_id' =>
                    $empresa->id,

                'tipo_comprobante_id' =>
                    $tipoBoleta->id,

                'serie' =>
                    strtoupper(
                        $validated['series']['boleta']['serie']
                    ),

                'correlativo_actual' =>
                    0,

                'activo' =>
                    true,
            ]);

            /*
            |--------------------------------------------------------------------------
            | Crear serie de FACTURA
            |--------------------------------------------------------------------------
            */

            SerieComprobante::create([
                'tienda_id' =>
                    $empresa->tienda_id,

                'empresa_id' =>
                    $empresa->id,

                'tipo_comprobante_id' =>
                    $tipoFactura->id,

                'serie' =>
                    strtoupper(
                        $validated['series']['factura']['serie']
                    ),

                'correlativo_actual' =>
                    0,

                'activo' =>
                    true,
            ]);
        });

        return back()->with(
            'success',
            'Empresa y series creadas correctamente.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Mostrar empresa
    |--------------------------------------------------------------------------
    */

    public function show(Empresa $empresa)
    {
        $empresa->load([
            'tienda',
            'marcas',
            'seriesComprobantes.tipoComprobante',
        ]);

        return Inertia::render(
            'Empresas/Show',
            [
                'empresa' => $empresa,
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Mostrar formulario de edición
    |--------------------------------------------------------------------------
    */

    public function edit(Empresa $empresa)
    {
        $empresa->load([
            'tienda',
            'marcas',
            'seriesComprobantes.tipoComprobante',
        ]);

        $tiendas = Tienda::orderBy(
                'nombre_comercial'
            )
            ->get();
        return Inertia::render(
            'Empresas/Form',
            [
                'empresa' => $empresa,
                'tiendas' => $tiendas,
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Actualizar empresa
    |--------------------------------------------------------------------------
    */

    public function update(
        Request $request,
        Empresa $empresa
    ) {
        $validated = $request->validate(
            [
                'tienda_id' => [
                    'required',
                    'exists:tiendas,id',
                ],

                'ruc' => [
                    'required',
                    'string',
                    'digits:11',
                    Rule::unique(
                        'empresas',
                        'ruc'
                    )->ignore($empresa->id),
                ],

                'razon_social' => [
                    'required',
                    'string',
                    'max:255',
                ],

                'activo' => [
                    'required',
                    'boolean',
                ],

                /*
                |--------------------------------------------------------------------------
                | SERIES
                |--------------------------------------------------------------------------
                */

                'series' => [
                    'required',
                    'array',
                ],

                'series.boleta.serie' => [
                    'required',
                    'string',
                    'size:4',
                    'regex:/^[A-Z0-9]+$/',
                ],

                'series.factura.serie' => [
                    'required',
                    'string',
                    'size:4',
                    'regex:/^[A-Z0-9]+$/',
                ],
            ],

            [
                'tienda_id.required' =>
                    'Debes seleccionar una tienda.',

                'tienda_id.exists' =>
                    'La tienda seleccionada no es válida.',

                'ruc.required' =>
                    'El RUC es obligatorio.',

                'ruc.digits' =>
                    'El RUC debe tener exactamente 11 dígitos.',

                'ruc.unique' =>
                    'El RUC ingresado ya está registrado.',

                'razon_social.required' =>
                    'La razón social es obligatoria.',

                'activo.required' =>
                    'Debes indicar el estado de la empresa.',

                'series.required' =>
                    'Debes configurar las series de comprobantes.',

                'series.boleta.serie.required' =>
                    'La serie de Boleta es obligatoria.',

                'series.boleta.serie.size' =>
                    'La serie de Boleta debe tener exactamente 4 caracteres.',

                'series.boleta.serie.regex' =>
                    'La serie de Boleta solo puede contener letras y números.',

                'series.factura.serie.required' =>
                    'La serie de Factura es obligatoria.',

                'series.factura.serie.size' =>
                    'La serie de Factura debe tener exactamente 4 caracteres.',

                'series.factura.serie.regex' =>
                    'La serie de Factura solo puede contener letras y números.',
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | TRANSACCIÓN
        |--------------------------------------------------------------------------
        */

        DB::transaction(function () use (
            $validated,
            $empresa
        ) {

            /*
            |--------------------------------------------------------------------------
            | Actualizar datos de empresa
            |--------------------------------------------------------------------------
            */

            $empresa->update([
                'tienda_id' =>
                    $validated['tienda_id'],

                'ruc' =>
                    $validated['ruc'],

                'razon_social' =>
                    $validated['razon_social'],

                'activo' =>
                    $validated['activo'],
            ]);

            /*
            |--------------------------------------------------------------------------
            | Buscar tipos de comprobante
            |--------------------------------------------------------------------------
            */

            $tipoBoleta = TipoComprobante::whereRaw(
                'LOWER(nombre) LIKE ?',
                ['%boleta%']
            )->first();

            $tipoFactura = TipoComprobante::whereRaw(
                'LOWER(nombre) LIKE ?',
                ['%factura%']
            )->first();

            if (!$tipoBoleta) {
                throw new \Exception(
                    'No existe el tipo de comprobante Boleta.'
                );
            }

            if (!$tipoFactura) {
                throw new \Exception(
                    'No existe el tipo de comprobante Factura.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | ACTUALIZAR SERIE DE BOLETA
            |--------------------------------------------------------------------------
            */

            $serieBoleta =
                SerieComprobante::where(
                    'empresa_id',
                    $empresa->id
                )
                ->where(
                    'tipo_comprobante_id',
                    $tipoBoleta->id
                )
                ->first();

            if (!$serieBoleta) {

                SerieComprobante::create([
                    'tienda_id' =>
                        $empresa->tienda_id,

                    'empresa_id' =>
                        $empresa->id,

                    'tipo_comprobante_id' =>
                        $tipoBoleta->id,

                    'serie' =>
                        strtoupper(
                            $validated['series']['boleta']['serie']
                        ),

                    'correlativo_actual' =>
                        0,

                    'activo' =>
                        true,
                ]);

            } else {

                /*
                |--------------------------------------------------------------------------
                | No permitir cambiar la serie si ya tiene ventas
                |--------------------------------------------------------------------------
                */

                $tieneVentas =
                    $serieBoleta
                        ->ventas()
                        ->exists();

                if (
                    $tieneVentas &&
                    $serieBoleta->serie !==
                        strtoupper(
                            $validated['series']['boleta']['serie']
                        )
                ) {
                    throw new \Exception(
                        'No puedes cambiar la serie de Boleta porque ya existen ventas asociadas a ella.'
                    );
                }

                $serieBoleta->update([
                    'serie' =>
                        strtoupper(
                            $validated['series']['boleta']['serie']
                        ),

                    'activo' =>
                        true,
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | ACTUALIZAR SERIE DE FACTURA
            |--------------------------------------------------------------------------
            */

            $serieFactura =
                SerieComprobante::where(
                    'empresa_id',
                    $empresa->id
                )
                ->where(
                    'tipo_comprobante_id',
                    $tipoFactura->id
                )
                ->first();

            if (!$serieFactura) {

                SerieComprobante::create([
                    'tienda_id' =>
                        $empresa->tienda_id,

                    'empresa_id' =>
                        $empresa->id,

                    'tipo_comprobante_id' =>
                        $tipoFactura->id,

                    'serie' =>
                        strtoupper(
                            $validated['series']['factura']['serie']
                        ),

                    'correlativo_actual' =>
                        0,

                    'activo' =>
                        true,
                ]);

            } else {

                /*
                |--------------------------------------------------------------------------
                | No permitir cambiar la serie si ya tiene ventas
                |--------------------------------------------------------------------------
                */

                $tieneVentas =
                    $serieFactura
                        ->ventas()
                        ->exists();

                if (
                    $tieneVentas &&
                    $serieFactura->serie !==
                        strtoupper(
                            $validated['series']['factura']['serie']
                        )
                ) {
                    throw new \Exception(
                        'No puedes cambiar la serie de Factura porque ya existen ventas asociadas a ella.'
                    );
                }

                $serieFactura->update([
                    'serie' =>
                        strtoupper(
                            $validated['series']['factura']['serie']
                        ),

                    'activo' =>
                        true,
                ]);
            }
        });

        return back()->with(
            'success',
            'Empresa y series actualizadas correctamente.'
        );
    }
}
