<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

class TipoPagoController extends Controller
{
    public function index(){
        return Inertia::render(
            'TiposPago/Index'
        );
    }
}
