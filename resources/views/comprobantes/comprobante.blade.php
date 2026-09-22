<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">

    <title>
        {{ $venta->tipoComprobante->nombre ?? 'Comprobante' }}
        {{ $venta->serie }}-{{ $venta->correlativo }}
    </title>

    <style>
        @page {
            size: 80mm auto;
            margin: 0;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 0;
            width: 80mm;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 12px;
            color: #000;
        }

        .ticket {
            width: 80mm;
            padding: 5mm;
        }

        .center {
            text-align: center;
        }

        .right {
            text-align: right;
        }

        .bold {
            font-weight: bold;
        }

        .logo {
            font-size: 20px;
            font-weight: bold;
        }

        .subtitle {
            font-size: 11px;
            margin-top: 2px;
        }

        .line {
            border-top: 1px dashed #000;
            margin: 8px 0;
        }

        .comprobante {
            font-size: 14px;
            font-weight: bold;
            margin-top: 8px;
        }

        .info {
            margin-top: 5px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }

        th,
        td {
            padding: 3px 0;
            vertical-align: top;
        }

        th {
            border-bottom: 1px dashed #000;
        }

        .producto {
            width: 45%;
        }

        .cantidad {
            width: 15%;
            text-align: center;
        }

        .precio {
            width: 20%;
            text-align: right;
        }

        .importe {
            width: 20%;
            text-align: right;
        }

        .totales {
            margin-top: 8px;
        }

        .total-final {
            font-size: 16px;
            font-weight: bold;
        }

        .footer {
            margin-top: 15px;
            text-align: center;
            font-size: 11px;
        }

        @media print {
            body {
                width: 80mm;
            }

            .ticket {
                width: 80mm;
            }
        }
    </style>
</head>

<body>

<div class="ticket">

    {{-- ENCABEZADO --}}
    <div class="center">

        <div class="logo">
            ESSENCE BOUTIQUE
        </div>

        <div class="subtitle">
            Perfumería & Cosmética de autor
        </div>

        <div class="subtitle">
            Ventas · Inventario · Clientes
        </div>

        <div class="line"></div>

        <div class="comprobante">
            {{ strtoupper($venta->tipoComprobante->nombre ?? 'COMPROBANTE') }}
        </div>

        <div class="bold">
            {{ $venta->serie }}-{{ $venta->correlativo }}
        </div>

    </div>

    <div class="line"></div>

    {{-- DATOS --}}
    <div class="info">

        <div>
            <strong>Fecha:</strong>
            {{ \Carbon\Carbon::parse($venta->fecha_emision)->format('d/m/Y H:i') }}
        </div>

        <div>
            <strong>Cliente:</strong>
            {{ $venta->cliente->razon_social_nombre ?? 'CLIENTE ANÓNIMO' }}
        </div>

        <div>
            <strong>Documento:</strong>
            {{ $venta->cliente->numero_documento ?? '00000000' }}
        </div>

    </div>

    <div class="line"></div>

    {{-- DETALLE --}}
    <table>

        <thead>

        <tr>
            <th class="producto">
                Producto
            </th>

            <th class="cantidad">
                Cant.
            </th>

            <th class="precio">
                P.U.
            </th>

            <th class="importe">
                Importe
            </th>
        </tr>

        </thead>

        <tbody>

        @foreach($venta->detalles as $detalle)

            <tr>

                <td class="producto">
                    {{ $detalle->producto->nombre ?? $detalle->descripcion }}
                </td>

                <td class="cantidad">
                    {{ $detalle->cantidad }}
                </td>

                <td class="precio">
                    {{ number_format($detalle->precio_unitario, 2) }}
                </td>

                <td class="importe">
                    {{ number_format($detalle->subtotal + $detalle->igv, 2) }}
                </td>

            </tr>

        @endforeach

        </tbody>

    </table>

    <div class="line"></div>

    {{-- TOTALES --}}
    <div class="totales">

        <table>

            <tr>
                <td>
                    Op. Gravada
                </td>

                <td class="right">
                    S/ {{ number_format($venta->op_gravada, 2) }}
                </td>
            </tr>

            <tr>
                <td>
                    IGV
                </td>

                <td class="right">
                    S/ {{ number_format($venta->igv, 2) }}
                </td>
            </tr>

            @if($venta->descuento_total > 0)

                <tr>
                    <td>
                        Descuento
                    </td>

                    <td class="right">
                        S/ {{ number_format($venta->descuento_total, 2) }}
                    </td>
                </tr>

            @endif

            <tr>
                <td class="total-final">
                    TOTAL
                </td>

                <td class="right total-final">
                    S/ {{ number_format($venta->importe_total, 2) }}
                </td>
            </tr>

        </table>

    </div>

    <div class="line"></div>

    {{-- FORMA DE PAGO --}}
    <div>

        <strong>
            Forma de pago:
        </strong>

        {{ strtoupper($venta->forma_pago) }}

        @if($venta->tipoPago)
            - {{ $venta->tipoPago->nombre }}
        @endif

    </div>

    {{-- PIE --}}
    <div class="footer">

        <div>
            ¡Gracias por su compra!
        </div>

        <div style="margin-top: 5px;">
            ESSENCE BOUTIQUE
        </div>

    </div>

</div>

<script>
    window.onload = function () {
        window.print();
    };
</script>

</body>

</html>