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
const mostrarForm = ref(false);
const subcategoriaSeleccionada = ref(null);

const props = defineProps({
    subcategorias: Object,
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
        field: "categoria",
        header: "Categoría",
        type: "relationship",
        relationship: "categoria",
        displayField: "nombre",
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

const items = ref([{ label: "Subcategorías" }]);

const nuevaSubcategoria = () => {
    subcategoriaSeleccionada.value = null;
    mostrarForm.value = true;
};

const editarSubcategoria = (subcategoria) => {
    subcategoriaSeleccionada.value = subcategoria;
    mostrarForm.value = true;
};

const formularioGuardado = async () => {
    mostrarForm.value = false;
    subcategoriaSeleccionada.value = null;
};

const abrirForm = () => {
    subcategoriaSeleccionada.value = null;
    mostrarForm.value = true;
};

const cerrarForm = () => {
    mostrarForm.value = false;
    subcategoriaSeleccionada.value = null;
};
</script>

<template>
    <v-container fluid>
        <Card>
            <template #title>
                <div class="card flex justify-content-center">
                    <Breadcrumb :home="home" :model="items" />
                </div>
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-800">
                            Subcategorías
                        </h1>

                        <p class="text-sm text-gray-500">
                            Organiza mejor tus productos según categorías y subcategorías
                        </p>
                    </div>
                </div>
            </template>
            <template #content>
                <DataTableBase
                    title="Subcategorías"
                    :data="props.subcategorias.data"
                    :columns="columns"
                    :search-fields="['nombre']"
                    :loading="loading"
                    createRoute="subcategorias.create"
                    titleButton="Nueva subcategoría"
                    :show-view="false"
                    :show-delete="false"
                    :mostrarCrear="true"
                    :showFilters="false"
                    @editar="editarSubcategoria"
                    @nuevo="abrirForm"
                />
            </template>
        </Card>
    </v-container>

    <Form
        v-model:visible="mostrarForm"
        :subcategoria="subcategoriaSeleccionada"
        :categorias="props.categorias"
        @saved="formularioGuardado"
        @cancel="cerrarForm"
    />
</template>
