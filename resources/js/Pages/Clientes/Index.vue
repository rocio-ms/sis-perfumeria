<script setup>
import { ref } from "vue";

import { router } from "@inertiajs/vue3";

// PrimeVue
import Breadcrumb from "primevue/breadcrumb";

// Componentes propios
import DataTableBase from "@/Components/DataTableBase.vue";
import Card from "@/Components/Card.vue";
import AppLayout from "@/Layouts/AppLayout.vue";

// SweetAlert
import Swal from "sweetalert2";

defineOptions({
    layout: AppLayout,
});

const props = defineProps({
    clientes: {
        type: Object,
        default: () => ({
            data: [],
        }),
    },
});

// ============================================================
// FORMULARIO CREAR / EDITAR
// ============================================================

const mostrarForm = ref(false);
const clienteSeleccionado = ref(null);

// ============================================================
// SHOW
// ============================================================

const mostrarShow = ref(false);

// ============================================================
// COLUMNAS
// ============================================================

const columns = [
    {
        field: "tipo_documento.nombre",
        header: "Tipo documento",
    },
    {
        field: "numero_documento",
        header: "N.º documento",
    },
    {
        field: "nombre_completo",
        header: "Cliente",
    },
    {
        field: "telefono",
        header: "Teléfono",
    },
    {
        field: "correo",
        header: "Correo",
    },
    {
        field: "activo",
        header: "Estado",
        type: "status",
    },
];

// ============================================================
// BREADCRUMB
// ============================================================

const home = ref({
    icon: "pi pi-home",
});

const items = ref([
    {
        label: "Clientes",
    },
]);

// ============================================================
// ABRIR FORMULARIO
// ============================================================

const abrirForm = () => {
    clienteSeleccionado.value = null;
    mostrarForm.value = true;
};

// ============================================================
// EDITAR CLIENTE
// ============================================================

const editarCliente = (cliente) => {
    clienteSeleccionado.value = cliente;
    mostrarForm.value = true;
};

// ============================================================
// VER CLIENTE
// ============================================================

const verCliente = (cliente) => {
    clienteSeleccionado.value = cliente;
    mostrarShow.value = true;
};

// ============================================================
// CERRAR SHOW
// ============================================================

const cerrarShow = () => {
    mostrarShow.value = false;
    clienteSeleccionado.value = null;
};

// ============================================================
// CERRAR FORM
// ============================================================

const cerrarForm = () => {
    mostrarForm.value = false;
    clienteSeleccionado.value = null;
};

// ============================================================
// FORMULARIO GUARDADO
// ============================================================

const formularioGuardado = async (tipo) => {
    mostrarForm.value = false;
    clienteSeleccionado.value = null;

    await Swal.fire({
        title: tipo === "actualizado" ? "¡Actualizado!" : "¡Guardado!",
        text:
            tipo === "actualizado"
                ? "El cliente fue actualizado correctamente."
                : "El cliente fue registrado correctamente.",
        icon: "success",
        confirmButtonText: "Aceptar",
    });

    router.reload({
        only: ["clientes"],
    });
};

// ============================================================
// ELIMINAR CLIENTE
// ============================================================

const eliminarCliente = async (cliente) => {
    const nombre =
        cliente.nombre_completo ??
        cliente.nombre ??
        cliente.razon_social ??
        "este cliente";

    const result = await Swal.fire({
        title: "¿Eliminar cliente?",
        text: `El cliente "${nombre}" será eliminado.`,
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: "Sí, eliminar",
        cancelButtonText: "Cancelar",
        reverseButtons: true,
    });

    if (!result.isConfirmed) {
        return;
    }

    router.delete(route("clientes.destroy", cliente.id), {
        onSuccess: async () => {
            await Swal.fire({
                title: "Eliminado",
                text: "El cliente fue eliminado correctamente.",
                icon: "success",
                confirmButtonText: "Aceptar",
            });
        },

        onError: () => {
            Swal.fire({
                title: "Error",
                text: "No se pudo eliminar el cliente.",
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
            <!-- ================================================= -->
            <!-- HEADER -->
            <!-- ================================================= -->

            <template #title>
                <div class="card flex justify-content-center">
                    <Breadcrumb :home="home" :model="items" />
                </div>

                <div
                    class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4"
                >
                    <div>
                        <h1 class="text-2xl font-bold text-gray-800">
                            Clientes
                        </h1>

                        <p class="text-sm text-gray-500">
                            Gestiona y consulta los clientes registrados.
                        </p>
                    </div>
                </div>
            </template>

            <!-- ================================================= -->
            <!-- CONTENIDO -->
            <!-- ================================================= -->

            <template #content>
                <DataTableBase
                    title="Clientes"
                    :data="props.clientes.data"
                    :columns="columns"
                    :loading="false"
                    title-button="Nuevo cliente"
                    :show-view="true"
                    :show-delete="false"
                    :mostrarCrear="false"
                    @nuevo="abrirForm"
                    @editar="editarCliente"
                    @ver="verCliente"
                    createPermission="productos.crear"
                    viewPermission="productos.ver"
                    editPermission="productos.editar"
                    deletePermission="productos.eliminar"
                />
            </template>
        </Card>
    </v-container>
</template>
