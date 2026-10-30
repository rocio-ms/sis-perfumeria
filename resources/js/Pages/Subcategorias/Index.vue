<script setup>
import { ref } from "vue";

import AppLayout from "@/Layouts/AppLayout.vue";
import Card from "@/Components/Card.vue";
import DataTableBase from "@/Components/DataTableBase.vue";
import Form from "./Form.vue";

defineOptions({
    layout: AppLayout,
});

const props = defineProps({
    subcategorias: {
        type: Array,
        default: () => [],
    },
    categorias: {
        type: Array,
        default: () => [],
    },
});

const mostrarForm = ref(false);
const subcategoriaSeleccionada = ref(null);

const columns = [
    {
        field: "nombre",
        header: "Subcategoría",
    },
    {
        field: "categoria.nombre",
        header: "Categoría",
        filter: true,
        type: "select",
        filterOptions: props.categorias.map((categoria) => ({
            label: categoria.nombre,
            value: categoria.nombre,
        })),
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

const abrirFormulario = (subcategoria = null) => {
    subcategoriaSeleccionada.value = subcategoria;
    mostrarForm.value = true;
};

const cerrarFormulario = () => {
    mostrarForm.value = false;
    subcategoriaSeleccionada.value = null;
};
</script>

<template>
    <v-container fluid>
        <Card>
            <template #title>
                <div class="card flex justify-content-center">
                    <Breadcrumb
                        :home="{ icon: 'pi pi-home' }"
                        :model="[{ label: 'Subcategorías' }]"
                    />
                </div>
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">
                        Subcategorías
                    </h1>
                    <p class="text-sm text-gray-500">
                        Organiza los productos dentro de sus categorías.
                    </p>
                </div>
            </template>

            <template #content>
                <DataTableBase
                    title="Subcategorías"
                    :data="props.subcategorias"
                    :columns="columns"
                    :search-fields="['nombre']"
                    title-button="Nueva subcategoría"
                    :show-view="false"
                    :show-delete="false"
                    :show-filters="true"
                    :mostrar-crear="true"
                    create-permission="subcategorias.crear"
                    edit-permission="subcategorias.editar"
                    @nuevo="abrirFormulario()"
                    @editar="abrirFormulario"
                />
            </template>
        </Card>
    </v-container>

    <Form
        v-model:visible="mostrarForm"
        :subcategoria="subcategoriaSeleccionada"
        :categorias="props.categorias"
        @saved="cerrarFormulario"
        @cancel="cerrarFormulario"
    />
</template>
