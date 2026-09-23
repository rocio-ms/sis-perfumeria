<script setup>
import { ref } from "vue";
import { router } from "@inertiajs/vue3";

import AppLayout from "@/Layouts/AppLayout.vue";
import DataTableBase from "@/Components/DataTableBase.vue";
import Card from "@/Components/Card.vue";

import Form from "./Form.vue";
import Swal from "sweetalert2";
import Show from "./Show.vue";

defineOptions({
    layout: AppLayout,
});

const props = defineProps({
    productos: {
        type: Object,
        default: () => ({
            data: [],
        }),
    },
    categorias: {
        type: Array,
        default: () => [],
    },

    marcas: {
        type: Array,
        default: () => [],
    },
    siguienteCodigo: {
        type: String,
        default: "000001",
    },
});

//Create and edit
const mostrarForm = ref(false);
const productoSeleccionado = ref(null);

//Show
const mostrarShow = ref(false);

const columns = [
    {
        field: "codigo",
        header: "Código",
    },
    {
        field: "nombre",
        header: "Producto",
    },
    {
        field: "imagen_url",
        header: "Imagen",
        type: "image",
    },
    {
        field: "precio_actual.precio_venta",
        header: "Precio venta",
        type: "currency",
    },
    {
        field: "stock_actual",
        header: "Stock",
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
const items = ref([{ label: "Productos" }]);

const abrirForm = () => {
    productoSeleccionado.value = null;
    mostrarForm.value = true;
};

const editarProducto = (producto) => {
    productoSeleccionado.value = producto;
    mostrarForm.value = true;
};

const verProducto = (producto) => {
    productoSeleccionado.value = producto;
    mostrarShow.value = true;
};

const cerrarShow = () => {
    mostrarShow.value = false;
    productoSeleccionado.value = null;
};

const cerrarForm = () => {
    mostrarForm.value = false;
    productoSeleccionado.value = null;
};

const formularioGuardado = async (tipo) => {
    mostrarForm.value = false;
    productoSeleccionado.value = null;

    await Swal.fire({
        title: tipo === "actualizado" ? "¡Actualizado!" : "¡Guardado!",
        text:
            tipo === "actualizado"
                ? "El producto fue actualizado correctamente."
                : "El producto fue registrado correctamente.",
        icon: "success",
        confirmButtonText: "Aceptar",
    });

    // Recargar productos y siguiente código
    router.reload({
        only: ["productos", "siguienteCodigo"],
    });
};

const eliminarProducto = async (producto) => {
    const result = await Swal.fire({
        title: "¿Eliminar producto?",
        text: `El producto "${producto.nombre}" será eliminado.`,
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: "Sí, eliminar",
        cancelButtonText: "Cancelar",
        reverseButtons: true,
    });

    if (!result.isConfirmed) {
        return;
    }

    router.delete(route("productos.destroy", producto.id), {
        onSuccess: async () => {
            await Swal.fire({
                title: "Eliminado",
                text: "El producto fue eliminado correctamente.",
                icon: "success",
                confirmButtonText: "Aceptar",
            });
        },

        onError: () => {
            Swal.fire({
                title: "Error",
                text: "No se pudo eliminar el producto.",
                icon: "error",
                confirmButtonText: "Aceptar",
            });
        },
    });
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
                            Productos
                        </h1>

                        <p class="text-sm text-gray-500">
                            Gestiona y consulta los productos, precios y stock
                            disponibles
                        </p>
                    </div>
                </div>
            </template>
            <template #content>
                <DataTableBase
                    title="Productos"
                    :data="props.productos.data"
                    :columns="columns"
                    :loading="false"
                    title-button="Nuevo producto"
                    :show-view="true"
                    :show-delete="false"
                    :show-edit="true"
                    :mostrarCrear="true"
                    @nuevo="abrirForm"
                    @editar="editarProducto"
                    @ver="verProducto"
                    createPermission="productos.crear"
                    viewPermission="productos.ver"
                    editPermission="productos.editar"
                    deletePermission="productos.eliminar"
                />
            </template>
        </Card>

        <Form
            v-model:visible="mostrarForm"
            :producto="productoSeleccionado"
            :categorias="props.categorias"
            :marcas="props.marcas"
            :codigo-inicial="siguienteCodigo"
            @saved="formularioGuardado"
            @cancel="cerrarForm"
        />

        <Show
            v-if="mostrarShow && productoSeleccionado"
            v-model:visible="mostrarShow"
            :producto="productoSeleccionado"
            @update:visible="cerrarShow"
        />
    </v-container>
</template>
