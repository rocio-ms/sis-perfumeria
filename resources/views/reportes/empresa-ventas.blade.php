<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">

    <title>
        Reporte de ventas - {{ $empresa->razon_social }}
    </title>

    <style>

        @page {
            margin: 35px 30px;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
            color: #1f2937;
        }

        .header {
            border-bottom: 2px solid #4f46e5;
            padding-bottom: 12px;
            margin-bottom: 20px;
        }

        .titulo {
            font-size: 20px;
            font-weight: bold;
            color: #312e81;
            margin-bottom: 5px;
        }

        .subtitulo {
            font-size: 11px;
            color: #6b7280;
        }

        .empresa {
            font-size: 14px;
            font-weight: bold;
            margin-top: 8px;
        }

        .resumen {
            width: 100%;
            margin-bottom: 20px;
        }

        .resumen td {
            width: 33.33%;
            border: 1px solid #e5e7eb;
            padding: 10px;
            background: #f9fafb;
        }

        .resumen-label {
            color: #6b7280;
            font-size: 9px;
        }

        .resumen-value {
            font-size: 15px;
            font-weight: bold;
            margin-top: 4px;
        }

        .dia {
            background: #eef2ff;
            border-left: 4px solid #4f46e5;
            padding: 8px 10px;
            margin-top: 18px;
            margin-bottom: 8px;
            font-size: 12px;
            font-weight: bold;
            color: #312e81;
        }

        .venta {
            border: 1px solid #e5e7eb;
            margin-bottom: 12px;
            page-break-inside: avoid;
        }

        .venta-header {
            background: #f8fafc;
            padding: 8px;
            border-bottom: 1px solid #e5e7eb;
        }

        .venta-info {
            width: 100%;
        }

        .venta-info td {
            padding: 2px 4px;
            vertical-align: top;
        }

        .label {
            color: #6b7280;
            font-size: 8px;
        }

        .value {
            font-weight: bold;
            font-size: 9px;
        }

        .productos {
            width: 100%;
            border-collapse: collapse;
        }

        .productos th {
            background: #f3f4f6;
            color: #374151;
            font-size: 8px;
            padding: 6px;
            border-bottom: 1px solid #d1d5db;
            text-align: left;
        }

        .productos td {
            padding: 6px;
            border-bottom: 1px solid #e5e7eb;
            font-size: 8px;
        }

        .text-right {
            text-align: right;
        }

        .total-venta {
            background: #f9fafb;
            padding: 8px;
            text-align: right;
            font-size: 10px;
            font-weight: bold;
        }

        .total-dia {
            text-align: right;
            padding: 8px;
            margin-bottom: 10px;
            font-weight: bold;
            color: #312e81;
        }

        .total-general {
            margin-top: 20px;
            border-top: 2px solid #4f46e5;
            padding-top: 12px;
            text-align: right;
            font-size: 16px;
            font-weight: bold;
            color: #312e81;
        }

        .footer {
            position: fixed;
            bottom: -20px;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 8px;
            color: #9ca3af;
        }

    </style>

</head>

<body>

    {{-- ========================================================= --}}
    {{-- ENCABEZADO --}}
    {{-- ========================================================= --}}

    <div class="header">

        <div class="titulo">
            REPORTE DETALLADO DE VENTAS
        </div>

        <div class="subtitulo">
            Detalle de ventas por empresa
        </div>

        <div class="empresa">
            {{ $empresa->razon_social }}
        </div>

        <div class="subtitulo">
            Periodo: {{ $periodo }} ({{ $fechaInicio }} - {{ $fechaFin }})
        </div>

        @if(!empty($empresa->ruc))
            <div class="subtitulo">
                RUC: {{ $empresa->ruc }}
            </div>
        @endif

    </div>


    {{-- ========================================================= --}}
    {{-- RESUMEN --}}
    {{-- ========================================================= --}}

    <table class="resumen">

        <tr>

            <td>

                <div class="resumen-label">
                    TOTAL DE VENTAS
                </div>

                <div class="resumen-value">
                    {{ $cantidadVentas }}
                </div>

            </td>

            <td>

                <div class="resumen-label">
                    PRODUCTOS VENDIDOS
                </div>

                <div class="resumen-value">
                    {{ number_format($totalProductos, 0) }}
                </div>

            </td>

            <td>

                <div class="resumen-label">
                    TOTAL VENDIDO
                </div>

                <div class="resumen-value">
                    S/ {{ number_format($totalGeneral, 2) }}
                </div>

            </td>

        </tr>

    </table>


    {{-- ========================================================= --}}
    {{-- VENTAS AGRUPADAS POR DÍA --}}
    {{-- ========================================================= --}}

    @forelse($ventasPorDia as $fecha => $ventas)

        <div class="dia">

            FECHA:
            {{ $fecha }}

        </div>


        @php
            $totalDia = 0;
        @endphp


        @foreach($ventas as $venta)

            @php
                $totalDia += $venta['total'];
            @endphp


            <div class="venta">

                {{-- ================================================= --}}
                {{-- INFORMACIÓN DE LA VENTA --}}
                {{-- ================================================= --}}

                <div class="venta-header">

                    <table class="venta-info">

                        <tr>

                            <td width="20%">

                                <div class="label">
                                    HORA
                                </div>

                                <div class="value">
                                    {{ $venta['hora'] }}
                                </div>

                            </td>

                            <td width="25%">

                                <div class="label">
                                    COMPROBANTE
                                </div>

                                <div class="value">
                                    {{ $venta['comprobante'] }}
                                </div>

                            </td>

                            <td width="30%">

                                <div class="label">
                                    CLIENTE
                                </div>

                                <div class="value">
                                    {{ $venta['cliente'] }}
                                </div>

                            </td>

                            <td width="25%">

                                <div class="label">
                                    TIPO DE PAGO
                                </div>

                                <div class="value">
                                    {{ $venta['tipo_pago'] }}
                                </div>

                            </td>

                        </tr>

                    </table>

                </div>


                {{-- ================================================= --}}
                {{-- PRODUCTOS --}}
                {{-- ================================================= --}}

                <table class="productos">

                    <thead>

                        <tr>

                            <th width="12%">
                                Código
                            </th>

                            <th width="38%">
                                Producto
                            </th>

                            <th width="15%">
                                Cant.
                            </th>

                            <th width="17%">
                                P. Unit.
                            </th>

                            <th width="18%">
                                Importe
                            </th>

                        </tr>

                    </thead>

                    <tbody>

                        @foreach($venta['detalles'] as $detalle)

                            <tr>

                                <td>
                                    {{ $detalle->producto?->codigo ?? '—' }}
                                </td>

                                <td>
                                    {{ $detalle->producto?->nombre ?? $detalle->descripcion }}
                                </td>

                                <td>
                                    {{ $detalle->cantidad }}
                                </td>

                                <td class="text-right">
                                    S/
                                    {{ number_format($detalle->precio_unitario, 2) }}
                                </td>

                                <td class="text-right">

                                    S/
                                    {{ number_format($detalle->subtotal, 2) }}

                                </td>

                            </tr>

                        @endforeach

                    </tbody>

                </table>


                {{-- ================================================= --}}
                {{-- TOTAL DE LA VENTA --}}
                {{-- ================================================= --}}

                <div class="total-venta">

                    TOTAL DE LA VENTA:

                    S/
                    {{ number_format($venta['total'], 2) }}

                </div>

            </div>

        @endforeach


        {{-- ================================================= --}}
        {{-- TOTAL DEL DÍA --}}
        {{-- ================================================= --}}

        <div class="total-dia">

            TOTAL DEL {{ $fecha }}:

            S/
            {{ number_format($totalDia, 2) }}

        </div>

    @empty

        <div style="text-align:center; padding:40px; color:#6b7280;">

            No existen ventas registradas para esta empresa en el periodo seleccionado.

        </div>

    @endforelse


    {{-- ========================================================= --}}
    {{-- TOTAL GENERAL --}}
    {{-- ========================================================= --}}

    <div class="total-general">

        TOTAL GENERAL:

        S/
        {{ number_format($totalGeneral, 2) }}

    </div>


    {{-- ========================================================= --}}
    {{-- FOOTER --}}
    {{-- ========================================================= --}}

    <div class="footer">

        Reporte generado por Sistema Perfumería Belleza

    </div>

</body>

</html>