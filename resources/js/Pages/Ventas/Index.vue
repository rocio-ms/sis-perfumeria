<script setup>
import { ref, computed } from "vue";
import { router } from "@inertiajs/vue3";

import Card from "@/Components/Card.vue";
import AppLayout from "@/Layouts/AppLayout.vue";
import DataTableBase from "@/Components/DataTableBase.vue";

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
        type: Object,
        default: () => ({
            data: [],
            current_page: 1,
            last_page: 1,
            per_page: 10,
            total: 0,
        }),
    },

    filters: {
        type: Object,
        default: () => ({
            search: "",
        }),
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
const ventaSeleccionada = ref(null);
const mostrarComprobantes = ref(false);

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
        field: "tipo_comprobante.nombre",
        header: "Comprobante",
        type: "object",
        objectField: "nombre",
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

const abrirComprobante = (comprobante) => {
    window.open(route("ventas.comprobante", comprobante.id), "_blank");
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
        new Map(boletas.map((boleta) => [boleta.id, boleta])).values(),
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
                    :data="props.ventas?.data ?? []"
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
            header="Detalle de venta"
            :style="{ width: '900px' }"
            :breakpoints="{ '1199px': '80vw', '575px': '95vw' }"
        >
            <div v-if="ventaSeleccionada" class="space-y-6">
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
                        label="Ver boletas emitidas"
                        icon="pi pi-receipt"
                        severity="info"
                        @click="verComprobante"
                    />
                </div>
            </div>
        </Dialog>

        <Dialog
            v-model:visible="mostrarComprobantes"
            modal
            header="Boletas emitidas"
            :style="{ width: '520px' }"
            :breakpoints="{ '575px': '95vw' }"
        >
            <p class="text-sm text-gray-500 mb-4">
                Esta venta generó {{ boletasVenta.length }} boleta(s).
            </p>

            <div class="flex flex-col gap-2">
                <Button
                    v-for="boleta in boletasVenta"
                    :key="boleta.id"
                    :label="`${boleta.tipo_comprobante?.nombre ?? 'Boleta'} ${boleta.serie}-${boleta.correlativo}`"
                    icon="pi pi-external-link"
                    severity="info"
                    outlined
                    class="justify-start"
                    @click="abrirComprobante(boleta)"
                />
            </div>
        </Dialog>
    </v-container>
</template>
