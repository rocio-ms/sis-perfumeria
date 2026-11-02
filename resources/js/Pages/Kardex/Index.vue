<script setup>
import { ref } from "vue";
import { router } from "@inertiajs/vue3";

import DataTableBase from "@/Components/DataTableBase.vue";
import AppLayout from "@/Layouts/AppLayout.vue";
import Card from "@/Components/Card.vue";

defineOptions({
    layout: AppLayout,
});

const props = defineProps({
    kardexs: {
        type: Object,
        default: () => ({ data: [], total: 0, current_page: 1, per_page: 20 }),
    },

    productos: {
        type: Array,
        default: () => [],
    },
    tiposMovimiento: {
        type: Array,
        default: () => [],
    },
    filtros: {
        type: Object,
        default: () => ({}),
    },
});

const cargandoKardex = ref(false);

/*
|--------------------------------------------------------------------------
| COLUMNAS
|--------------------------------------------------------------------------
*/

const columns = [
    {
        field: "fecha_movimiento",
        header: "Fecha",
        type: "date",
        filter: true,
    },
    {
        field: "tipo_movimiento.nombre",
        header: "Movimiento",
        type: "status",
        filter: true,
        filterOptions: props.tiposMovimiento.map((tipo) => ({
            label: tipo.nombre,
            value: tipo.nombre,
        })),
        filterType: "select",
    },
    {
        field: "producto.nombre",
        header: "Producto",
        filter: true,
        filterOptions: props.productos.map((producto) => ({
            label: `${producto.codigo} - ${producto.nombre}`,
            value: producto.id,
        })),
        filterType: "select",
    },
    {
        field: "lote_id",
        header: "Lote",
    },
    {
        field: "cantidad",
        header: "Cantidad",
    },
    {
        field: "costo_unitario",
        header: "Costo unitario",
        type: "currency",
    },
    {
        field: "costo_total",
        header: "Costo total",
        type: "currency",
    },
    {
        field: "saldo_cantidad",
        header: "Saldo",
    },
    {
        field: "saldo_costo_unitario",
        header: "Costo saldo",
        type: "currency",
    },
    {
        field: "observacion",
        header: "Observación",
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
        label: "Kardex",
    },
]);

/*
|--------------------------------------------------------------------------
| LIMPIAR FILTROS
|--------------------------------------------------------------------------
*/

const formatearFechaConsulta = (fecha) => {
    if (!fecha) return "";
    if (typeof fecha === "string") return fecha.slice(0, 10);

    const year = fecha.getFullYear();
    const month = String(fecha.getMonth() + 1).padStart(2, "0");
    const day = String(fecha.getDate()).padStart(2, "0");
    return `${year}-${month}-${day}`;
};

const buscarKardex = (consulta) => {
    const fechas = consulta.filters.fecha_movimiento;
    router.get(
        route("kardex.index"),
        {
            page: consulta.page,
            per_page: consulta.rows,
            search: consulta.search,
            producto_id: consulta.filters["producto.nombre"],
            tipo_movimiento: consulta.filters["tipo_movimiento.nombre"],
            fecha_desde: formatearFechaConsulta(fechas?.[0]),
            fecha_hasta: formatearFechaConsulta(fechas?.[1]),
            sort: consulta.sortField,
            direction: consulta.sortOrder === 1 ? "asc" : "desc",
        },
        {
            only: ["kardexs", "filtros"],
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onStart: () => (cargandoKardex.value = true),
            onFinish: () => (cargandoKardex.value = false),
        },
    );
};
</script>

<template>
    <v-container fluid>
        <Card>
            <!-- ==========================================================
                 TÍTULO
            =========================================================== -->

            <template #title>
                <div class="card flex justify-content-center">
                    <Breadcrumb :home="home" :model="items" />
                </div>
                <div
                    class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4"
                >
                    <div>
                        <h1 class="text-2xl font-bold text-gray-800">
                            Histórico de Kardex de Inventario
                        </h1>
                        <p class="text-sm text-gray-500">
                            Consulta el historial de entradas y salidas de los
                            productos.
                        </p>
                    </div>
                </div>
            </template>

            <template #content>
                <!-- ======================================================
                     TABLA
                ======================================================= -->

                <DataTableBase
                    title="Movimientos de Kardex"
                    :data="props.kardexs.data"
                    :columns="columns"
                    server-pagination
                    :total-records="props.kardexs.total"
                    :current-page="props.kardexs.current_page"
                    :rows="props.kardexs.per_page"
                    :server-filters="props.filtros"
                    :loading="cargandoKardex"
                    :show-view="false"
                    :mostrarCrear="false"
                    :show-edit="false"
                    :show-delete="false"
                    :show-create="false"
                    :show-filters="true"
                    @server-query="buscarKardex"
                />
            </template>
        </Card>
    </v-container>
</template>
