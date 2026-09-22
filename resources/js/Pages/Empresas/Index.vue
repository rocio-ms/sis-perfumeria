<script setup>
import { ref } from "vue";

import AppLayout from "@/Layouts/AppLayout.vue";
import DataTableBase from "@/Components/DataTableBase.vue";

import Card from "@/Components/Card.vue";

import Form from "./Form.vue";
import Show from "./Show.vue";

defineOptions({
    layout: AppLayout,
});

const loading = ref(false);

const mostrarForm = ref(false);
const empresaSeleccionada = ref(null);

const props = defineProps({
    empresas: {
        type: Object,
        default: () => ({
            data: [],
        }),
    },

    tiendas: {
        type: Array,
        default: () => [],
    },
});

const columns = [
    {
        field: "ruc",
        header: "RUC",
    },
    {
        field: "razon_social",
        header: "Razón social",
    },
    {
        field: "nombre_comercial",
        header: "Nombre comercial",
    },
    {
        field: "tienda.nombre_comercial",
        header: "Tienda",
        default: "Sin tienda",
    },
    {
        field: "telefono",
        header: "Teléfono",
        default: "Sin teléfono",
    },
    {
        field: "activo",
        header: "Estado",
        type: "status",
    },
];

// console.log(props);

const home = ref({
    icon: "pi pi-home",
});

const items = ref([
    {
        label: "Empresas",
    },
]);

const mostrarShow = ref(false);

const nuevaEmpresa = () => {
    empresaSeleccionada.value = null;
    mostrarForm.value = true;
};

const editarEmpresa = (empresa) => {
    empresaSeleccionada.value = empresa;
    mostrarForm.value = true;
};

const formularioGuardado = () => {
    mostrarForm.value = false;
    empresaSeleccionada.value = null;
};

const abrirForm = () => {
    empresaSeleccionada.value = null;
    mostrarForm.value = true;
};

const cerrarForm = () => {
    mostrarForm.value = false;
    empresaSeleccionada.value = null;
};

const verEmpresa = (empresa) => {
    empresaSeleccionada.value = empresa;
    mostrarShow.value = true;
};

const cerrarShow = () => {
    mostrarShow.value = false;
    usuarioSeleccionado.value = null;
};
</script>

<template>
    <v-container fluid>
        <Card>
            <template #title>
                <!-- BREADCRUMB -->
                <div class="card flex justify-content-center">
                    <Breadcrumb :home="home" :model="items" />
                </div>

                <!-- TÍTULO -->
                <div
                    class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4"
                >
                    <div>
                        <h1 class="text-2xl font-bold text-gray-800">
                            Empresas
                        </h1>

                        <p class="text-sm text-gray-500">
                            Gestiona las empresas asociadas a tu tienda
                        </p>
                    </div>
                </div>
            </template>

            <template #content>
                <DataTableBase
                    title="Empresas"
                    :data="props.empresas.data"
                    :columns="columns"
                    :loading="loading"
                    :search-fields="['ruc', 'razon_social', 'nombre_comercial']"
                    createRoute="empresas.create"
                    titleButton="Nueva empresa"
                    :show-view="true"
                    :show-delete="false"
                    :showFilters="false"
                    :mostrarCrear="true"
                    @editar="editarEmpresa"
                    @nuevo="abrirForm"
                    @ver="verEmpresa"
                />
            </template>
        </Card>

        <Form
            v-model:visible="mostrarForm"
            :empresa="empresaSeleccionada"
            :tiendas="props.tiendas"
            @saved="formularioGuardado"
            @cancel="cerrarForm"
        />

        <!-- SHOW -->
        <Show
            v-if="mostrarShow && empresaSeleccionada"
            v-model:visible="mostrarShow"
            :empresa="empresaSeleccionada"
            @update:visible="cerrarShow"
        />
    </v-container>
</template>
