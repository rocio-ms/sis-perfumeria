<script setup>
import { computed } from "vue";
import { router } from "@inertiajs/vue3";

import Card from "primevue/card";

const props = defineProps({
    entrada: {
        type: Object,
        required: true,
    },
});
const emit = defineEmits(["update:visible"]);
const entrada = computed(() => props.entrada);
const visible = defineModel("visible");

/*
|--------------------------------------------------------------------------
| Breadcrumb
|--------------------------------------------------------------------------
*/

const home = {
    icon: "pi pi-home",
    route: route("dashboard"),
};

const breadcrumbItems = [
    {
        label: "Entradas",
        route: route("entradas.index"),
    },
    {
        label: "Detalle de entrada",
    },
];

/*
|--------------------------------------------------------------------------
| Computed
|--------------------------------------------------------------------------
*/

const detalles = computed(() => {
    return props.entrada?.detalles ?? [];
});

const loteDeDetalle = (detalle) => {
    return detalle.lotes?.[0] ?? null;
};

const totalProductos = computed(() => {
    return detalles.value.length;
});

const totalUnidades = computed(() => {
    return detalles.value.reduce((total, detalle) => {
        return total + Number(detalle.cantidad || 0);
    }, 0);
});

const totalCompra = computed(() => {
    return Number(props.entrada?.total_compra || 0);
});

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

const formatearMoneda = (valor) => {
    return Number(valor || 0).toLocaleString("es-PE", {
        style: "currency",
        currency: "PEN",
    });
};

const formatearFecha = (fecha) => {
    if (!fecha) {
        return "—";
    }

    const date = new Date(fecha);

    if (isNaN(date.getTime())) {
        return "—";
    }

    return date.toLocaleDateString("es-PE", {
        day: "2-digit",
        month: "2-digit",
        year: "numeric",
    });
};

const formatearMesAnio = (fecha) => {
    if (!fecha) {
        return "—";
    }

    const date = new Date(fecha);

    if (isNaN(date.getTime())) {
        return "—";
    }

    return date.toLocaleDateString("es-PE", {
        month: "2-digit",
        year: "numeric",
    });
};

const formatearFechaHora = (fecha) => {
    if (!fecha) {
        return "—";
    }

    const date = new Date(fecha);

    if (isNaN(date.getTime())) {
        return "—";
    }

    return date.toLocaleString("es-PE", {
        day: "2-digit",
        month: "2-digit",
        year: "numeric",
        hour: "2-digit",
        minute: "2-digit",
    });
};

/*
|--------------------------------------------------------------------------
| Tipo de comprobante
|--------------------------------------------------------------------------
*/

const nombreComprobante = (tipo) => {
    const tipos = {
        factura: "Factura",
        boleta: "Boleta",
        guia_remision: "Guía de remisión",
        nota_compra: "Nota de compra",
        orden_compra: "Orden de compra",
    };

    return tipos[tipo] ?? tipo ?? "—";
};

/*
|--------------------------------------------------------------------------
| Precio final
|--------------------------------------------------------------------------
*/

const precioFinal = (detalle) => {
    const precioVenta = Number(detalle.precio_venta || 0);

    const descuento = detalle.tiene_descuento
        ? Number(detalle.descuento || 0)
        : 0;

    return Math.max(precioVenta - descuento, 0);
};

/*
|--------------------------------------------------------------------------
| Navegación
|--------------------------------------------------------------------------
*/

const volver = () => {
    router.visit(route("entradas.index"));
};

const editar = () => {
    router.visit(route("entradas.edit", props.entrada.id));
};

const cerrar = () => {
    emit("update:visible", false);
};
</script>

<template>
    <Dialog
        v-model:visible="visible"
        modal
        header="Detalle de entrada"
        :style="{ width: '95vw', maxWidth: '1400px' }"
        :breakpoints="{ '1199px': '95vw', '575px': '100vw' }"
    >
        <div class="space-y-6">
            <!-- ===================================================== -->
            <!-- INFORMACIÓN GENERAL -->
            <!-- ===================================================== -->

            <Card>
                <template #title>
                    <div class="flex items-center gap-2">
                        <i class="pi pi-file text-blue-600"></i>
                        <span>Información de la entrada</span>
                    </div>
                </template>

                <template #content>
                    <div
                        class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6"
                    >
                        <!-- Código -->
                        <div>
                            <span class="block text-sm text-gray-500 mb-1">
                                Código de ingreso
                            </span>

                            <span class="font-semibold text-gray-800">
                                {{ entrada.codigo_ingreso }}
                            </span>
                        </div>

                        <!-- Comprobante -->
                        <div>
                            <span class="block text-sm text-gray-500 mb-1">
                                Tipo de comprobante
                            </span>

                            <span class="font-semibold text-gray-800">
                                {{
                                    nombreComprobante(entrada.tipo_comprobante)
                                }}
                            </span>
                        </div>

                        <!-- Número -->
                        <div>
                            <span class="block text-sm text-gray-500 mb-1">
                                Número de comprobante
                            </span>

                            <span class="font-semibold text-gray-800">
                                {{ entrada.numero_comprobante || "Sin número" }}
                            </span>
                        </div>

                        <!-- Fecha ingreso -->
                        <div>
                            <span class="block text-sm text-gray-500 mb-1">
                                Fecha de ingreso
                            </span>

                            <span class="font-semibold text-gray-800">
                                {{ formatearFecha(entrada.fecha_ingreso) }}
                            </span>
                        </div>

                        <!-- Fecha emisión -->
                        <div>
                            <span class="block text-sm text-gray-500 mb-1">
                                Fecha de emisión
                            </span>

                            <span class="font-semibold text-gray-800">
                                {{ formatearFecha(entrada.fecha_emision) }}
                            </span>
                        </div>

                        <!-- Usuario -->
                        <div>
                            <span class="block text-sm text-gray-500 mb-1">
                                Registrado por
                            </span>

                            <span class="font-semibold text-gray-800">
                                {{
                                    entrada.user?.name ||
                                    entrada.user?.nombre ||
                                    "—"
                                }}
                            </span>
                        </div>

                        <!-- Fecha creación -->
                        <div>
                            <span class="block text-sm text-gray-500 mb-1">
                                Fecha de registro
                            </span>

                            <span class="font-semibold text-gray-800">
                                {{ formatearFechaHora(entrada.created_at) }}
                            </span>
                        </div>

                        <!-- Total -->
                        <div>
                            <span class="block text-sm text-gray-500 mb-1">
                                Total de compra
                            </span>

                            <span class="font-bold text-lg text-gray-900">
                                {{ formatearMoneda(totalCompra) }}
                            </span>
                        </div>
                    </div>

                    <!-- Observación -->

                    <div
                        v-if="entrada.observacion"
                        class="mt-6 pt-5 border-t border-gray-200"
                    >
                        <span class="block text-sm text-gray-500 mb-1">
                            Observación
                        </span>

                        <p class="text-gray-700 m-0">
                            {{ entrada.observacion }}
                        </p>
                    </div>
                </template>
            </Card>

            <!-- ===================================================== -->
            <!-- PROVEEDOR -->
            <!-- ===================================================== -->

            <Card>
                <template #title>
                    <div class="flex items-center gap-2">
                        <i class="pi pi-building text-blue-600"></i>
                        <span>Proveedor</span>
                    </div>
                </template>

                <template #content>
                    <div
                        v-if="entrada.proveedor"
                        class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6"
                    >
                        <div>
                            <span class="block text-sm text-gray-500 mb-1">
                                Nombre comercial
                            </span>

                            <span class="font-semibold text-gray-800">
                                {{ entrada.proveedor.nombre_comercial || "—" }}
                            </span>
                        </div>

                        <div>
                            <span class="block text-sm text-gray-500 mb-1">
                                Razón social
                            </span>

                            <span class="font-semibold text-gray-800">
                                {{ entrada.proveedor.razon_social || "—" }}
                            </span>
                        </div>

                        <div>
                            <span class="block text-sm text-gray-500 mb-1">
                                RUC
                            </span>

                            <span class="font-semibold text-gray-800">
                                {{ entrada.proveedor.ruc || "—" }}
                            </span>
                        </div>

                        <div>
                            <span class="block text-sm text-gray-500 mb-1">
                                Teléfono
                            </span>

                            <span class="font-semibold text-gray-800">
                                {{ entrada.proveedor.telefono || "—" }}
                            </span>
                        </div>
                    </div>

                    <div v-else class="flex items-center gap-3 text-gray-500">
                        <i class="pi pi-info-circle"></i>

                        <span>
                            Esta entrada fue registrada sin proveedor.
                        </span>
                    </div>
                </template>
            </Card>

            <!-- ===================================================== -->
            <!-- RESUMEN -->
            <!-- ===================================================== -->

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <!-- Productos -->
                <Card>
                    <template #content>
                        <div class="flex items-center gap-4">
                            <div
                                class="w-12 h-12 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center"
                            >
                                <i class="pi pi-box text-xl"></i>
                            </div>

                            <div>
                                <span class="block text-sm text-gray-500">
                                    Productos
                                </span>

                                <span class="text-2xl font-bold text-gray-800">
                                    {{ totalProductos }}
                                </span>
                            </div>
                        </div>
                    </template>
                </Card>

                <!-- Unidades -->
                <Card>
                    <template #content>
                        <div class="flex items-center gap-4">
                            <div
                                class="w-12 h-12 rounded-xl bg-green-50 text-green-600 flex items-center justify-center"
                            >
                                <i class="pi pi-box text-xl"></i>
                            </div>

                            <div>
                                <span class="block text-sm text-gray-500">
                                    Unidades ingresadas
                                </span>

                                <span class="text-2xl font-bold text-gray-800">
                                    {{ totalUnidades }}
                                </span>
                            </div>
                        </div>
                    </template>
                </Card>

                <!-- Total -->
                <Card>
                    <template #content>
                        <div class="flex items-center gap-4">
                            <div
                                class="w-12 h-12 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center"
                            >
                                <i class="pi pi-money-bill text-xl"></i>
                            </div>

                            <div>
                                <span class="block text-sm text-gray-500">
                                    Total de compra
                                </span>

                                <span class="text-2xl font-bold text-gray-800">
                                    {{ formatearMoneda(totalCompra) }}
                                </span>
                            </div>
                        </div>
                    </template>
                </Card>
            </div>

            <!-- ===================================================== -->
            <!-- PRODUCTOS -->
            <!-- ===================================================== -->

            <Card>
                <template #title>
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <i class="pi pi-shopping-bag text-blue-600"></i>

                            <span> Productos ingresados </span>
                        </div>

                        <span class="text-sm font-normal text-gray-500">
                            {{ totalProductos }}
                            {{
                                totalProductos === 1 ? "producto" : "productos"
                            }}
                        </span>
                    </div>
                </template>

                <template #content>
                    <!-- Sin productos -->

                    <div
                        v-if="detalles.length === 0"
                        class="border border-dashed border-gray-300 rounded-lg py-10 text-center"
                    >
                        <i class="pi pi-inbox text-3xl text-gray-400 mb-3"></i>

                        <p class="text-gray-500 m-0">
                            No hay productos registrados en esta entrada.
                        </p>
                    </div>

                    <!-- Tabla -->

                    <div
                        v-else
                        class="border border-gray-200 rounded-lg overflow-x-auto"
                    >
                        <table class="w-full min-w-[1200px]">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th
                                        class="text-left px-4 py-3 text-sm font-semibold text-gray-700"
                                    >
                                        Producto
                                    </th>

                                    <th
                                        class="text-center px-4 py-3 text-sm font-semibold text-gray-700"
                                    >
                                        Cantidad
                                    </th>

                                    <th
                                        class="text-right px-4 py-3 text-sm font-semibold text-gray-700"
                                    >
                                        Precio compra
                                    </th>

                                    <th
                                        class="text-right px-4 py-3 text-sm font-semibold text-gray-700"
                                    >
                                        Subtotal
                                    </th>

                                    <th
                                        class="text-right px-4 py-3 text-sm font-semibold text-gray-700"
                                    >
                                        Precio venta
                                    </th>

                                    <th
                                        class="text-center px-4 py-3 text-sm font-semibold text-gray-700"
                                    >
                                        Descuento
                                    </th>

                                    <th
                                        class="text-right px-4 py-3 text-sm font-semibold text-gray-700"
                                    >
                                        Precio final
                                    </th>

                                    <th
                                        class="text-center px-4 py-3 text-sm font-semibold text-gray-700"
                                    >
                                        Lote
                                    </th>

                                    <th
                                        class="text-center px-4 py-3 text-sm font-semibold text-gray-700"
                                    >
                                        Vencimiento
                                    </th>
                                </tr>
                            </thead>

                            <tbody>
                                <tr
                                    v-for="detalle in detalles"
                                    :key="detalle.id"
                                    class="border-t border-gray-100 hover:bg-gray-50 transition"
                                >
                                    <!-- Producto -->

                                    <td class="px-4 py-4">
                                        <div class="flex items-center gap-3">
                                            <div
                                                class="w-9 h-9 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center shrink-0"
                                            >
                                                <i class="pi pi-box"></i>
                                            </div>

                                            <div class="min-w-0">
                                                <div
                                                    class="font-semibold text-gray-800"
                                                >
                                                    {{
                                                        detalle.producto
                                                            ?.nombre ||
                                                        "Producto eliminado"
                                                    }}
                                                </div>

                                                <small class="text-gray-500">
                                                    Código:
                                                    {{
                                                        detalle.producto
                                                            ?.codigo || "—"
                                                    }}
                                                </small>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Cantidad -->

                                    <td
                                        class="px-4 py-4 text-center font-semibold"
                                    >
                                        {{ detalle.cantidad }}
                                    </td>

                                    <!-- Precio compra -->

                                    <td class="px-4 py-4 text-right">
                                        {{
                                            formatearMoneda(
                                                detalle.precio_compra,
                                            )
                                        }}
                                    </td>

                                    <!-- Subtotal -->

                                    <td
                                        class="px-4 py-4 text-right font-semibold"
                                    >
                                        {{ formatearMoneda(detalle.subtotal) }}
                                    </td>

                                    <!-- Precio venta -->

                                    <td class="px-4 py-4 text-right">
                                        {{
                                            formatearMoneda(
                                                detalle.precio_venta,
                                            )
                                        }}
                                    </td>

                                    <!-- Descuento -->

                                    <td class="px-4 py-4 text-center">
                                        <Tag
                                            v-if="detalle.tiene_descuento"
                                            :value="`-${formatearMoneda(
                                                detalle.descuento,
                                            )}`"
                                            severity="warn"
                                        />

                                        <span v-else class="text-gray-400">
                                            Sin descuento
                                        </span>
                                    </td>

                                    <!-- Precio final -->

                                    <td
                                        class="px-4 py-4 text-right font-bold text-gray-800"
                                    >
                                        {{
                                            formatearMoneda(
                                                precioFinal(detalle),
                                            )
                                        }}
                                    </td>

                                    <!-- Lote -->

                                    <td class="px-4 py-4 text-center">
                                        <Tag
                                            v-if="loteDeDetalle(detalle)"
                                            :value="
                                                loteDeDetalle(detalle)
                                                    .numero_lote
                                            "
                                            severity="info"
                                        />

                                        <span v-else class="text-gray-400">
                                            —
                                        </span>
                                    </td>

                                    <!-- Vencimiento -->

                                    <td class="px-4 py-4 text-center">
                                        <template
                                            v-if="
                                                loteDeDetalle(detalle)
                                                    ?.fecha_vencimiento
                                            "
                                        >
                                            <div
                                                class="flex flex-col items-center"
                                            >
                                                <span
                                                    class="font-medium text-gray-700"
                                                >
                                                    {{
                                                        formatearMesAnio(
                                                            loteDeDetalle(
                                                                detalle,
                                                            ).fecha_vencimiento,
                                                        )
                                                    }}
                                                </span>

                                                <small class="text-gray-400">
                                                    Fecha de vencimiento
                                                </small>
                                            </div>
                                        </template>

                                        <span v-else class="text-gray-400">
                                            No vence
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </template>
            </Card>

            <!-- ===================================================== -->
            <!-- TOTAL FINAL -->
            <!-- ===================================================== -->

            <div class="flex justify-end">
                <div
                    class="w-full sm:w-96 border border-gray-200 rounded-xl p-5 bg-gray-50"
                >
                    <div class="flex justify-between items-center">
                        <div>
                            <span class="block text-sm text-gray-500">
                                Total de compra
                            </span>

                            <span class="text-xs text-gray-400">
                                {{ totalUnidades }} unidades
                            </span>
                        </div>

                        <span class="text-2xl font-bold text-gray-900">
                            {{ formatearMoneda(totalCompra) }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- ===================================================== -->
            <!-- FOOTER -->
            <!-- ===================================================== -->

            <div class="flex justify-end pt-3 border-t border-gray-100">
                <Button
                    label="Cerrar"
                    icon="pi pi-times"
                    s
                    severity="secondary"
                    outlined
                    @click="cerrar"
                />
            </div>
        </div>
    </Dialog>
</template>
