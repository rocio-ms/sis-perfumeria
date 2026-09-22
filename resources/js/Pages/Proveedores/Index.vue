<script setup>
import { ref } from "vue";
import DataTableBase from "@/Components/DataTableBase.vue";
import AppLayout from "@/Layouts/AppLayout.vue";
import Card from "@/Components/Card.vue";

import Form from "./Form.vue";
import Show from "./Show.vue";
import Swal from "sweetalert2";

defineOptions({
    layout: AppLayout,
});

const loading = ref(false);
const totalRecords = ref(0);
const currentPage = ref(1);

const mostrarForm = ref(false);
const proveedorSeleccionado = ref(null);

const mostrarShow = ref(false);

const props = defineProps({
    proveedores: {
        type: Object,
        default: () => ({
            data: [],
        }),
    },
});

const columns = [
    {
        field: "razon_social",
        header: "Razón social",
    },
    {
        field: "nombre_comercial",
        header: "Nombre comercial",
    },
    {
        field: "nombre_contacto",
        header: "Contacto",
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

const home = ref({
    icon: "pi pi-home",
});

const items = ref([{ label: "Proveedores" }]);

const verProveedor = (proveedor) => {
    proveedorSeleccionado.value = proveedor;
    mostrarShow.value = true;
};

const nuevoProveedor = () => {
    proveedorSeleccionado.value = null;
    mostrarForm.value = true;
};

const editarProveedor = (proveedor) => {
    proveedorSeleccionado.value = proveedor;
    mostrarForm.value = true;
};

const formularioGuardado = async () => {
    mostrarForm.value = false;
    proveedorSeleccionado.value = null;
};

const abrirForm = () => {
    proveedorSeleccionado.value = null;
    mostrarForm.value = true;
};

const cerrarForm = () => {
    mostrarForm.value = false;
    proveedorSeleccionado.value = null;
};


</script>

<template>
    <v-container fluid>
        <Card>
            <template #title>
                <div class="card flex jusfity-content-center">
                    <Breadcrumb :home="home" :model="items" />
                </div>
                <div
                    class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4"
                >
                    <div>
                        <h1 class="text-2xl font-bold text-gray-800">
                            Proveedores
                        </h1>

                        <p class="text-sm text-gray-500">
                            Consulta y administra la información de los
                            proveedores y sus datos de contacto.
                        </p>
                    </div>
                </div>
            </template>
            <template #content>
                <DataTableBase
                    v-model:filters="filters"
                    title="Proveedores"
                    :data="props.proveedores.data"
                    :columns="columns"
                    :loading="loading"
                    createRoute="proveedores.create"
                    titleButton="Nuevo proveedor"
                    :show-view="true"
                    :show-delete="false"
                    :mostrarCrear="true"
                    :show-filters="false"
                    @ver="verProveedor"
                    @editar="editarProveedor"
                    @nuevo="abrirForm"
                />
            </template>
        </Card>
    </v-container>

    <Form
        v-model:visible="mostrarForm"
        :proveedor="proveedorSeleccionado"
        @saved="formularioGuardado"
        @cancel="cerrarForm"
    />

    <Show v-model:visible="mostrarShow" :proveedor="proveedorSeleccionado" />
</template>
