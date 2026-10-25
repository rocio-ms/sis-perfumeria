<script setup>
import { ref } from "vue";

import AppLayout from "@/Layouts/AppLayout.vue";
import DataTableBase from "@/Components/DataTableBase.vue";
import Card from "@/Components/Card.vue";
import Form from "./Form.vue";

defineOptions({
    layout: AppLayout,
});

const loading = ref(false);
const totalRecords = ref(0);
const currentPage = ref(1);

const mostrarForm = ref(false);
const marcaSeleccionada = ref(null);

const props = defineProps({
    marcas: {
        type: Array,
        default: () => [],
    },

    empresas: {
        type: Array,
        default: () => [],
    },
});


const columns = [
    {
        field: "nombre",
        header: "Nombre",
    },
    {
        field: "empresa.razon_social",
        header: "Razón Social",
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

const items = ref([{ label: "Marcas" }]);

const nuevaMarca = () => {
    marcaSeleccionada.value = null;
    mostrarForm.value = true;
};

const editarMarca = (marca) => {
    marcaSeleccionada.value = marca;
    mostrarForm.value = true;
};

const formularioGuardado = async () => {
    mostrarForm.value = false;
    marcaSeleccionada.value = null;
};

const abrirForm = () => {
    marcaSeleccionada.value = null;
    mostrarForm.value = true;
};

const cerrarForm = () => {
    mostrarForm.value = false;
    marcaSeleccionada.value = null;
};
</script>

<template>
    <v-container fluid>
        <Card>
            <template #title>
                <div class="card flex justify-content-center">
                    <Breadcrumb :home="home" :model="items" />
                </div>
                <!-- TÍTULO -->
                <div
                    class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4"
                >
                    <div>
                        <h1 class="text-2xl font-bold text-gray-800">Marcas</h1>

                        <p class="text-sm text-gray-500">
                            Gestiona las marcas de los productos registrados
                        </p>
                    </div>
                </div>
            </template>
            <template #content>
                <DataTableBase
                    title="Marcas"
                    :data="props.marcas"
                    :columns="columns"
                    :loading="loading"
                    :search-fields="['nombre']"
                    createRoute="marcas.create"
                    titleButton="Nueva marca"
                    :show-view="false"
                    :show-delete="false"
                    :showFilters="false"
                    :mostrarCrear="true"
                    @editar="editarMarca"
                    @nuevo="abrirForm"
                />
            </template>
        </Card>

        <Form
            v-model:visible="mostrarForm"
            :marca="marcaSeleccionada"
            :empresas="props.empresas"
            @saved="formularioGuardado"
            @cancel="cerrarForm"
        />
    </v-container>
</template>
