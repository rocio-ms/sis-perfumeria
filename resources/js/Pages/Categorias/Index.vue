<script setup>
import { ref } from "vue";

import DataTableBase from "@/Components/DataTableBase.vue";
import AppLayout from "@/Layouts/AppLayout.vue";
import Card from "@/Components/Card.vue";
import Form from "./Form.vue";


defineOptions({
    layout: AppLayout,
});

const loading = ref(false);
const totalRecords = ref(0);
const currentPage = ref(1);

const mostrarForm = ref(false);
const categoriaSeleccionada = ref(null);

const props = defineProps({
    categorias: {
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
        field: "descripcion",
        header: "Descripción",
        default: "Sin descripción",
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

const items = ref([{ label: "Categorías" }]);

const nuevaCategoria = () => {
    categoriaSeleccionada.value = null;
    mostrarForm.value = true;
};

const editarCategoria = (categoria) => {
    categoriaSeleccionada.value = categoria;
    mostrarForm.value = true;
};

const formularioGuardado = async () => {
    mostrarForm.value = false;
    categoriaSeleccionada.value = null;
};

const abrirForm = () => {
    categoriaSeleccionada.value = null;
    mostrarForm.value = true;
};

const cerrarForm = () => {
    mostrarForm.value = false;
    categoriaSeleccionada.value = null;
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
                        <h1 class="text-2xl font-bold text-gray-800">
                            Categorías
                        </h1>

                        <p class="text-sm text-gray-500">
                            Organiza y administra las categorías de los
                            productos registrados
                        </p>
                        
                    </div>
                </div>
            </template>
            <template #content>
                <DataTableBase
                    title="Categorías"
                    :data="props.categorias"
                    :columns="columns"
                    :search-fields="['nombre']"
                    :loading="loading"
                    createRoute="categorias.create"
                    titleButton="Nueva categoría"
                    :show-view="false"
                    :show-delete="false"
                    :mostrarCrear="true"
                    :showFilters="false"
                    @editar="editarCategoria"
                    @nuevo="abrirForm"
                />
            </template>
        </Card>
    </v-container>

    <Form
        v-model:visible="mostrarForm"
        :categoria="categoriaSeleccionada"
        @saved="formularioGuardado"
        @cancel="cerrarForm"
    />
</template>
