<script setup>
import { ref, computed } from "vue";

import DataTableBase from "@/Components/DataTableBase.vue";
import Card from "@/Components/Card.vue";
import AppLayout from "@/Layouts/AppLayout.vue";

import Form from "./Form.vue";
import ComprobanteTicket from "@/Pages/Ventas/ComprobanteTicket.vue";
import Button from "primevue/button";
import Dialog from "primevue/dialog";
import Swal from "sweetalert2";

defineOptions({
    layout: AppLayout,
});

const props = defineProps({
    salidas: {
        type: Array,
        default: () => [],
    },

    productos: {
        type: Array,
        default: () => [],
    },
    clientes: {
        type: Array,
        default: () => [],
    },
    tienda: {
        type: Object,
        default: () => ({}),
    },
});

const mostrarForm = ref(false);
const mostrarComprobanteVenta = ref(false);
const salidaSeleccionada = ref(null);
const ventaSeleccionada = ref(null);
const salidasFiltradas = computed(() => props.salidas);
const comprobantesVenta = computed(() => {
    const comprobantes = ventaSeleccionada.value?.comprobantes ?? [];
    const ventas = comprobantes.length
        ? comprobantes
        : ventaSeleccionada.value
          ? [ventaSeleccionada.value]
          : [];

    return Array.from(
        new Map(ventas.map((venta) => [String(venta.id), venta])).values(),
    );
});
/*
|--------------------------------------------------------------------------
| COLUMNAS
|--------------------------------------------------------------------------
*/

const columns = [
    {
        field: "codigo_salida",
        header: "Código",
        filter: true,
    },

    {
        field: "fecha_salida",
        header: "Fecha",
        type: "date",
        filter: true,
    },

    {
        field: "tipo_salida",
        header: "Tipo de salida",
        type: "salida",
        filter: true,
        filterOptions: [
            {
                label: "Venta Automática",
                value: "venta",
            },

            {
                label: "Consumo Interno",
                value: "consumo",
            },

            {
                label: "Merma / Daño",
                value: "merma",
            },

            {
                label: "Devolución",
                value: "devolucion",
            },
        ],
    },

    {
        field: "cliente.nombre",
        header: "Cliente / Destino",
    },

    {
        field: "total",
        header: "Total",
        type: "currency",
    },
];

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
        label: "Salidas",
    },
]);

/*
|--------------------------------------------------------------------------
| ABRIR FORMULARIO
|--------------------------------------------------------------------------
*/

const abrirForm = () => {
    salidaSeleccionada.value = null;
    mostrarForm.value = true;
};

/*
|--------------------------------------------------------------------------
| VER SALIDA
|--------------------------------------------------------------------------
*/

const verSalida = (salida) => {
    if (!salida) {
        return;
    }

    /*
    |--------------------------------------------------------------------------
    | SI ES VENTA
    |--------------------------------------------------------------------------
    |
    | Mostrar los comprobantes de la venta directamente en un diálogo.
    |
    */

    if (salida.tipo_salida === "venta") {
        if (!salida.venta) {
            Swal.fire({
                title: "Comprobante no disponible",
                text: "No se encontró la venta asociada a esta salida.",
                icon: "error",
                confirmButtonText: "Aceptar",
            });
            return;
        }

        ventaSeleccionada.value = salida.venta;
        mostrarComprobanteVenta.value = true;
        return;
    }

    /*
    |--------------------------------------------------------------------------
    | OTRAS SALIDAS
    |--------------------------------------------------------------------------
    |
    | Merma, consumo y devolución se muestran
    | desde el módulo de Salidas.
    |
    */

    salidaSeleccionada.value = salida;
    mostrarForm.value = true;
};

const cerrarDialogoComprobante = () => {
    mostrarComprobanteVenta.value = false;
    ventaSeleccionada.value = null;
};

const imprimirComprobantesVenta = () => {
    if (!comprobantesVenta.value.length) {
        return;
    }

    const limpiarModoImpresion = () => {
        document.body.classList.remove("printing-salida-receipts");
    };

    document.body.classList.add("printing-salida-receipts");
    window.addEventListener("afterprint", limpiarModoImpresion, {
        once: true,
    });
    window.print();
};

/*
|--------------------------------------------------------------------------
| CERRAR FORMULARIO
|--------------------------------------------------------------------------
*/

const cerrarForm = () => {
    mostrarForm.value = false;
    salidaSeleccionada.value = null;
};

/*
|--------------------------------------------------------------------------
| FORMULARIO GUARDADO
|--------------------------------------------------------------------------
*/

const formularioGuardado = async (tipo) => {
    mostrarForm.value = false;
    salidaSeleccionada.value = null;

    await Swal.fire({
        title: tipo === "actualizado" ? "¡Actualizado!" : "¡Guardado!",

        text:
            tipo === "actualizado"
                ? "La salida fue actualizada correctamente."
                : "La salida fue registrada correctamente.",

        icon: "success",

        confirmButtonText: "Aceptar",
    });
};
</script>

<template>
    <v-container fluid>
        <Card>
            <!-- =====================================================
                 BREADCRUMB
            ====================================================== -->

            <template #title>
                <div class="card flex justify-content-center">
                    <Breadcrumb :home="home" :model="items" />
                </div>
                <div
                    class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4"
                >
                    <div>
                        <h1 class="text-2xl font-bold text-gray-800">
                            Historial de Salidas de Inventario
                        </h1>
                        <p class="text-sm text-gray-500">
                            Consulta las salidas de inventario, ventas, mermas,
                            consumos y devoluciones.
                        </p>
                    </div>
                </div>
            </template>

            <template #content>
                <!-- =================================================
                     DATATABLE
                ================================================== -->

                <DataTableBase
                    title="Salidas"
                    :data="salidasFiltradas"
                    :columns="columns"
                    :loading="false"
                    title-button="Nueva salida"
                    :show-filters="true"
                    :show-view="true"
                    :show-edit="false"
                    :show-delete="false"
                    :mostrarCrear="true"
                    @nuevo="abrirForm"
                    @ver="verSalida"
                />
            </template>
        </Card>

        <!-- =========================================================
             FORMULARIO DE SALIDA
        ========================================================== -->

        <Form
            v-model:visible="mostrarForm"
            :salida="salidaSeleccionada"
            :productos="props.productos"
            @saved="formularioGuardado"
            @cancel="cerrarForm"
        />

        <Dialog
            v-model:visible="mostrarComprobanteVenta"
            modal
            :header="`Comprobantes emitidos (${comprobantesVenta.length})`"
            :style="{ width: '900px', maxHeight: '90vh' }"
            :breakpoints="{ '1199px': '80vw', '575px': '95vw' }"
            @hide="cerrarDialogoComprobante"
        >
            <p class="mb-4 text-sm text-gray-600">
                Se emitieron {{ comprobantesVenta.length }}
                comprobante{{ comprobantesVenta.length === 1 ? "" : "s" }}.
            </p>

            <div class="salida-receipts-print-area">
                <ComprobanteTicket
                    v-for="comprobante in comprobantesVenta"
                    :key="comprobante.id"
                    :venta="comprobante"
                    :tienda="props.tienda"
                />
            </div>

            <template #footer>
                <Button
                    label="Cerrar"
                    severity="secondary"
                    outlined
                    @click="cerrarDialogoComprobante"
                />
                <Button
                    label="Imprimir todos"
                    icon="pi pi-print"
                    @click="imprimirComprobantesVenta"
                />
            </template>
        </Dialog>
    </v-container>
</template>

<style scoped>
.salida-receipts-print-area {
    display: flex;
    flex-direction: column;
    align-items: center;
}

@media print {
    :global(body.printing-salida-receipts #app) {
        display: none !important;
    }

    :global(body.printing-salida-receipts .salida-receipts-print-area),
    :global(body.printing-salida-receipts .salida-receipts-print-area *) {
        visibility: visible !important;
    }

    :global(body.printing-salida-receipts .salida-receipts-print-area) {
        position: absolute !important;
        top: 0 !important;
        left: 0 !important;
        display: block !important;
        width: 80mm !important;
        max-height: none !important;
        overflow: visible !important;
    }

    :global(body.printing-salida-receipts .p-dialog-mask) {
        position: static !important;
        display: block !important;
        overflow: visible !important;
        background: transparent !important;
    }

    :global(body.printing-salida-receipts .p-dialog) {
        position: static !important;
        width: 80mm !important;
        max-height: none !important;
        margin: 0 !important;
        box-shadow: none !important;
        transform: none !important;
    }

    :global(body.printing-salida-receipts .p-dialog-header),
    :global(body.printing-salida-receipts .p-dialog-footer) {
        display: none !important;
    }

    :global(body.printing-salida-receipts .p-dialog-content) {
        display: block !important;
        width: 80mm !important;
        margin: 0 !important;
        padding: 0 !important;
        max-height: none !important;
        overflow: visible !important;
    }

    :global(body.printing-salida-receipts .salida-receipts-print-area > .ticket-page) {
        margin: 0 !important;
        break-inside: avoid;
        page-break-inside: avoid;
    }

    :global(html),
    :global(body.printing-salida-receipts) {
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
