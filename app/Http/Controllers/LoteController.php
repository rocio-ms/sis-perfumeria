<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Lote;

class LoteController extends Controller
{
    public function index(){

        $lotes = Lote::with([
            'detalleEntrada.producto',
            'detalleEntrada.entrada.proveedor',
        ])
        ->latest()
        ->get();

        return Inertia::render(
            'Lotes/Index', 
            [ 'lotes' => $lotes ]
        );
    }
}
