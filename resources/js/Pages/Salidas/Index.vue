<script setup>
import { ref, computed } from "vue";

import DataTableBase from "@/Components/DataTableBase.vue";
import Card from "@/Components/Card.vue";
import AppLayout from "@/Layouts/AppLayout.vue";

import Form from "./Form.vue";
import Swal from "sweetalert2";

defineOptions({
    layout: AppLayout,
});

const props = defineProps({
    salidas: {
        type: Object,
        default: () => ({
            data: [],
        }),
    },

    productos: {
        type: Array,
        default: () => [],
    },
    clientes: {
        type: Array,
        default: () => [],
    },
});

const mostrarForm = ref(false);
const salidaSeleccionada = ref(null);
const salidasFiltradas = computed(() => props.salidas?.data ?? []);
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
    | La venta se consulta desde el Historial de Ventas.
    |
    */

    if (salida.tipo_salida === "venta") {
        if (salida.venta_id) {
            window.location.href = route("ventas.show", salida.venta_id);
        }

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
    </v-container>
</template>
