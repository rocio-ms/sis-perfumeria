<script setup>
import { ref } from "vue";
import { router } from "@inertiajs/vue3";

import DataTableBase from "@/Components/DataTableBase.vue";
import AppLayout from "@/Layouts/AppLayout.vue";
import Card from "@/Components/Card.vue";

import Swal from "sweetalert2";

import Form from "./Form.vue";
import Show from "./Show.vue";

defineOptions({
    layout: AppLayout,
});

const props = defineProps({
    usuarios: {
        type: Object,
        default: () => ({
            data: [],
        }),
    },

    roles: {
        type: Array,
        default: () => [],
    },
});

//Formulario

const mostrarForm = ref(false);
const usuarioSeleccionado = ref(null);

// Show

const mostrarShow = ref(false);

const columns = [
    {
        field: "name",
        header: "Usuario",
    },
    {
        field: "dni",
        header: "DNI",
    },
    {
        field: "username",
        header: "Nombre de usuario",
    },

    {
        field: "roles.0.name",
        header: "Rol",
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

const items = ref([
    {
        label: "Usuarios",
    },
]);

//Nuevo usuario

const abrirForm = () => {
    usuarioSeleccionado.value = null;
    mostrarForm.value = true;
};

//Editar usuario

const editarUsuario = (usuario) => {
    usuarioSeleccionado.value = usuario;
    mostrarForm.value = true;
};

//Ver usuario

const verUsuario = (usuario) => {
    usuarioSeleccionado.value = usuario;
    mostrarShow.value = true;
};

//Cerrar form de show

const cerrarShow = () => {
    mostrarShow.value = false;
    usuarioSeleccionado.value = null;
};

//Cerrar form de crear/editar

const cerrarForm = () => {
    mostrarForm.value = false;
    usuarioSeleccionado.value = null;
};

const formularioGuardado = async (tipo) => {
    mostrarForm.value = false;
    usuarioSeleccionado.value = null;

    await Swal.fire({
        title: tipo === "actualizado" ? "¡Actualizado!" : "¡Guardado!",
        text:
            tipo === "actualizado"
                ? "El usuario fue actualizado correctamente."
                : "El usuario fue registrado correctamente.",
        icon: "success",
        confirmButtonText: "Aceptar",
    });

    router.reload({
        only: ["usuarios"],
    });
};
</script>

<template>
    <v-container fluid>
        <Card>
            <template #title>
                <!-- BREADCRUMB -->
                <div class="card flex justify-content-center mb-3">
                    <Breadcrumb :home="home" :model="items" />
                </div>

                <!-- HEADER -->
                <div
                    class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4"
                >
                    <div>
                        <h1 class="text-2xl font-bold text-gray-800">
                            Usuarios
                        </h1>

                        <p class="text-sm text-gray-500">
                            Gestiona los usuarios que tienen acceso al sistema y
                            sus roles.
                        </p>
                    </div>
                </div>
            </template>

            <template #content>
                <DataTableBase
                    title="Usuarios"
                    :data="props.usuarios.data"
                    :columns="columns"
                    :loading="false"
                    title-button="Nuevo usuario"
                    :show-view="true"
                    :show-delete="false"
                    :show-filters="false"
                    :mostrarCrear="true"
                    @nuevo="abrirForm"
                    @editar="editarUsuario"
                    @ver="verUsuario"
                />
            </template>
        </Card>

        <!-- FORM -->
        <Form
            v-model:visible="mostrarForm"
            :usuario="usuarioSeleccionado"
            :roles="props.roles"
            @saved="formularioGuardado"
            @cancel="cerrarForm"
        />

        <!-- SHOW -->
        <Show
            v-if="mostrarShow && usuarioSeleccionado"
            v-model:visible="mostrarShow"
            :usuario="usuarioSeleccionado"
            @update:visible="cerrarShow"
        />
    </v-container>
</template>
