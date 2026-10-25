<script setup>
import { ref, computed } from "vue";
import { router } from "@inertiajs/vue3";

import Card from "@/Components/Card.vue";
import AppLayout from "@/Layouts/AppLayout.vue";
import DataTableBase from "@/Components/DataTableBase.vue";
import ComprobanteTicket from "./ComprobanteTicket.vue";

import Swal from "sweetalert2";

defineOptions({
    layout: AppLayout,
});

/*
|--------------------------------------------------------------------------
| PROPS
|--------------------------------------------------------------------------
*/

const props = defineProps({
    ventas: {
        type: Array,
        default: () => [],
    },

    filters: {
        type: Object,
        default: () => ({
            search: "",
        }),
    },

    tienda: {
        type: Object,
        default: () => ({}),
    },
});

/*
|--------------------------------------------------------------------------
| FILTROS LOCALES
|--------------------------------------------------------------------------
|
| No usamos directamente v-model sobre props.
|
*/

const filtros = ref({
    search: props.filters?.search ?? "",
});

//Dialogs
const mostrarDetalle = ref(false);
const mostrarComprobantes = ref(false);
const ventaSeleccionada = ref(null);

/*
|--------------------------------------------------------------------------
| BREADCRUMB
|--------------------------------------------------------------------------
*/

const home = ref({
    icon: "pi pi-home",
});

const items = ref([
    {
        label: "Ventas",
    },
]);

/*
|--------------------------------------------------------------------------
| COLUMNAS
|--------------------------------------------------------------------------
*/

const columns = [
    {
        field: "serie",
        header: "Serie",
    },

    {
        field: "correlativo",
        header: "Correlativo",
    },

    {
        field: "comprobantes_count",
        header: "Boletas",
    },

    {
        field: "fecha_emision",
        header: "Fecha de emisión",
        type: "date",
    },

    {
        field: "cliente.numero_documento",
        header: "Cliente",
        type: "object",
        objectField: "razon_social_nombre",
    },


    {
        field: "importe_total",
        header: "Total",
        type: "currency",
    },
];

/*
|--------------------------------------------------------------------------
| VER VENTA
|--------------------------------------------------------------------------
*/

const verVenta = (venta) => {
    console.log("VENTA SELECCIONADA:", venta);

    venta.detalles?.forEach((detalle) => {
        console.log("Producto:", detalle.producto?.nombre);
        console.log("Precio:", detalle.precio_unitario);
        console.log("Descuento:", detalle.descuento);
        console.log("Subtotal:", detalle.subtotal);
    });

    ventaSeleccionada.value = venta;
    mostrarComprobantes.value = false;
    mostrarDetalle.value = true;
};
const formatearFecha = (fecha) => {
    if (!fecha) return "—";

    const fechaObj = new Date(fecha);

    if (Number.isNaN(fechaObj.getTime())) {
        return "—";
    }

    return new Intl.DateTimeFormat("es-PE", {
        day: "2-digit",
        month: "2-digit",
        year: "numeric",
        hour: "2-digit",
        minute: "2-digit",
        hour12: true,
    }).format(fechaObj);
};

const verComprobante = () => {
    mostrarComprobantes.value = true;
};

const volverAlDetalle = () => {
    mostrarComprobantes.value = false;
};

const imprimirComprobantes = async () => {
    const ticket = document.querySelector(
        ".receipts-print-area .ticket-page",
    );

    if (!(ticket instanceof HTMLElement)) return;

    const iframe = document.createElement("iframe");
    iframe.title = "Vista previa del comprobante";
    Object.assign(iframe.style, {
        position: "fixed",
        left: "-10000px",
        top: "0",
        width: "80mm",
        height: "200mm",
        border: "0",
        opacity: "0",
    });
    document.body.append(iframe);

    const printDocument = iframe.contentDocument;
    const printWindow = iframe.contentWindow;

    if (!printDocument || !printWindow) {
        iframe.remove();
        return;
    }

    printDocument.open();
    printDocument.write(
        "<!doctype html><html><head><meta charset=\"utf-8\"><title>Comprobante</title></head><body></body></html>",
    );
    printDocument.close();

    document
        .querySelectorAll('link[rel="stylesheet"]')
        .forEach((stylesheet) => {
            const link = printDocument.createElement("link");
            link.rel = "stylesheet";
            link.href = stylesheet.href;
            printDocument.head.append(link);
        });

    document.querySelectorAll("style").forEach((stylesheet) => {
        printDocument.head.append(stylesheet.cloneNode(true));
    });

    const printStyles = printDocument.createElement("style");
    printStyles.textContent = `
        @page { size: 80mm 200mm; margin: 0; }
        html, body { width: 80mm; margin: 0; padding: 0; background: #fff; }
        .ticket-page { margin: 0 !important; break-inside: avoid; page-break-inside: avoid; }
    `;
    printDocument.head.append(printStyles);
    printDocument.body.append(ticket.cloneNode(true));

    await Promise.all(
        Array.from(printDocument.querySelectorAll('link[rel="stylesheet"]')).map(
            (link) =>
                link.sheet
                    ? Promise.resolve()
                    : new Promise((resolve) => {
                          link.addEventListener("load", resolve, { once: true });
                          link.addEventListener("error", resolve, { once: true });
                      }),
        ),
    );

    await Promise.all(
        Array.from(printDocument.images).map((image) =>
            image.complete
                ? Promise.resolve()
                : new Promise((resolve) => {
                      image.addEventListener("load", resolve, { once: true });
                      image.addEventListener("error", resolve, { once: true });
                  }),
        ),
    );

    printWindow.addEventListener("afterprint", () => iframe.remove(), {
        once: true,
    });
    printWindow.focus();
    printWindow.print();
};

/*
|--------------------------------------------------------------------------
| NUEVA VENTA
|--------------------------------------------------------------------------
*/

const nuevaVenta = () => {
    router.visit(route("ventas.create"));
};

/*
|--------------------------------------------------------------------------
| ANULAR VENTA
|--------------------------------------------------------------------------
*/

const eliminarVenta = async (venta) => {
    const result = await Swal.fire({
        title: "¿Anular venta?",
        text: `La venta ${venta.serie}-${venta.correlativo} será anulada.`,
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: "Sí, anular",
        cancelButtonText: "Cancelar",
        reverseButtons: true,
    });

    if (!result.isConfirmed) {
        return;
    }

    router.delete(route("ventas.destroy", venta.id), {
        onSuccess: async () => {
            await Swal.fire({
                title: "Venta anulada",
                text: "La venta fue anulada correctamente.",
                icon: "success",
                confirmButtonText: "Aceptar",
            });
        },

        onError: () => {
            Swal.fire({
                title: "Error",
                text: "No se pudo anular la venta.",
                icon: "error",
                confirmButtonText: "Aceptar",
            });
        },
    });
};

//Computed
const detallesVenta = computed(() => {
    return boletasVenta.value.flatMap((boleta) => boleta.detalles ?? []);
});

const boletasVenta = computed(() => {
    const comprobantes = ventaSeleccionada.value?.comprobantes ?? [];
    const boletas = comprobantes.length > 0
        ? comprobantes
        : ventaSeleccionada.value
          ? [ventaSeleccionada.value]
          : [];

    return Array.from(
        new Map(boletas.map((boleta) => [String(boleta.id), boleta])).values(),
    );
});

const esVentaAgrupada = computed(() => boletasVenta.value.length > 1);

const nombreBoletas = computed(() => {
    return boletasVenta.value.length === 1 ? "boleta" : "boletas";
});

const totalBoleta = (boleta) => {
    return Number(boleta.importe_total ?? boleta.total ?? 0);
};

const cantidadProductosBoleta = (boleta) => {
    return (boleta.detalles ?? []).reduce(
        (cantidad, detalle) => cantidad + Number(detalle.cantidad ?? 0),
        0,
    );
};

const totalVenta = computed(() => {
    return boletasVenta.value.reduce(
        (total, boleta) => total + totalBoleta(boleta),
        0,
    );
});
</script>

<template>
    <v-container fluid>
        <Card>
            <template #title>
                <div class="card flex justify-content-center">
                    <Breadcrumb :home="home" :model="items" />
                </div>

                <div
                    class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4"
                >
                    <div>
                        <h1 class="text-2xl font-bold text-gray-800">
                            Historial de Ventas
                        </h1>

                        <p class="text-sm text-gray-500">
                            Consulta y administra el registro de ventas
                            realizadas en tu negocio.
                        </p>
                    </div>
                </div>
            </template>

            <template #content>
                <DataTableBase
                    title="Ventas"
                    :data="props.ventas"
                    :columns="columns"
                    :loading="false"
                    title-button="Nueva venta"
                    :show-view="true"
                    :show-edit="false"
                    :show-delete="false"
                    @nuevo="nuevaVenta"
                    @ver="verVenta"
                    @eliminar="eliminarVenta"
                />
            </template>
        </Card>

        <Dialog
            v-model:visible="mostrarDetalle"
            modal
            :header="mostrarComprobantes ? 'Comprobantes emitidos' : 'Detalle de venta'"
            :style="{ width: mostrarComprobantes ? '760px' : '900px', maxHeight: '90vh' }"
            :breakpoints="{ '1199px': '80vw', '575px': '95vw' }"
            @hide="mostrarComprobantes = false"
        >
            <div v-if="ventaSeleccionada && !mostrarComprobantes" class="space-y-6">
                <!-- Información de la venta -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <p class="text-sm text-gray-500">Boletas generadas</p>

                        <p class="font-semibold text-rose-700">
                            {{ boletasVenta.length }}
                        </p>
                    </div>

                    <div>
                        <p class="text-sm text-gray-500">Fecha de emisión</p>

                        <p class="font-semibold">
                            {{ formatearFecha(ventaSeleccionada.fecha_emision) }}
                        </p>
                    </div>

                    <div>
                        <p class="text-sm text-gray-500">Cliente</p>

                        <p class="font-semibold">
                            {{
                                ventaSeleccionada.cliente
                                    ?.razon_social_nombre ?? "CLIENTES VARIOS"
                            }}
                        </p>
                    </div>
                </div>

                <!-- Productos -->
                <div>
                    <h3 class="text-lg font-semibold mb-3">
                        Productos vendidos
                    </h3>

                    <div class="border rounded-lg overflow-hidden">
                        <table class="w-full">
                            <thead class="bg-gray-100">
                                <tr>
                                    <th class="text-left px-4 py-3">
                                        Producto
                                    </th>

                                    <th class="text-center px-4 py-3">
                                        Cantidad
                                    </th>

                                    <th class="text-right px-4 py-3">Precio</th>

                                    <th class="text-right px-4 py-3">
                                        Descuento
                                    </th>

                                    <th class="text-right px-4 py-3">
                                        Subtotal
                                    </th>
                                </tr>
                            </thead>

                            <tbody>
                                <tr
                                    v-for="detalle in detallesVenta"
                                    :key="detalle.id"
                                    class="border-t"
                                >
                                    <td class="px-4 py-3">
                                        <div class="font-medium">
                                            {{
                                                detalle.producto?.nombre ??
                                                "Producto"
                                            }}
                                        </div>

                                        <small class="text-gray-500">
                                            Código:
                                            {{
                                                detalle.producto?.codigo ?? "—"
                                            }}
                                        </small>
                                    </td>

                                    <td class="text-center px-4 py-3">
                                        {{ detalle.cantidad }}
                                    </td>

                                    <td class="text-right px-4 py-3">
                                        S/
                                        {{
                                            Number(
                                                detalle.precio_unitario ?? 0,
                                            ).toFixed(2)
                                        }}
                                    </td>

                                    <!-- Descuento -->

                                    <td class="text-right px-4 py-3">
                                        <span
                                            v-if="
                                                Number(detalle.descuento ?? 0) >
                                                0
                                            "
                                            class="font-medium text-green-600"
                                        >
                                            - S/
                                            {{
                                                Number(
                                                    detalle.descuento,
                                                ).toFixed(2)
                                            }}
                                        </span>
                                        <span v-else class="text-gray-400">
                                            Sin descuento
                                        </span>
                                    </td>

                                    <!-- Subtotal -->

                                    <td
                                        class="text-right px-4 py-3 font-medium"
                                    >
                                        S/
                                        {{
                                            Number(
                                                detalle.subtotal ?? 0,
                                            ).toFixed(2)
                                        }}
                                    </td>
                                </tr>

                                <tr v-if="detallesVenta.length === 0">
                                    <td
                                        colspan="4"
                                        class="text-center py-6 text-gray-500"
                                    >
                                        No hay productos registrados.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Total -->
                <div class="flex justify-end">
                    <div class="text-right">
                        <p class="text-sm text-gray-500">Total de la venta</p>

                        <p class="text-2xl font-bold">
                            S/ {{ totalVenta.toFixed(2) }}
                        </p>
                    </div>
                </div>

                <!-- Botón de boletas -->
                <div class="flex justify-end border-t pt-4">
                    <Button
                        :label="
                            boletasVenta.length > 1
                                ? 'Ver todos los comprobantes'
                                : 'Ver comprobante'
                        "
                        icon="pi pi-receipt"
                        severity="info"
                        @click="verComprobante"
                    />
                </div>
            </div>

            <div v-else-if="ventaSeleccionada" class="space-y-4">
                <div class="flex justify-between gap-3 no-print">
                    <Button
                        label="Volver al detalle"
                        icon="pi pi-arrow-left"
                        severity="secondary"
                        outlined
                        @click="volverAlDetalle"
                    />
                    <Button
                        label="Imprimir comprobante"
                        icon="pi pi-print"
                        @click="imprimirComprobantes"
                    />
                </div>

                <div class="receipts-print-area">
                    <ComprobanteTicket
                        v-if="ventaSeleccionada"
                        :key="ventaSeleccionada.id"
                        :venta="ventaSeleccionada"
                        :tienda="props.tienda"
                    />
                </div>
            </div>
        </Dialog>

    </v-container>
</template>

<style scoped>
@media print {
    :global(body.printing-receipts #app) {
        display: none !important;
    }

    :global(body.printing-receipts .receipts-print-area) {
        position: absolute !important;
        top: 0 !important;
        left: 0 !important;
        display: block !important;
        width: 80mm;
        max-height: none !important;
        overflow: visible !important;
        margin: 0 !important;
    }

    :global(body.printing-receipts .p-dialog-mask) {
        position: static !important;
        display: block !important;
        overflow: visible !important;
        background: transparent !important;
    }

    :global(body.printing-receipts .p-dialog) {
        position: static !important;
        width: 80mm !important;
        max-height: none !important;
        margin: 0 !important;
        box-shadow: none !important;
        transform: none !important;
    }

    :global(body.printing-receipts .p-dialog-header),
    :global(body.printing-receipts .p-dialog-footer) {
        display: none !important;
    }

    :global(body.printing-receipts .no-print) {
        display: none !important;
    }

    :global(body.printing-receipts .p-dialog-content) {
        display: block !important;
        width: 80mm !important;
        margin: 0 !important;
        padding: 0 !important;
        max-height: none !important;
        overflow: visible !important;
    }

    :global(body.printing-receipts .receipts-print-area > .ticket-page) {
        margin: 0 !important;
        break-inside: avoid;
        page-break-inside: avoid;
    }

    :global(html),
    :global(body.printing-receipts) {
        margin: 0 !important;
        padding: 0 !important;
        width: 80mm !important;
    }

    @page {
        size: 80mm 200mm;
        margin: 0;
    }
}
</style>
