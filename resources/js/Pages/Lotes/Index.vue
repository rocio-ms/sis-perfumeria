<script setup>
import { ref } from "vue";
import { router } from "@inertiajs/vue3";
import AppLayout from "@/Layouts/AppLayout.vue";
import DataTableBase from "@/Components/DataTableBase.vue";

import Card from "@/Components/Card.vue";

defineOptions({
    layout: AppLayout,
});

const props = defineProps({
    lotes: {
        type: Object,
        default: () => ({ data: [], total: 0, current_page: 1, per_page: 20 }),
    },
    filtros: {
        type: Object,
        default: () => ({}),
    },
});

const cargandoLotes = ref(false);

const columns = [
    {
        field: "numero_lote",
        header: "N° de lote",
    },
    {
        field: "codigo_barras",
        header: "Código de barras del lote",
        default: "Sin código",
    },
    {
        field: "detalle_entrada.producto.nombre",
        header: "Producto",
    },
    {
        field: "detalle_entrada.producto.codigo",
        header: "Código",
    },
    {
        field: "detalle_entrada.entrada.proveedor.nombre_comercial",
        header: "Proveedor",
        default: "Sin proveedor",
    },
    {
        field: "fecha_ingreso",
        header: "Fecha de ingreso",
        type: "date",
        filter: true,
    },
    {
        field: "fecha_vencimiento",
        header: "Fecha de vencimiento",
        type: "date",
        filter: true,
        dateFormat: "month-year",
        default: "Sin vencimiento",
    },
    {
        field: "cantidad_inicial",
        header: "Cantidad inicial",
    },
    {
        field: "cantidad_actual",
        header: "Stock actual",
    },
];

const home = ref({
    icon: "pi pi-home",
});

const items = ref([{ label: "Lotes" }]);

const formatearFechaConsulta = (fecha) => {
    if (!fecha) return "";
    if (typeof fecha === "string") return fecha.slice(0, 10);

    const year = fecha.getFullYear();
    const month = String(fecha.getMonth() + 1).padStart(2, "0");
    const day = String(fecha.getDate()).padStart(2, "0");
    return `${year}-${month}-${day}`;
};

const buscarLotes = (consulta) => {
    const fechaIngreso = consulta.filters.fecha_ingreso;
    const fechaVencimiento = consulta.filters.fecha_vencimiento;

    router.get(
        route("lotes.index"),
        {
            page: consulta.page,
            per_page: consulta.rows,
            search: consulta.search,
            fecha_ingreso_desde: formatearFechaConsulta(fechaIngreso?.[0]),
            fecha_ingreso_hasta: formatearFechaConsulta(fechaIngreso?.[1]),
            fecha_vencimiento_desde: formatearFechaConsulta(fechaVencimiento?.[0]),
            fecha_vencimiento_hasta: formatearFechaConsulta(fechaVencimiento?.[1]),
            sort: consulta.sortField,
            direction: consulta.sortOrder === 1 ? "asc" : "desc",
        },
        {
            only: ["lotes", "filtros"],
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onStart: () => (cargandoLotes.value = true),
            onFinish: () => (cargandoLotes.value = false),
        },
    );
};
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
                        <h1 class="text-2xl font-bold text-gray-800">Lotes</h1>

                        <p class="text-sm text-gray-500">
                            Consulta los lotes, fechas de vencimiento y
                            cantidades disponibles de los productos.
                        </p>
                    </div>
                </div>
            </template>
            <template #content>
                <DataTableBase
                    title="Lotes"
                    :data="props.lotes.data"
                    :columns="columns"
                    server-pagination
                    :total-records="props.lotes.total"
                    :current-page="props.lotes.current_page"
                    :rows="props.lotes.per_page"
                    :server-filters="props.filtros"
                    :loading="cargandoLotes"
                    :show-view="false"
                    :show-edit="false"
                    :show-delete="false"
                    :show-create="false"
                    :show-filters="true"
                    @server-query="buscarLotes"
                />
            </template>
        </Card>
    </v-container>
</template>
