<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Services\JsonPeService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

class ClienteController extends Controller
{
    public function index(Request $request)
    {

        $buscar = $request->input('buscar');

        $clientes = Cliente::query()
            ->when($buscar, function ($query) use ($buscar) {
                $query->where(function ($q) use ($buscar) {
                    $q->where('numero_documento', 'like', "%{$buscar}%")
                        ->orWhere('razon_social_nombre', 'like', "%{$buscar}%")
                        ->orWhere('ap_paterno', 'like', "%{$buscar}%")
                        ->orWhere('ap_materno', 'like', "%{$buscar}%")
                        ->orWhere('nombres', 'like', "%{$buscar}%")
                        ->orWhereRaw(
                            "CONCAT(nombres, ' ', ap_paterno, ' ', ap_materno) LIKE ?",
                            ["%{$buscar}%"]
                        );
                });
            })
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render(
            'Clientes/Index',
            [
                'clientes' => $clientes,
                'filtros' => [
                    'buscar' => $buscar,
                ],
            ]
        );
    }

    public function create()
    {
        return Inertia::render('Clientes/Form', [
            'cliente' => null,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'tipo_documento' => [
                'required',
                'in:DNI,RUC',
            ],

            'numero_documento' => [
                'required',
                'string',
                'max:15',
                'unique:clientes,numero_documento',
            ],

            'razon_social_nombre' => [
                'required',
                'string',
                'max:255',
            ],

            'ap_paterno' => [
                'nullable',
                'string',
                'max:100',
            ],

            'ap_materno' => [
                'nullable',
                'string',
                'max:100',
            ],

            'nombres' => [
                'nullable',
                'string',
                'max:200',
            ],

            'direccion' => [
                'nullable',
                'string',
                'max:255',
            ],

            'telefono' => [
                'nullable',
                'string',
                'max:20',
            ],

            'email' => [
                'nullable',
                'email',
                'max:255',
            ],

            'activo' => [
                'boolean',
            ],
        ]);

        Cliente::create($validated);

        return redirect()
            ->route('clientes.index')
            ->with('success', 'Cliente registrado correctamente.');
    }

    public function show(Cliente $cliente)
    {
        return Inertia::render('Clientes/Show', [
            'cliente' => $cliente,
        ]);
    }


    public function edit(Cliente $cliente)
    {
        return Inertia::render('Clientes/Form', [
            'cliente' => $cliente,
        ]);
    }

    public function update(Request $request, Cliente $cliente)
    {
        $validated = $request->validate([
            'tipo_documento' => [
                'required',
                'in:DNI,RUC',
            ],

            'numero_documento' => [
                'required',
                'string',
                'max:15',
                'unique:clientes,numero_documento,' . $cliente->id,
            ],

            'razon_social_nombre' => [
                'required',
                'string',
                'max:255',
            ],

            'ap_paterno' => [
                'nullable',
                'string',
                'max:100',
            ],

            'ap_materno' => [
                'nullable',
                'string',
                'max:100',
            ],

            'nombres' => [
                'nullable',
                'string',
                'max:200',
            ],

            'direccion' => [
                'nullable',
                'string',
                'max:255',
            ],

            'telefono' => [
                'nullable',
                'string',
                'max:20',
            ],

            'email' => [
                'nullable',
                'email',
                'max:255',
            ],

            'activo' => [
                'boolean',
            ],
        ]);

        $cliente->update($validated);

        return redirect()
            ->route('clientes.index')
            ->with('success', 'Cliente actualizado correctamente.');
    }


    public function toggleEstado(Cliente $cliente)
    {
        $cliente->update([
            'activo' => !$cliente->activo,
        ]);

        return back()->with(
            'success',
            $cliente->activo
                ? 'Cliente activado correctamente.'
                : 'Cliente desactivado correctamente.'
        );
    }

    public function buscarDocumento(Request $request)
    {
        $request->validate([
            'tipo_documento' => 'required|in:DNI,RUC',
            'numero_documento' => 'required|string',
        ]);

        $cliente = Cliente::where(
            'tipo_documento',
            $request->tipo_documento
        )
            ->where(
                'numero_documento',
                $request->numero_documento
            )
            ->first();

        if (!$cliente) {
            return response()->json([
                'success' => false,
                'message' => 'Cliente no encontrado',
            ]);
        }

        return response()->json([
            'success' => true,
            'cliente' => [
                'id' => $cliente->id,
                'tipo_documento' => $cliente->tipo_documento,
                'numero_documento' => $cliente->numero_documento,
                'nombre' => trim(
                    $cliente->nombres . ' ' .
                        $cliente->ap_paterno . ' ' .
                        $cliente->ap_materno
                ),
            ],
        ]);
    }

    public function consultarDni(Request $request, JsonPeService $jsonPe)
    {

        $validator = Validator::make($request->all(), [
            'numero_documento' => [
                'required',
                'digits:8',
            ],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'El DNI debe tener 8 dígitos',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $resultado = $jsonPe->consultarDni(
                $request->numero_documento
            );

            // dd($resultado);

            if (!($resultado['success'] ?? false)) {
                return response()->json([
                    'success' => false,
                    'message' => $resultado['message']
                        ?? 'No se encontraron datos para el DNI',
                ], 404);
            }

            $data = $resultado['data'] ?? [];

            return response()->json([
                'success' => true,
                'data' => [
                    'dni' => $data['numero'] ?? $request->numero_documento,
                    'nombres' => $data['nombres'] ?? '',
                    'apellido_paterno' => $data['apellido_paterno'] ?? '',
                    'apellido_materno' => $data['apellido_materno'] ?? '',
                    'nombre_completo' => $data['nombre_completo']
                        ?? trim(
                            ($data['nombres'] ?? '') . ' ' .
                                ($data['apellido_paterno'] ?? '') . ' ' .
                                ($data['apellido_materno'] ?? '')
                        ),
                ],
            ]);
        } catch (Throwable $e) {
            // return response()->json([
            //     'success' => false,
            //     'message' => 'No fue posible consultar el DNI en este momento.',
            // ], 500);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error' => get_class($e),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ], 500);
        }
    }
}
