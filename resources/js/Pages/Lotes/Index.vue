<script setup>
import { ref } from "vue";
import AppLayout from "@/Layouts/AppLayout.vue";
import DataTableBase from "@/Components/DataTableBase.vue";

import Card from "@/Components/Card.vue";

defineOptions({
    layout: AppLayout,
});

const loading = ref(false);

const props = defineProps({
    lotes: {
        type: Array,
        default: () => [],
    },
});

const columns = [
    {
        field: "numero_lote",
        header: "N° de lote",
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
    },
    {
        field: "fecha_vencimiento",
        header: "Fecha de vencimiento",
        type: "date",
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
                    v-model:filters="filters"
                    title="Lotes"
                    :data="props.lotes"
                    :columns="columns"
                    :loading="loading"
                    :show-view="false"
                    :show-edit="false"
                    :show-delete="false"
                    :show-create="false"
                />
            </template>
        </Card>
    </v-container>
</template>
