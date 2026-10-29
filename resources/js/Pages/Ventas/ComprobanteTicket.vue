<script setup>
import { computed } from "vue";

const props = defineProps({
    venta: {
        type: Object,
        required: true,
    },
    tienda: {
        type: Object,
        default: () => ({}),
    },
});

const venta = computed(() => props.venta);
const cliente = computed(() => venta.value.cliente ?? null);
const detalles = computed(() => venta.value.detalles ?? []);
const empresa = computed(() => venta.value.empresa_emisora ?? null);
const esVentaMixta = computed(() => !venta.value.empresa_emisora_id);
const tienda = computed(() => props.tienda ?? {});

const logoUrl = computed(() => {
    const ruta = tienda.value.logo_url;

    if (!ruta) return null;
    if (/^(https?:|data:|\/storage\/)/i.test(ruta)) return ruta;

    return `/storage/${ruta.replace(/^\/+/, "")}`;
});

const nombreTienda = computed(
    () =>
        tienda.value.nombre_comercial ||
        "MI TIENDA",
);

const razonSocialEmisor = computed(
    () =>
        (!esVentaMixta.value && empresa.value?.razon_social) ||
        nombreTienda.value,
);

const rucEmisor = computed(
    () =>
        (!esVentaMixta.value && empresa.value?.ruc) ||
        "",
);

const direccionEmisor = computed(
    () => tienda.value.direccion || "",
);

const telefonoEmisor = computed(
    () => tienda.value.telefono || "",
);

const ubicacionTienda = computed(
    () =>
        [
            tienda.value.departamento,
            tienda.value.provincia,
            tienda.value.distrito,
        ]
            .filter(Boolean)
            .join(" - "),
);

const numeroComprobante = computed(
    () => `${venta.value.serie ?? ""} - ${venta.value.correlativo ?? ""}`,
);

const fechaFormateada = computed(() => {
    if (!venta.value.fecha_emision) return "-";

    const fecha = new Date(venta.value.fecha_emision);

    if (Number.isNaN(fecha.getTime())) return venta.value.fecha_emision;

    return new Intl.DateTimeFormat("es-PE", {
        day: "2-digit",
        month: "2-digit",
        year: "numeric",
        hour: "2-digit",
        minute: "2-digit",
    }).format(fecha);
});

const nombreCliente = computed(
    () =>
        cliente.value?.nombre ||
        cliente.value?.razon_social ||
        cliente.value?.razon_social_nombre ||
        cliente.value?.nombre_completo ||
        cliente.value?.nombres ||
        "CLIENTE VARIOS",
);

const tipoDocumentoCliente = computed(
    () => cliente.value?.tipo_documento || "DNI",
);

const numeroDocumentoCliente = computed(
    () => cliente.value?.numero_documento || "00000000",
);

const nombreCajero = computed(() => venta.value.usuario?.username || "-");

const formatoMoneda = (valor) =>
    new Intl.NumberFormat("es-PE", {
        style: "currency",
        currency: "PEN",
        minimumFractionDigits: 2,
    }).format(Number(valor) || 0);

const descripcionProducto = (detalle) =>
    detalle.producto?.nombre || detalle.descripcion || "Producto";

const cantidadProducto = (detalle) => Number(detalle.cantidad) || 0;

const precioUnitario = (detalle) => Number(detalle.precio_unitario) || 0;

const subtotalDetalle = (detalle) => {
    if (detalle.subtotal !== undefined && detalle.subtotal !== null) {
        return Number(detalle.subtotal) || 0;
    }

    return cantidadProducto(detalle) * precioUnitario(detalle);
};

const descuentoVenta = computed(
    () => Number(venta.value.descuento_total ?? venta.value.descuento ?? 0),
);

const totalVenta = computed(
    () => Number(venta.value.importe_total ?? venta.value.total ?? 0),
);

const nombreFormaPago = computed(
    () =>
        venta.value.tipo_pago?.nombre ||
        venta.value.tipoPago?.nombre ||
        venta.value.forma_pago ||
        "Contado",
);

const numeroALetras = (numero) => {
    const unidades = [
        "cero", "uno", "dos", "tres", "cuatro", "cinco", "seis", "siete", "ocho", "nueve",
    ];
    const especiales = [
        "diez", "once", "doce", "trece", "catorce", "quince", "dieciseis", "diecisiete", "dieciocho", "diecinueve", "veinte",
    ];
    const decenas = [
        "", "", "veinte", "treinta", "cuarenta", "cincuenta", "sesenta", "setenta", "ochenta", "noventa",
    ];
    const centenas = [
        "", "ciento", "doscientos", "trescientos", "cuatrocientos", "quinientos", "seiscientos", "setecientos", "ochocientos", "novecientos",
    ];

    const menorDeMil = (valor) => {
        if (valor < 10) return unidades[valor];
        if (valor <= 20) return especiales[valor - 10];
        if (valor < 100) {
            const decena = decenas[Math.floor(valor / 10)];
            return valor % 10 ? `${decena} y ${unidades[valor % 10]}` : decena;
        }
        if (valor === 100) return "cien";

        const centena = centenas[Math.floor(valor / 100)];
        const resto = valor % 100;

        return resto ? `${centena} ${menorDeMil(resto)}` : centena;
    };

    if (numero < 1000) return menorDeMil(numero);

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
    const importe = Math.max(0, totalVenta.value);
    let entero = Math.floor(importe);
    let centimos = Math.round((importe - entero) * 100);

    if (centimos === 100) {
        entero += 1;
        centimos = 0;
    }

    return `IMPORTE EN LETRAS: ${numeroALetras(entero).toUpperCase()} CON ${String(centimos).padStart(2, "0")}/100 SOLES`;
});

const montoRecibido = computed(
    () => Number(venta.value.monto_recibido ?? venta.value.montoRecibido ?? 0),
);

const vuelto = computed(() => Number(venta.value.vuelto ?? 0));
</script>

<template>
    <article class="ticket-container ticket-page">
        <header class="header text-center">
            <img v-if="logoUrl" :src="logoUrl" class="logo" alt="Logo de la tienda" />
            <div class="store-name">{{ nombreTienda }}</div>
            <div
                v-if="!esVentaMixta && razonSocialEmisor !== nombreTienda"
                class="store-info"
            >
                {{ razonSocialEmisor }}
            </div>
            <div class="store-info">
                <template v-if="rucEmisor">RUC: {{ rucEmisor }}<br /></template>
                <template v-if="direccionEmisor">{{ direccionEmisor }}<br /></template>
                <template v-if="ubicacionTienda">{{ ubicacionTienda }}<br /></template>
                <template v-if="tienda.ubigeo">Ubigeo: {{ tienda.ubigeo }}<br /></template>
                <template v-if="telefonoEmisor">Teléfono: {{ telefonoEmisor }}<br /></template>
            </div>
        </header>

        <div class="divider"></div>

        <div class="text-center">
            <div class="ticket-title">
                {{ venta.tipo_comprobante?.nombre || venta.tipoComprobante?.nombre || "BOLETA DE VENTA ELECTRÓNICA" }}
            </div>
            <div class="bold-text text-md">{{ numeroComprobante }}</div>
        </div>

        <div class="divider"></div>

        <table class="info-table">
            <tbody>
                <tr><td class="bold-text">Fecha/Hora:</td><td>{{ fechaFormateada }}</td></tr>
                <tr><td class="bold-text">Cajero/a:</td><td>{{ nombreCajero }}</td></tr>
                <tr><td class="bold-text">Cliente:</td><td>{{ nombreCliente }}</td></tr>
                <tr><td class="bold-text">{{ tipoDocumentoCliente }}:</td><td>{{ numeroDocumentoCliente }}</td></tr>
                <tr v-if="cliente?.direccion"><td class="bold-text">Dirección:</td><td>{{ cliente.direccion }}</td></tr>
            </tbody>
        </table>

        <div class="double-divider"></div>

        <table class="items-table">
            <thead>
                <tr>
                    <th class="text-left">Cant. Descrip.</th>
                    <th class="text-right">P.Unit</th>
                    <th class="text-right">Total</th>
                </tr>
            </thead>
            <tbody>
                <template v-for="(item, index) in detalles" :key="item.id ?? index">
                    <tr>
                        <td colspan="3">
                            <span class="item-name">{{ descripcionProducto(item) }}</span>
                            <span class="item-calc">{{ cantidadProducto(item).toFixed(2) }} x {{ formatoMoneda(precioUnitario(item)) }}</span>
                        </td>
                    </tr>
                    <tr class="item-totals-row">
                        <td></td>
                        <td class="text-right">{{ formatoMoneda(precioUnitario(item)) }}</td>
                        <td class="text-right bold-text">{{ formatoMoneda(subtotalDetalle(item)) }}</td>
                    </tr>
                </template>
                <tr v-if="detalles.length === 0">
                    <td colspan="3" class="text-center">No hay productos registrados.</td>
                </tr>
            </tbody>
        </table>

        <div class="divider"></div>

        <table class="totals-table">
            <tbody>
                <tr v-if="descuentoVenta > 0">
                    <td class="text-left">Descuento:</td>
                    <td class="text-right">- {{ formatoMoneda(descuentoVenta) }}</td>
                </tr>
                <tr class="total-final">
                    <td class="text-left">TOTAL A PAGAR:</td>
                    <td class="text-right">{{ formatoMoneda(totalVenta) }}</td>
                </tr>
                <tr><td colspan="2" class="amount-in-words">{{ importeEnLetras }}</td></tr>
            </tbody>
        </table>

        <div class="divider"></div>
        <div>IGV aplicado: 18%</div>

        <section class="payment-methods">
            <div class="bold-text text-left">Forma de Pago:</div>
            <table class="info-table">
                <tbody>
                    <tr>
                        <td>- {{ nombreFormaPago }}:</td>
                        <td class="text-right">{{ formatoMoneda(totalVenta) }}</td>
                    </tr>
                    <tr v-if="montoRecibido > 0" class="border-top-dashed">
                        <td class="bold-text">- Entregado:</td>
                        <td class="text-right bold-text">{{ formatoMoneda(montoRecibido) }}</td>
                    </tr>
                    <tr v-if="vuelto > 0">
                        <td class="bold-text">- Vuelto:</td>
                        <td class="text-right bold-text">{{ formatoMoneda(vuelto) }}</td>
                    </tr>
                </tbody>
            </table>
        </section>

        <div class="double-divider"></div>
        <footer class="footer text-center">
            <div>Representación impresa de la Boleta de Venta Electrónica.</div>
            <div class="bold-text mt-1">¡Gracias por su compra!</div>
        </footer>
    </article>
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
    margin: 0 auto 20px;
}

.ticket-page:last-child {
    break-after: auto;
    page-break-after: auto;
}

.text-center { text-align: center; }
.text-right { text-align: right; }
.text-left { text-align: left; }
.bold-text { font-weight: bold; }
.text-md { font-size: 13px; }
.mt-1 { margin-top: 4px; }
.divider { border-top: 1px dashed #000000; margin: 5px 0; }
.double-divider { border-top: 2px double #000000; margin: 5px 0; }
.logo {
    display: block;
    max-width: 45mm;
    max-height: 25mm;
    width: auto;
    height: auto;
    margin: 0 auto 2px;
    filter: grayscale(100%) contrast(200%);
}
.store-name { font-size: 18px; font-weight: bold; text-transform: uppercase; word-break: break-word; }
.store-info { font-size: 10px; word-break: break-word; }
.ticket-title { font-size: 12px; font-weight: bold; line-height: 1.2; }
.info-table, .totals-table, .items-table { width: 100%; border-collapse: collapse; font-size: 11px; table-layout: fixed; }
.info-table td { padding: 1px 0; vertical-align: top; word-break: break-word; }
.info-table td:first-child { width: 35%; }
.info-table td:last-child { width: 65%; }
.items-table th { border-bottom: 1px solid #000000; padding-bottom: 2px; font-weight: bold; }
.items-table th:first-child { width: 50%; }
.items-table th:nth-child(2) { width: 23%; }
.items-table th:nth-child(3) { width: 27%; }
.item-name { display: block; font-weight: bold; word-break: break-word; }
.item-calc { font-size: 10px; color: #333333; word-break: break-word; }
.item-totals-row td { padding-bottom: 4px; }
.totals-table td { padding: 1px 0; }
.total-final { font-size: 14px; font-weight: bold; }
.amount-in-words { padding-top: 3px !important; font-size: 9px; line-height: 1.25; text-align: left; overflow-wrap: anywhere; }
.border-top-dashed { border-top: 1px dashed #000000; }

@media print {
    @page {
        size: 80mm 200mm;
        margin: 0;
    }

    html,
    body {
        width: 80mm;
        margin: 0;
        padding: 0;
    }

    .ticket-container {
        width: 80mm;
        max-width: 80mm;
        min-width: 80mm;
        margin: 0;
        padding: 4mm;
        box-shadow: none;

        break-after: auto;
        page-break-after: auto;

        break-before: auto;
        page-break-before: auto;
    }
}
</style>