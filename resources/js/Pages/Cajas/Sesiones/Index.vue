<script setup>
import { ref, onMounted, computed } from "vue";
import { router } from "@inertiajs/vue3";

import AppLayout from "@/Layouts/AppLayout.vue";
import Card from "@/Components/Card.vue";
import DataTableBase from "@/Components/DataTableBase.vue";
import Apertura from "./Apertura.vue";
import Cierre from "./Cierre.vue";
import Show from "./Show.vue";

defineOptions({
    layout: AppLayout,
});

const props = defineProps({
    sesiones: {
        type: Array,
        default: () => [],
    },

    cajasDisponibles: {
        type: Array,
        default: () => [],
    },

    filters: {
        type: Object,
        default: () => ({
            search: "",
        }),
    },
});

console.log("SESIONES RECIBIDAS:", props.sesiones);
console.log("PRIMERA SESIÓN:", props.sesiones?.[0]);
console.log("VENTAS PRIMERA SESIÓN:", props.sesiones?.[0]?.ventas);

console.log("CAJAS DISPONIBLES:", props.cajasDisponibles);

const mostrarApertura = ref(false);
const mostrarCierre = ref(false);

const sesionSeleccionada = ref(null);

const mostrarShow = ref(false);

const home = ref({
    icon: "pi pi-home",
    command: () => {
        router.visit("/dashboard");
    },
});

const items = ref([
    {
        label: "Sesiones de Caja",
    },
]);

const columns = [
    {
        field: "caja",
        header: "Caja",
    },
    {
        field: "usuario",
        header: "Usuario",
    },
    {
        field: "fecha_apertura",
        header: "Fecha apertura",
        type: "date",
    },
    {
        field: "fecha_cierre",
        header: "Fecha cierre",
        type: "date",
    },
    {
        field: "monto_apertura",
        header: "Monto apertura",
    },
    {
        field: "monto_cierre_real",
        header: "Monto cierre",
    },
    {
        field: "estado",
        header: "Estado",
    },
];

const sesionesTabla = computed(() => {
    return props.sesiones.map((sesion) => ({
        ...sesion,

        caja: sesion.caja?.nombre ?? "Sin caja",

        usuario: sesion.usuario?.name ?? "Sin usuario",

        estado: sesion.estado
            ? sesion.estado.charAt(0).toUpperCase() + sesion.estado.slice(1)
            : "",

        fecha_apertura: sesion.fecha_apertura,

        fecha_cierre: sesion.fecha_cierre ? sesion.fecha_cierre : "Pendiente",
        monto_apertura: Number(sesion.monto_apertura ?? 0).toFixed(2),

        monto_cierre_teorico: Number(sesion.monto_cierre_teorico ?? 0).toFixed(
            2,
        ),

        monto_cierre_real: Number(sesion.monto_cierre_real ?? 0).toFixed(2),

        diferencia: Number(sesion.diferencia ?? 0).toFixed(2),
    }));
});

const abrirApertura = () => {
    sesionSeleccionada.value = null;
    mostrarApertura.value = true;
};

const abrirCierre = (sesion) => {
    console.log("SESION SELECCIONADA PARA CIERRE:", sesion);
    sesionSeleccionada.value = sesion;
    mostrarCierre.value = true;
};

const verSesion = (sesion) => {
    console.log("ID SELECCIONADO:", sesion.id);

    const sesionOriginal = props.sesiones.find(
        (item) => item.id === sesion.id,
    );

    console.log("SESION ORIGINAL:", sesionOriginal);
    console.log("VENTAS DE SESION ORIGINAL:", sesionOriginal?.ventas);
    console.log("CANTIDAD DE VENTAS:", sesionOriginal?.ventas?.length ?? 0);

    sesionSeleccionada.value = sesionOriginal;
    mostrarShow.value = true;
};
const formularioGuardado = () => {
    mostrarApertura.value = false;
    mostrarCierre.value = false;
    mostrarShow.value = false;
    sesionSeleccionada.value = null;

    router.reload({
        only: ["sesiones"],
    });
};

const cerrarForm = () => {
    mostrarApertura.value = false;
    mostrarCierre.value = false;
    mostrarShow.value = false;
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
                        <h1 class="text-2xl font-bold text-gray-800">
                            Sesiones de Caja
                        </h1>

                        <p class="text-sm text-gray-500">
                            Administra las aperturas y cierres de caja, controla
                            los movimientos y consulta el estado de cada sesión
                        </p>
                    </div>
                    <Button
                        label="Abrir caja"
                        icon="pi pi-lock-open"
                        severity="success"
                        @click="abrirApertura"
                    />
                </div>
            </template>

            <template #content>
                <DataTableBase
                    :data="sesionesTabla"
                    :columns="columns"
                    :mostrarCrear="false"
                    :loading="false"
                    :showView="true"
                    :showEdit="false"
                    :showDelete="false"
                    :showClose="true"
                    @ver="verSesion"
                    @cerrar="abrirCierre"
                />
            </template>
        </Card>

        <!-- Apertura -->
        <Apertura
            v-model:visible="mostrarApertura"
            :cajas="props.cajasDisponibles"
            @confirmar-apertura="formularioGuardado"
            @cancel="cerrarForm"
        />

        <!-- Cierre -->
        <Cierre
            v-model:visible="mostrarCierre"
            :sesion="sesionSeleccionada"
            @confirmar-cierre="formularioGuardado"
            @cancel="cerrarForm"
        />

        <Show v-model:visible="mostrarShow" :sesion="sesionSeleccionada" />
    </v-container>
</template>
