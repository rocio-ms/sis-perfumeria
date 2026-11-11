<script setup>
import { computed, ref } from "vue";

import { router } from "@inertiajs/vue3";

// PrimeVue
import Breadcrumb from "primevue/breadcrumb";

// Componentes propios
import DataTableBase from "@/Components/DataTableBase.vue";
import Card from "@/Components/Card.vue";
import AppLayout from "@/Layouts/AppLayout.vue";
import DetalleCliente from "./Partials/DetalleCliente.vue";

// SweetAlert
import Swal from "sweetalert2";

defineOptions({
    layout: AppLayout,
});

const props = defineProps({
    clientes: {
        type: Array,
        default: () => [],
    },
});

// ============================================================
// FORMULARIO CREAR / EDITAR
// ============================================================

const mostrarForm = ref(false);
const clienteSeleccionado = ref(null);

const mostrarDetalle = ref(false);
const clienteDetalle = ref(null);

// ============================================================
// COLUMNAS
// ============================================================
const columns = [
    {
        field: "tipo_documento",
        header: "Tipo documento",
    },
    {
        field: "numero_documento",
        header: "N.º documento",
    },
    {
        field: "razon_social_nombre",
        header: "Cliente",
    },
    {
        field: "contacto",
        header: "Contacto",
    },
    {
        field: "activo",
        header: "Estado",
        type: "status",
    },
];

const clientesConContacto = computed(() =>
    props.clientes.map((cliente) => ({
        ...cliente,
        contacto: [cliente.telefono?.trim(), cliente.email?.trim()]
            .filter(Boolean)
            .join(" · ") || null,
    })),
);

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
    clienteDetalle.value = cliente;
    mostrarDetalle.value = true;
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
                    :data="clientesConContacto"
                    :columns="columns"
                    :loading="false"
                    title-button="Nuevo cliente"
                    :show-view="true"
                    :show-delete="false"
                    :mostrarCrear="false"
                    :show-edit="false"
                    @nuevo="abrirForm"
                    @editar="editarCliente"
                    @ver="verCliente"
                    createPermission="productos.crear"
                    viewPermission="clientes.ver"
                    editPermission="productos.editar"
                    deletePermission="productos.eliminar"
                />
            </template>
        </Card>

        <Dialog
            v-model:visible="mostrarDetalle"
            header="Detalle del cliente"
            modal
            :style="{ width: '36rem', maxWidth: '92vw' }"
        >
            <DetalleCliente v-if="clienteDetalle" :cliente="clienteDetalle" />
            <template #footer>
                <Button type="button" label="Cerrar" severity="secondary" outlined @click="mostrarDetalle = false" />
            </template>
        </Dialog>
    </v-container>
</template>
