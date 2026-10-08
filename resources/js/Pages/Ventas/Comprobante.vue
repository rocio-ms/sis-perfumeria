<script setup>
import { computed } from "vue";
import { router } from "@inertiajs/vue3";

import AppLayout from "@/Layouts/AppLayout.vue";
import Card from "@/Components/Card.vue";
import ComprobanteTicket from "./ComprobanteTicket.vue";
import Button from "primevue/button";

const props = defineProps({
    venta: {
        type: Object,
        required: true,
    },

    comprobantes: {
        type: Array,
        default: () => [],
    },

    tienda: {
        type: Object,
        default: null,
    },
});

/*
|--------------------------------------------------------------------------
| TIENDA
|--------------------------------------------------------------------------
*/

const tienda = computed(() => props.tienda ?? {});

const logoUrl = computed(() => {
    const ruta = tienda.value.logo_url;

    if (!ruta) {
        return null;
    }

    if (/^(https?:|data:|\/storage\/)/i.test(ruta)) {
        return ruta;
    }

    return `/storage/${ruta.replace(/^\/+/, "")}`;
});

const ubicacionTienda = computed(() => {
    return [
        tienda.value.departamento,
        tienda.value.provincia,
        tienda.value.distrito,
    ]
        .filter(Boolean)
        .join(" - ");
});

/*
|--------------------------------------------------------------------------
| VENTA
|--------------------------------------------------------------------------
*/

const venta = computed(() => props.venta ?? {});

const comprobantesAdicionales = computed(() => {
    const ids = new Set([String(props.venta?.id ?? "")]);

    return props.comprobantes.filter((comprobante) => {
        const id = String(comprobante.id ?? "");

        if (!id || ids.has(id)) {
            return false;
        }

        ids.add(id);
        return true;
    });
});

/*
|--------------------------------------------------------------------------
| EMPRESA EMISORA
|--------------------------------------------------------------------------
|
| Si empresa_emisora_id tiene valor:
|   → la venta pertenece a una empresa específica.
|
| Si empresa_emisora_id es NULL:
|   → es una venta mixta y usamos los datos de la tienda.
|
*/

const empresaEmisora = computed(() => {
    return venta.value.empresa_emisora ?? null;
});

const esVentaMixta = computed(() => {
    return !venta.value.empresa_emisora_id;
});

/*
|--------------------------------------------------------------------------
| DATOS DEL EMISOR
|--------------------------------------------------------------------------
*/

const rucEmisor = computed(() => {
    if (!esVentaMixta.value && empresaEmisora.value?.ruc) {
        return empresaEmisora.value.ruc;
    }

    return tienda.value.ruc || "";
});

const razonSocialEmisor = computed(() => {
    if (!esVentaMixta.value && empresaEmisora.value?.razon_social) {
        return empresaEmisora.value.razon_social;
    }

    return (
        tienda.value.razon_social ||
        tienda.value.nombre_comercial ||
        tienda.value.nombre ||
        "MI TIENDA"
    );
});

const nombreComercialEmisor = computed(() => {
    if (!esVentaMixta.value && empresaEmisora.value?.nombre_comercial) {
        return empresaEmisora.value.nombre_comercial;
    }

    return (
        tienda.value.nombre_comercial ||
        tienda.value.nombre ||
        tienda.value.razon_social ||
        "MI TIENDA"
    );
});

const direccionEmisor = computed(() => {
    if (!esVentaMixta.value && empresaEmisora.value?.direccion) {
        return empresaEmisora.value.direccion;
    }

    return tienda.value.direccion || "";
});

const telefonoEmisor = computed(() => {
    if (!esVentaMixta.value && empresaEmisora.value?.telefono) {
        return empresaEmisora.value.telefono;
    }

    return tienda.value.telefono || "";
});

const emailEmisor = computed(() => {
    if (!esVentaMixta.value && empresaEmisora.value?.email) {
        return empresaEmisora.value.email;
    }

    return tienda.value.email || "";
});

/*
|--------------------------------------------------------------------------
| SERIE Y CORRELATIVO
|--------------------------------------------------------------------------
|
| Estos valores YA vienen generados por el backend.
| Comprobante.vue solamente los muestra.
|
*/

const serieVenta = computed(() => {
    return venta.value.serie || "";
});

const correlativoVenta = computed(() => {
    return venta.value.correlativo || "";
});

const numeroComprobante = computed(() => {
    if (!serieVenta.value && !correlativoVenta.value) {
        return "-";
    }

    return `${serieVenta.value} - ${correlativoVenta.value}`;
});

/*
|--------------------------------------------------------------------------
| CLIENTE
|--------------------------------------------------------------------------
*/

const cliente = computed(() => venta.value.cliente ?? null);

console.log("VENTA COMPLETA:", venta.value);
console.log("CLIENTE RECIBIDO:", venta.value.cliente);
console.log("CLIENTE ID:", venta.value.cliente_id);

/*
|--------------------------------------------------------------------------
| DETALLES
|--------------------------------------------------------------------------
*/

const detalles = computed(() => {
    return Array.isArray(venta.value.detalles) ? venta.value.detalles : [];
});

/*
|--------------------------------------------------------------------------
| USUARIO / CAJERO
|--------------------------------------------------------------------------
*/

const usuario = computed(() => venta.value.usuario ?? null);

/*
|--------------------------------------------------------------------------
| FORMATO MONEDA
|--------------------------------------------------------------------------
*/

const formatoMoneda = (valor) => {
    return new Intl.NumberFormat("es-PE", {
        style: "currency",
        currency: "PEN",
        minimumFractionDigits: 2,
    }).format(Number(valor) || 0);
};

/*
|--------------------------------------------------------------------------
| FECHA
|--------------------------------------------------------------------------
*/

const fechaFormateada = computed(() => {
    if (!venta.value.fecha_emision) {
        return "-";
    }

    const fecha = new Date(venta.value.fecha_emision);

    if (isNaN(fecha.getTime())) {
        return venta.value.fecha_emision;
    }

    return new Intl.DateTimeFormat("es-PE", {
        day: "2-digit",
        month: "2-digit",
        year: "numeric",
        hour: "2-digit",
        minute: "2-digit",
    }).format(fecha);
});

/*
|--------------------------------------------------------------------------
| NOMBRE TIENDA / EMISOR
|--------------------------------------------------------------------------
*/

const nombreTienda = computed(() => {
    return (
        nombreComercialEmisor.value || razonSocialEmisor.value || "MI TIENDA"
    );
});

/*
|--------------------------------------------------------------------------
| CLIENTE
|--------------------------------------------------------------------------
*/

const nombreCliente = computed(() => {
    if (!cliente.value) {
        return "CLIENTE VARIOS";
    }

    return (
        cliente.value.nombre ||
        cliente.value.razon_social ||
        cliente.value.razon_social_nombre ||
        cliente.value.nombres ||
        "CLIENTE VARIOS"
    );
});

const tipoDocumentoCliente = computed(() => {
    if (!cliente.value) {
        return "DNI";
    }

    return cliente.value.tipo_documento || cliente.value.tipoDocumento || "DNI";
});

const numeroDocumentoCliente = computed(() => {
    if (!cliente.value) {
        return "00000000";
    }

    return cliente.value.numero_documento || cliente.value.documento || "-";
});

const direccionCliente = computed(() => {
    return cliente.value?.direccion || null;
});

/*
|--------------------------------------------------------------------------
| CAJERO
|--------------------------------------------------------------------------
*/

const nombreCajero = computed(() => {
    if (!usuario.value) {
        return "-";
    }

    return usuario.value.username;
});

/*
|--------------------------------------------------------------------------
| DETALLE
|--------------------------------------------------------------------------
*/

const descripcionProducto = (detalle) => {
    return detalle.producto?.nombre || detalle.descripcion || "Producto";
};

const cantidadProducto = (detalle) => {
    return Number(detalle.cantidad) || 0;
};

const precioUnitario = (detalle) => {
    return Number(
        detalle.precio_unitario ??
            detalle.precioUnitario ??
            detalle.precio_venta ??
            0,
    );
};

const subtotalDetalle = (detalle) => {
    if (detalle.subtotal !== undefined && detalle.subtotal !== null) {
        return Number(detalle.subtotal) || 0;
    }

    return cantidadProducto(detalle) * precioUnitario(detalle);
};

/*
|--------------------------------------------------------------------------
| TOTALES
|--------------------------------------------------------------------------
*/

const subtotalVenta = computed(() => {
    if (venta.value.subtotal !== undefined && venta.value.subtotal !== null) {
        return Number(venta.value.subtotal) || 0;
    }

    return detalles.value.reduce(
        (total, detalle) => total + subtotalDetalle(detalle),
        0,
    );
});

const descuentoVenta = computed(() => {
    return Number(venta.value.descuento_total ?? venta.value.descuento ?? 0);
});

const totalVenta = computed(() => {
    if (
        venta.value.importe_total !== undefined &&
        venta.value.importe_total !== null
    ) {
        return Number(venta.value.importe_total) || 0;
    }

    if (venta.value.total !== undefined && venta.value.total !== null) {
        return Number(venta.value.total) || 0;
    }

    return subtotalVenta.value - descuentoVenta.value;
});

/*
|--------------------------------------------------------------------------
| OPERACIÓN GRAVADA
|--------------------------------------------------------------------------
*/

const opGravada = computed(() => {
    return Number(venta.value.op_gravada) || 0;
});

/*
|--------------------------------------------------------------------------
| MONTO RECIBIDO / VUELTO
|--------------------------------------------------------------------------
*/

const montoRecibido = computed(() => {
    return Number(venta.value.monto_recibido ?? venta.value.montoRecibido ?? 0);
});

const vuelto = computed(() => {
    return Number(venta.value.vuelto ?? 0);
});

/*
|--------------------------------------------------------------------------
| NÚMERO A LETRAS
|--------------------------------------------------------------------------
*/

const numeroALetras = (numero) => {
    const unidades = [
        "cero",
        "uno",
        "dos",
        "tres",
        "cuatro",
        "cinco",
        "seis",
        "siete",
        "ocho",
        "nueve",
    ];

    const especiales = [
        "diez",
        "once",
        "doce",
        "trece",
        "catorce",
        "quince",
        "dieciseis",
        "diecisiete",
        "dieciocho",
        "diecinueve",
        "veinte",
    ];

    const decenas = [
        "",
        "",
        "veinte",
        "treinta",
        "cuarenta",
        "cincuenta",
        "sesenta",
        "setenta",
        "ochenta",
        "noventa",
    ];

    const centenas = [
        "",
        "ciento",
        "doscientos",
        "trescientos",
        "cuatrocientos",
        "quinientos",
        "seiscientos",
        "setecientos",
        "ochocientos",
        "novecientos",
    ];

    const convertirMenorDeMil = (valor) => {
        if (valor < 10) {
            return unidades[valor];
        }

        if (valor <= 20) {
            return especiales[valor - 10];
        }

        if (valor < 100) {
            const decena = decenas[Math.floor(valor / 10)];

            return valor % 10 ? `${decena} y ${unidades[valor % 10]}` : decena;
        }

        if (valor === 100) {
            return "cien";
        }

        const centena = centenas[Math.floor(valor / 100)];

        const resto = valor % 100;

        return resto ? `${centena} ${convertirMenorDeMil(resto)}` : centena;
    };

    if (numero < 1000) {
        return convertirMenorDeMil(numero);
    }

    if (numero < 1000000) {
        const miles = Math.floor(numero / 1000);
        const resto = numero % 1000;

        const textoMiles = miles === 1 ? "mil" : `${numeroALetras(miles)} mil`;

        return resto ? `${textoMiles} ${numeroALetras(resto)}` : textoMiles;
    }

    const millones = Math.floor(numero / 1000000);
    const resto = numero % 1000000;

    const textoMillones =
        millones === 1 ? "un millon" : `${numeroALetras(millones)} millones`;

    return resto ? `${textoMillones} ${numeroALetras(resto)}` : textoMillones;
};

const importeEnLetras = computed(() => {
    const importe = Math.max(0, Number(totalVenta.value) || 0);

    let entero = Math.floor(importe);

    let centimos = Math.round((importe - entero) * 100);

    if (centimos === 100) {
        entero += 1;
        centimos = 0;
    }

    return `IMPORTE EN LETRAS: ${numeroALetras(
        entero,
    ).toUpperCase()} CON ${String(centimos).padStart(2, "0")}/100 SOLES`;
});

/*
|--------------------------------------------------------------------------
| FORMA DE PAGO
|--------------------------------------------------------------------------
*/

const nombreFormaPago = computed(() => {
    return (
        venta.value.tipo_pago?.nombre ||
        venta.value.tipoPago?.nombre ||
        venta.value.forma_pago ||
        "Contado"
    );
});

const imprimirTicket = () => window.print();

/*
|--------------------------------------------------------------------------
| VOLVER A NUEVA VENTA
|--------------------------------------------------------------------------
*/

const nuevaVenta = () => {
    router.visit(route("ventas.create"), {
        replace: true,
        preserveScroll: false,
        preserveState: false,
    });
};

</script>

<template>
    <AppLayout>
        <div class="p-4 md:p-6 receipt-print-scope">
            <!-- CABECERA -->

            <!-- TICKET -->

            <Card class="shadow-sm">
                <template #content>
                    <div
                        class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-5 no-print"
                    >
                        <div class="flex gap-2 no-print">
                            <Button
                                label="Volver a crear venta"
                                icon="pi pi-plus"
                                severity="secondary"
                                outlined
                                @click="nuevaVenta"
                            />

                            <Button
                                label="Imprimir"
                                icon="pi pi-print"
                                @click="imprimirTicket"
                            />
                        </div>
                    </div>
                    <div class="flex justify-center">
                        <div class="receipt-list">
                        <div class="ticket-container">
                            <!-- EMISOR -->

                            <div class="header text-center">
                                <img
                                    v-if="logoUrl"
                                    :src="logoUrl"
                                    class="logo"
                                    alt="Logo de la tienda"
                                />

                                <div class="store-name">
                                    {{ nombreTienda }}
                                </div>

                                <div
                                    v-if="
                                        !esVentaMixta &&
                                        razonSocialEmisor &&
                                        razonSocialEmisor !== nombreTienda
                                    "
                                    class="store-info"
                                >
                                    {{ razonSocialEmisor }}
                                </div>

                                <div class="store-info">
                                    <template v-if="rucEmisor">
                                        RUC:
                                        {{ rucEmisor }}<br />
                                    </template>

                                    <template v-if="direccionEmisor">
                                        {{ direccionEmisor }}<br />
                                    </template>

                                    <template v-if="ubicacionTienda">
                                        {{ ubicacionTienda }}<br />
                                    </template>

                                    <template v-if="tienda.ubigeo">
                                        Ubigeo:
                                        {{ tienda.ubigeo }}<br />
                                    </template>

                                    <template v-if="telefonoEmisor">
                                        Teléfono:
                                        {{ telefonoEmisor }}<br />
                                    </template>
                                </div>
                            </div>

                            <div class="divider"></div>

                            <!-- COMPROBANTE -->

                            <div class="text-center">
                                <div class="ticket-title">
                                    {{
                                        venta.tipo_comprobante?.nombre ||
                                        venta.tipoComprobante?.nombre ||
                                        "BOLETA DE VENTA ELECTRÓNICA"
                                    }}
                                </div>

                                <div class="bold-text text-md">
                                    {{ numeroComprobante }}
                                </div>
                            </div>

                            <div class="divider"></div>

                            <!-- INFORMACIÓN -->

                            <table class="info-table">
                                <tbody>
                                    <tr>
                                        <td class="bold-text">Fecha/Hora:</td>

                                        <td>
                                            {{ fechaFormateada }}
                                        </td>
                                    </tr>

                                    <tr>
                                        <td class="bold-text">Cajero/a:</td>

                                        <td>
                                            {{ nombreCajero }}
                                        </td>
                                    </tr>

                                    <tr>
                                        <td class="bold-text">Cliente:</td>

                                        <td>
                                            {{ nombreCliente }}
                                        </td>
                                    </tr>

                                    <tr>
                                        <td class="bold-text">
                                            {{ tipoDocumentoCliente }}:
                                        </td>

                                        <td>
                                            {{ numeroDocumentoCliente }}
                                        </td>
                                    </tr>

                                    <tr v-if="direccionCliente">
                                        <td class="bold-text">Dirección:</td>

                                        <td>
                                            {{ direccionCliente }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>

                            <div class="double-divider"></div>

                            <!-- PRODUCTOS -->

                            <table class="items-table">
                                <thead>
                                    <tr>
                                        <th class="text-left">
                                            Cant. Descrip.
                                        </th>

                                        <th class="text-right">P.Unit</th>

                                        <th class="text-right">Total</th>
                                    </tr>
                                </thead>

                                <tbody>
                                    <template
                                        v-for="(item, index) in detalles"
                                        :key="item.id ?? index"
                                    >
                                        <tr>
                                            <td colspan="3">
                                                <span class="item-name">
                                                    {{
                                                        descripcionProducto(
                                                            item,
                                                        )
                                                    }}
                                                </span>

                                                <span class="item-calc">
                                                    {{
                                                        cantidadProducto(
                                                            item,
                                                        ).toFixed(2)
                                                    }}

                                                    x

                                                    {{
                                                        formatoMoneda(
                                                            precioUnitario(
                                                                item,
                                                            ),
                                                        )
                                                    }}
                                                </span>
                                            </td>
                                        </tr>

                                        <tr class="item-totals-row">
                                            <td></td>

                                            <td class="text-right">
                                                {{
                                                    formatoMoneda(
                                                        precioUnitario(item),
                                                    )
                                                }}
                                            </td>

                                            <td class="text-right bold-text">
                                                {{
                                                    formatoMoneda(
                                                        subtotalDetalle(item),
                                                    )
                                                }}
                                            </td>
                                        </tr>
                                    </template>

                                    <tr v-if="detalles.length === 0">
                                        <td colspan="3" class="text-center">
                                            No hay productos registrados.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>

                            <div class="divider"></div>

                            <!-- TOTALES -->

                            <table class="totals-table">
                                <tbody>
                                    <tr v-if="descuentoVenta > 0">
                                        <td class="text-left">Descuento:</td>

                                        <td class="text-right">
                                            -
                                            {{ formatoMoneda(descuentoVenta) }}
                                        </td>
                                    </tr>

                                    <tr class="">

                                    </tr>

                                    <tr class="total-final">
                                        <td class="text-left">
                                            TOTAL A PAGAR:
                                        </td>

                                        <td class="text-right">
                                            {{ formatoMoneda(totalVenta) }}
                                        </td>
                                    </tr>

                                    <tr>
                                        <td colspan="2" class="amount-in-words">
                                            {{ importeEnLetras }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                            <div class="divider"></div>
                            <div>IGV aplicado: 18%</div>

                            <!-- PAGO -->

                            <div class="payment-methods">
                                <div class="bold-text text-left">
                                    Forma de Pago:
                                </div>

                                <table class="info-table">
                                    <tbody>
                                        <tr>
                                            <td>
                                                -
                                                {{ nombreFormaPago }}:
                                            </td>

                                            <td class="text-right">
                                                {{ formatoMoneda(totalVenta) }}
                                            </td>
                                        </tr>

                                        <tr
                                            v-if="montoRecibido > 0"
                                            class="border-top-dashed"
                                        >
                                            <td class="bold-text">
                                                - Entregado:
                                            </td>

                                            <td class="text-right bold-text">
                                                {{
                                                    formatoMoneda(montoRecibido)
                                                }}
                                            </td>
                                        </tr>

                                        <tr v-if="vuelto > 0">
                                            <td class="bold-text">- Vuelto:</td>

                                            <td class="text-right bold-text">
                                                {{ formatoMoneda(vuelto) }}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <div class="double-divider"></div>

                            <!-- PIE -->

                            <div class="footer text-center">
                                <div>
                                    Representación impresa de la Boleta de Venta
                                    Electrónica.
                                </div>

                                <div class="bold-text mt-1">
                                    ¡Gracias por su compra!
                                </div>
                            </div>
                        </div>

                        <div
                            v-for="comprobante in comprobantesAdicionales"
                            :key="comprobante.id"
                            class="receipt-additional-ticket"
                        >
                            <ComprobanteTicket
                                :venta="comprobante"
                                :tienda="props.tienda"
                            />
                        </div>
                        </div>
                    </div>
                </template>
            </Card>
        </div>
    </AppLayout>
</template>

<style scoped>
.ticket-container {
    width: 80mm;
    max-width: 80mm;
    min-width: 80mm;
    background: #ffffff;
    padding: 4mm;
    font-family: "Courier New", Courier, monospace;
    font-size: 11px;
    line-height: 1.2;
    color: #000000;
    box-sizing: border-box;
    box-shadow: 0 4px 14px rgba(0, 0, 0, 0.18);
    margin: 0 auto;
}

.receipt-list {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 16px;
}

.receipt-additional-ticket {
    display: block;
}

.text-center {
    text-align: center;
}

.text-right {
    text-align: right;
}

.text-left {
    text-align: left;
}

.bold-text {
    font-weight: bold;
}

.text-md {
    font-size: 13px;
}

.mt-1 {
    margin-top: 4px;
}

.divider {
    border-top: 1px dashed #000000;
    margin: 5px 0;
}

.double-divider {
    border-top: 2px double #000000;
    margin: 5px 0;
}

.logo {
    display: block;
    max-width: 45mm;
    max-height: 25mm;
    width: auto;
    height: auto;
    margin: 0 auto 2px;
    filter: grayscale(100%) contrast(200%);
}

.store-name {
    font-size: 18px;
    font-weight: bold;
    text-transform: uppercase;
    word-break: break-word;
}

.store-info {
    font-size: 10px;
    word-break: break-word;
}

.ticket-title {
    font-size: 12px;
    font-weight: bold;
    line-height: 1.2;
}

.info-table,
.totals-table,
.items-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 11px;
    table-layout: fixed;
}

.info-table td {
    padding: 1px 0;
    vertical-align: top;
    word-break: break-word;
}

.info-table td:first-child {
    width: 35%;
}

.info-table td:last-child {
    width: 65%;
}

.items-table th {
    border-bottom: 1px solid #000000;
    padding-bottom: 2px;
    font-weight: bold;
}

.items-table th:first-child {
    width: 50%;
}

.items-table th:nth-child(2) {
    width: 23%;
}

.items-table th:nth-child(3) {
    width: 27%;
}

.item-name {
    display: block;
    font-weight: bold;
    word-break: break-word;
}

.item-calc {
    font-size: 10px;
    color: #333333;
    word-break: break-word;
}

.item-totals-row td {
    padding-bottom: 4px;
}

.totals-table td {
    padding: 1px 0;
}

.total-final {
    font-size: 14px;
    font-weight: bold;
}

.amount-in-words {
    padding-top: 3px !important;
    font-size: 9px;
    line-height: 1.25;
    text-align: left;
    overflow-wrap: anywhere;
}

.border-top-dashed {
    border-top: 1px dashed #000000;
}

@media print {
    body * {
        visibility: hidden !important;
    }

    .receipt-print-scope,
    .receipt-print-scope * {
        visibility: visible !important;
    }

    .no-print {
        display: none !important;
    }

    html,
    body {
        margin: 0 !important;
        padding: 0 !important;
        height: auto !important;
        min-height: 0 !important;
        width: 80mm !important;
        background: #ffffff !important;
    }

    :global(.v-application),
    :global(.v-application__wrap),
    :global(.v-main),
    :global(.page-content) {
        display: block !important;
        height: auto !important;
        min-height: 0 !important;
        margin: 0 !important;
        padding: 0 !important;
        overflow: visible !important;
    }

    :global(.topbar),
    :global(.v-navigation-drawer) {
        display: none !important;
    }

    @page {
        size: 80mm 200mm;
        margin: 0;
    }

    .receipt-print-scope {
        position: static !important;
        width: 80mm !important;
        min-width: 80mm !important;
        max-width: 80mm !important;
        margin: 0 !important;
        padding: 0 !important;
        background: #ffffff !important;
        box-shadow: none !important;
    }

    .receipt-print-scope :deep(.p-card-body),
    .receipt-print-scope :deep(.p-card-content) {
        margin: 0 !important;
        padding: 0 !important;
    }

    .receipt-print-scope > div,
    .receipt-print-scope .p-4,
    .receipt-print-scope .md\:p-6 {
        display: block !important;
        width: 80mm !important;
        max-width: 80mm !important;
        margin: 0 !important;
        padding: 0 !important;
    }

    .ticket-container {
        width: 80mm !important;
        max-width: 80mm !important;
        min-width: 80mm !important;
        margin: 0 auto !important;
        padding: 2mm 4mm 4mm !important;
        box-shadow: none !important;
        box-sizing: border-box;
        font-size: 11px;
        position: relative;
        left: 0;
        top: 0;
    }

    .receipt-list {
        display: block !important;
    }

    .receipt-additional-ticket {
        display: block;
        break-before: page;
        page-break-before: always;
    }

    .ticket-container,
    table,
    tr,
    td,
    th {
        page-break-inside: avoid;
    }

    * {
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
}
</style>
