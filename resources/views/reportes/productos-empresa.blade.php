<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Productos vendidos por empresa</title>
    <style>
        body { font: 11px DejaVu Sans, sans-serif; color: #1e293b; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 7px; border: 1px solid #cbd5e1; text-align: left; }
        th { background: #f1f5f9; }
        .numero { text-align: right; }
    </style>
</head>
<body>
    <h1>Productos vendidos por empresa</h1>
    <p>{{ $empresa->razon_social }} · {{ $periodo }} · {{ $fechaInicio }} al {{ $fechaFin }}</p>
    <table>
        <thead>
            <tr><th>Código</th><th>Producto</th><th>Unidades</th><th>Importe</th></tr>
        </thead>
        <tbody>
            @forelse ($productos as $producto)
                <tr>
                    <td>{{ $producto['codigo'] }}</td>
                    <td>{{ $producto['producto'] }}</td>
                    <td class="numero">{{ $producto['cantidad'] }}</td>
                    <td class="numero">S/ {{ number_format($producto['total'], 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="4">No hay ventas en el período seleccionado.</td></tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <th colspan="2">Total</th>
                <th class="numero">{{ $totalUnidades }}</th>
                <th class="numero">S/ {{ number_format($totalImporte, 2) }}</th>
            </tr>
        </tfoot>
    </table>
</body>
</html>
