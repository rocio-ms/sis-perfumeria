<script setup>
import { ref, onMounted, computed } from "vue";
import { router } from "@inertiajs/vue3";

import AppLayout from "@/Layouts/AppLayout.vue";
import DataTableBase from "@/Components/DataTableBase.vue";
import Card from "@/Components/Card.vue";
import Form from "./Form.vue";
import Cierre from "./Sesiones/Cierre.vue";

import Swal from "sweetalert2";
defineOptions({
    layout: AppLayout,
});

const props = defineProps({
    cajas: {
        type: Object,
        default: () => ({
            data: [],
            current_page: 1,
            last_page: 1,
            total: 0,
        }),
    },

    filters: {
        type: Object,
        default: () => ({
            search: "",
        }),
    },
});

const mostrarForm = ref(false);
const mostrarApertura = ref(false);
const mostrarCierre = ref(false);

const cajaSeleccionada = ref(null);
const sesionSeleccionada = ref(null);

const home = ref({
    icon: "pi pi-home",
    command: () => {
        router.visit("/dashboard");
    },
});

const items = ref([
    {
        label: "Cajas",
    },
]);

const columns = [
    {
        field: "nombre",
        header: "Caja",
    },
    {
        field: "turno_label",
        header: "Turno",
    },
    {
        field: "activo_label",
        header: "Estado",
        type: "status",
        severity: (value) => {
            return value === "Activo" ? "success" : "danger";
        },
    },
    {
        field: "sesion_estado",
        header: "Sesión actual",
        type: "status",
        severity: (value) => {
            return value === "Abierta" ? "success" : "danger";
        },
    },
];

const cargarCajas = computed(() => {
    return (props.cajas?.data ?? []).map((caja) => {
        const activo = Number(caja.activo) === 1;

        return {
            ...caja,

            // Mantener el valor original
            activo,

            // Texto para el Tag
            activo_label: activo ? "Activo" : "Inactivo",

            // Turno
            turno: caja.turno,

            turno_label: caja.turno
                ? caja.turno.charAt(0).toUpperCase() + caja.turno.slice(1)
                : "",

            // Sesión
            sesion_estado: caja.sesion_abierta ? "Abierta" : "Sin sesión",

            sesion_actual: caja.sesion_abierta ?? null,
        };
    });
});

const abrirForm = () => {
    cajaSeleccionada.value = null;
    mostrarForm.value = true;
};

const editarCaja = (caja) => {
    cajaSeleccionada.value = caja;
    mostrarForm.value = true;
};

const eliminarCaja = async (caja) => {
    const resultado = await Swal.fire({
        title: "¿Eliminar caja?",
        text: `La caja "${caja.nombre}" será desactivada.`,
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: "Sí, eliminar",
        cancelButtonText: "Cancelar",
        confirmButtonColor: "#dc2626",
    });

    if (!resultado.isConfirmed) {
        return;
    }

    router.delete(route("cajas.destroy", caja.id), {
        preserveScroll: true,

        onSuccess: () => {
            Swal.fire({
                title: "Eliminada",
                text: "La caja fue desactivada correctamente.",
                icon: "success",
                confirmButtonText: "Aceptar",
            });
        },
    });
};

const formularioGuardado = () => {
    mostrarForm.value = false;
    cajaSeleccionada.value = null;

    router.reload({
        only: ["cajas"],
    });
};

const cerrarForm = () => {
    mostrarForm.value = false;
    cajaSeleccionada.value = null;
};

const abrirCaja = (caja) => {
    cajaSeleccionada.value = caja;
    mostrarApertura.value = true;
};

const aperturaRealizada = () => {
    mostrarApertura.value = false;

    cajaSeleccionada.value = null;

    router.reload({
        only: ["cajas"],
    });

    Swal.fire({
        title: "Caja abierta",
        text: "La sesión de caja fue abierta correctamente",
        icon: "success",
        confirmButtonText: "Aceptar",
    });
};

const cerrarCaja = (caja) => {
    if (!caja.sesion_actual) {
        Swal.fire({
            title: "Sin sesión abierta",
            text: "Esta caja no tiene una sesión abierta",
            icon: "info",
        });

        return;
    }

    sesionSeleccionada.value = caja.sesion_actual;
    mostrarCierre.value = true;
};

const cierreRealizado = () => {
    mostrarCierre.value = false;
    sesionSeleccionada.value = null;

    router.reload({
        only: ["cajas"],
    });

    Swal.fire({
        title: "Caja cerrada",
        text: "La sesión de caja fue cerrada correctamente.",
        icon: "success",
        confirmButtonText: "Aceptar",
    });
};
const cancelarApertura = () => {
    mostrarApertura.value = false;
    cajaSeleccionada.value = null;
};

const cancelarCierre = () => {
    mostrarCierre.value = false;
    sesionSeleccionada.value = null;
};
</script>

<template>
    <v-container fluid>
        <Card>
            <template #title>
                <div class="card flex justify-content-center mb-4">
                    <Breadcrumb :home="home" :model="items" />
                </div>

                <!-- TÍTULO -->
                <div
                    class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4"
                >
                    <div>
                        <h1 class="text-2xl font-bold text-gray-800">Cajas</h1>

                        <p class="text-sm text-gray-500">
                            Administra las cajas registradoras y controla el
                            estado de sus sesiones de apertura y cierre.
                        </p>
                    </div>
                </div>
            </template>
            <template #content>
                <DataTableBase
                    title="Cajas"
                    :data="cargarCajas"
                    :columns="columns"
                    :loading="loading"
                    :createRoute="cajas.create"
                    titleButton="Nueva caja"
                    :show-view="false"
                    :show-delete="false"
                    :mostrarCrear="true"
                    @editar="editarCaja"
                    @cerrar="cerrarCaja"
                    @nuevo="abrirForm"
                />
            </template>
        </Card>
    </v-container>

    <Form
        v-model:visible="mostrarForm"
        :caja="cajaSeleccionada"
        @saved="formularioGuardado"
        @cancel="cerrarForm"
    />

    <Cierre
        v-model:visible="mostrarCierre"
        :sesion="sesionSeleccionada"
        @confirmar-cierre="cierreRealizado"
        @update:visible="cancelarCierre"
    />
</template>
