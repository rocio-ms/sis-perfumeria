<script setup>
import { ref, computed } from "vue";

import DataTableBase from "@/Components/DataTableBase.vue";
import AppLayout from "@/Layouts/AppLayout.vue";
import Card from "@/Components/Card.vue";

defineOptions({
    layout: AppLayout,
});

const props = defineProps({
    kardexs: {
        type: Array,
        default: () => [],
    },

    productos: {
        type: Array,
        default: () => [],
    },
});

/*
|--------------------------------------------------------------------------
| FILTROS
|--------------------------------------------------------------------------
*/

const filtroMovimiento = ref(null);
const filtroProducto = ref(null);
const rangoFechas = ref(null);

/*
|--------------------------------------------------------------------------
| OPCIONES DE MOVIMIENTO
|--------------------------------------------------------------------------
*/

const tiposMovimiento = [
    {
        label: "Todos los movimientos",
        value: null,
    },
    {
        label: "Entrada",
        value: "entrada",
    },
    {
        label: "Salida",
        value: "salida",
    },
];

/*
|--------------------------------------------------------------------------
| OPCIONES DE PRODUCTOS
|--------------------------------------------------------------------------
*/

const productosOptions = computed(() => {
    return [
        {
            id: null,
            nombre: "Todos los productos",
        },
        ...props.productos,
    ];
});

/*
|--------------------------------------------------------------------------
| FILTRADO LOCAL
|--------------------------------------------------------------------------
|
| DataTableBase recibe los registros ya filtrados.
|
*/

const kardexFiltrado = computed(() => {
    const lista = props.kardexs;

    return lista.filter((movimiento) => {
        /*
        |--------------------------------------------------------------------------
        | FILTRO POR TIPO DE MOVIMIENTO
        |--------------------------------------------------------------------------
        */

        let coincideMovimiento = true;

        if (filtroMovimiento.value) {
            const tipo =
                movimiento.tipo_movimiento?.tipo ??
                movimiento.tipoMovimiento?.tipo ??
                movimiento.tipo_movimiento?.nombre ??
                movimiento.tipoMovimiento?.nombre ??
                "";

            coincideMovimiento =
                tipo.toString().toLowerCase() ===
                filtroMovimiento.value.toString().toLowerCase();
        }

        /*
        |--------------------------------------------------------------------------
        | FILTRO POR PRODUCTO
        |--------------------------------------------------------------------------
        */

        let coincideProducto = true;

        if (filtroProducto.value) {
            coincideProducto =
                Number(movimiento.producto_id) === Number(filtroProducto.value);
        }

        /*
        |--------------------------------------------------------------------------
        | FILTRO POR FECHAS
        |--------------------------------------------------------------------------
        */

        let coincideFecha = true;

        if (rangoFechas.value && rangoFechas.value[0]) {
            const fechaMovimiento = new Date(movimiento.fecha_movimiento);

            const desde = new Date(rangoFechas.value[0]);
            desde.setHours(0, 0, 0, 0);

            coincideFecha = fechaMovimiento >= desde;

            if (rangoFechas.value[1]) {
                const hasta = new Date(rangoFechas.value[1]);
                hasta.setHours(23, 59, 59, 999);

                coincideFecha =
                    fechaMovimiento >= desde && fechaMovimiento <= hasta;
            }
        }

        return coincideMovimiento && coincideProducto && coincideFecha;
    });
});

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
    },
    {
        field: "producto.nombre",
        header: "Producto",
        filter: true,
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

const limpiarFiltros = () => {
    filtroMovimiento.value = null;
    filtroProducto.value = null;
    rangoFechas.value = null;
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
                    :data="kardexFiltrado"
                    :columns="columns"
                    :loading="false"
                    :show-view="false"
                    :mostrarCrear="false"
                    :show-edit="false"
                    :show-delete="false"
                    :show-create="false"
                    :show-filters="true"
                />
            </template>
        </Card>
    </v-container>
</template>
