<script setup>
import { ref, onMounted, computed } from "vue";
import { router } from "@inertiajs/vue3";

import AppLayout from "@/Layouts/AppLayout.vue";
import Card from "@/Components/Card.vue";
import Paginator from "primevue/paginator";
import {
    Bell,
    Check,
    CheckCheck,
    Trash2,
    CircleAlert,
    Package,
    Clock,
    CircleCheck,
} from "lucide-vue-next";

import Swal from "sweetalert2";
defineOptions({
    layout: AppLayout,
});

const props = defineProps({
    notificaciones: {
        type: Object,
        default: () => ({
            data: [],
            current_page: 1,
            last_page: 1,
            total: 0,
        }),
    },
    noLeidas: {
        type: Number,
        default: 0,
    },

    tipos: {
        type: Array,
        default: () => [],
    },

    filters: {
        type: Object,
        default: () => ({
            search: "",
            tipo: "",
            estado: "",
        }),
    },
});

const search = ref(props.filters.search || "");
const tipo = ref(props.filters.tipo || "");
const estado = ref(props.filters.estado || "");

const estados = [
    {
        label: "Todas",
        value: "",
    },
    {
        label: "No leídas",
        value: "no_leidas",
    },
    {
        label: "Leídas",
        value: "leidas",
    },
    {
        label: "Pendientes",
        value: "pendientes",
    },
    {
        label: "Solucionadas",
        value: "solucionadas",
    },
];

const tiposOptions = computed(() => [
    {
        label: "Todos los tipos",
        value: "",
    },
    ...props.tipos.map((tipo) => ({
        label: formatearTipo(tipo),
        value: tipo,
    })),
]);

function formatearTipo(tipo) {
    if (!tipo) return "";

    return tipo
        .replaceAll("_", " ")
        .replace(/\b\w/g, (letra) => letra.toUpperCase());
}

function buscar() {
    router.get(
        route("notificaciones.index"),
        {
            search: search.value || undefined,
            tipo: tipo.value || undefined,
            estado: estado.value || undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
        },
    );
}

function limpiarFiltros() {
    search.value = "";
    tipo.value = "";
    estado.value = "";

    buscar();
}

function marcarLeida(notificacion) {
    router.patch(
        route("notificaciones.leida", notificacion.id),
        {},
        {
            preserveScroll: true,
        },
    );
}

function marcarTodasLeidas() {
    router.patch(
        route("notificaciones.marcarTodasLeidas"),
        {},
        {
            preserveScroll: true,
        },
    );
}

function solucionar(notificacion) {
    router.patch(
        route("notificaciones.solucionar", notificacion.id),
        {},
        {
            preserveScroll: true,
        },
    );
}

function eliminar(notificacion) {
    router.delete(route("notificaciones.destroy", notificacion.id), {
        preserveScroll: true,
    });
}

function obtenerIcono(tipo) {
    if (tipo?.includes("stock")) {
        return Package;
    }

    if (tipo?.includes("vencimiento") || tipo?.includes("vencido")) {
        return Clock;
    }

    if (tipo?.includes("error") || tipo?.includes("alerta")) {
        return CircleAlert;
    }

    if (tipo?.includes("solucion")) {
        return CircleCheck;
    }

    return Bell;
}

function obtenerSeveridad(tipo) {
    if (
        tipo?.includes("error") ||
        tipo?.includes("critico") ||
        tipo?.includes("vencido")
    ) {
        return "danger";
    }

    if (
        tipo?.includes("stock") ||
        tipo?.includes("alerta") ||
        tipo?.includes("advertencia")
    ) {
        return "warn";
    }

    if (tipo?.includes("solucion")) {
        return "success";
    }

    return "info";
}

function formatearFecha(fecha) {
    if (!fecha) return "";

    return new Date(fecha).toLocaleString("es-PE", {
        day: "2-digit",
        month: "2-digit",
        year: "numeric",
        hour: "2-digit",
        minute: "2-digit",
    });
}

const home = ref({
    icon: "pi pi-home",
    command: () => {
        router.visit("/dashboard");
    },
});

const items = ref([
    {
        label: "Notificaciones",
    },
]);
</script>

<template>
    <v-container fluid>
        <!-- CARD PRINCIPAL -->
        <Card>
            <!-- HEADER -->
            <template #title>
                <!-- Breadcrumb -->
                <div class="mb-4">
                    <Breadcrumb :home="home" :model="items" />
                </div>

                <!-- TÍTULO -->
                <div
                    class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4"
                >
                    <div>
                        <h1 class="text-2xl font-bold text-gray-800">
                            Notificaciones
                        </h1>

                        <p class="text-sm text-gray-500">
                            Alertas y avisos del sistema
                        </p>
                    </div>

                    <Button
                        v-if="noLeidas > 0"
                        label="Marcar todas como leídas"
                        icon="pi pi-check-circle"
                        severity="secondary"
                        outlined
                        @click="marcarTodasLeidas"
                    />
                </div>
            </template>

            <!-- CONTENIDO -->
            <template #content>
                <!-- ============================== -->
                <!-- RESUMEN -->
                <!-- ============================== -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                    <!-- TOTAL -->
                    <Card>
                        <template #content>
                            <div class="flex items-center gap-4">
                                <div
                                    class="w-11 h-11 rounded-xl bg-indigo-100 flex items-center justify-center"
                                >
                                    <Bell class="text-indigo-600" :size="22" />
                                </div>

                                <div>
                                    <p class="text-sm text-slate-500">Total</p>

                                    <p
                                        class="text-2xl font-bold text-slate-800"
                                    >
                                        {{ notificaciones.total }}
                                    </p>
                                </div>
                            </div>
                        </template>
                    </Card>

                    <!-- SIN LEER -->
                    <Card>
                        <template #content>
                            <div class="flex items-center gap-4">
                                <div
                                    class="w-11 h-11 rounded-xl bg-blue-100 flex items-center justify-center"
                                >
                                    <CircleAlert
                                        class="text-blue-600"
                                        :size="22"
                                    />
                                </div>

                                <div>
                                    <p class="text-sm text-slate-500">
                                        Sin leer
                                    </p>

                                    <p
                                        class="text-2xl font-bold text-slate-800"
                                    >
                                        {{ noLeidas }}
                                    </p>
                                </div>
                            </div>
                        </template>
                    </Card>

                    <!-- SOLUCIONADAS -->
                    <Card>
                        <template #content>
                            <div class="flex items-center gap-4">
                                <div
                                    class="w-11 h-11 rounded-xl bg-emerald-100 flex items-center justify-center"
                                >
                                    <CircleCheck
                                        class="text-emerald-600"
                                        :size="22"
                                    />
                                </div>

                                <div>
                                    <p class="text-sm text-slate-500">
                                        Solucionadas
                                    </p>

                                    <p
                                        class="text-2xl font-bold text-slate-800"
                                    >
                                        {{
                                            notificaciones.data.filter(
                                                (n) => n.solucionada,
                                            ).length
                                        }}
                                    </p>
                                </div>
                            </div>
                        </template>
                    </Card>
                </div>

                <!-- ============================== -->
                <!-- FILTROS -->
                <!-- ============================== -->
                <div class="mb-6">
                    <Card>
                        <template #content>
                            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                                <!-- BUSCAR -->
                                <div class="md:col-span-2">
                                    <label
                                        class="block text-sm font-medium text-slate-600 mb-2"
                                    >
                                        Buscar
                                    </label>

                                    <InputText
                                        v-model="search"
                                        placeholder="Buscar notificación..."
                                        class="w-full"
                                        @keyup.enter="buscar"
                                    />
                                </div>

                                <!-- TIPO -->
                                <div>
                                    <label
                                        class="block text-sm font-medium text-slate-600 mb-2"
                                    >
                                        Tipo
                                    </label>

                                    <Select
                                        v-model="tipo"
                                        :options="tiposOptions"
                                        optionLabel="label"
                                        optionValue="value"
                                        class="w-full"
                                    />
                                </div>

                                <!-- ESTADO -->
                                <div>
                                    <label
                                        class="block text-sm font-medium text-slate-600 mb-2"
                                    >
                                        Estado
                                    </label>

                                    <Select
                                        v-model="estado"
                                        :options="estados"
                                        optionLabel="label"
                                        optionValue="value"
                                        class="w-full"
                                    />
                                </div>
                            </div>

                            <!-- BOTONES -->
                            <div class="flex justify-end gap-2 mt-4">
                                <Button
                                    label="Limpiar"
                                    severity="secondary"
                                    text
                                    @click="limpiarFiltros"
                                />

                                <Button
                                    label="Buscar"
                                    icon="pi pi-search"
                                    @click="buscar"
                                />
                            </div>
                        </template>
                    </Card>
                </div>

                <!-- ============================== -->
                <!-- LISTA DE NOTIFICACIONES -->
                <!-- ============================== -->
                <Card>
                    <template #content>
                        <!-- SIN NOTIFICACIONES -->
                        <div
                            v-if="notificaciones.data.length === 0"
                            class="py-16 text-center"
                        >
                            <Bell
                                :size="48"
                                class="mx-auto text-slate-300 mb-4"
                            />

                            <h3 class="text-lg font-semibold text-slate-700">
                                No hay notificaciones
                            </h3>

                            <p class="text-sm text-slate-400 mt-1">
                                No encontramos notificaciones con los filtros
                                seleccionados.
                            </p>
                        </div>

                        <!-- NOTIFICACIONES -->
                        <div v-else class="space-y-3">
                            <div
                                v-for="notificacion in notificaciones.data"
                                :key="notificacion.id"
                                class="border rounded-xl p-4 transition-all"
                                :class="[
                                    !notificacion.leida
                                        ? 'bg-indigo-50/40 border-indigo-200'
                                        : 'bg-white border-slate-200',
                                ]"
                            >
                                <div class="flex gap-4">
                                    <!-- ICONO -->
                                    <div
                                        class="w-11 h-11 shrink-0 rounded-xl flex items-center justify-center"
                                        :class="[
                                            notificacion.leida
                                                ? 'bg-slate-100'
                                                : 'bg-indigo-100',
                                        ]"
                                    >
                                        <component
                                            :is="
                                                obtenerIcono(notificacion.tipo)
                                            "
                                            :size="22"
                                            :class="[
                                                notificacion.leida
                                                    ? 'text-slate-500'
                                                    : 'text-indigo-600',
                                            ]"
                                        />
                                    </div>

                                    <!-- CONTENIDO -->
                                    <div class="flex-1 min-w-0">
                                        <div
                                            class="flex flex-col md:flex-row md:items-start md:justify-between gap-2"
                                        >
                                            <div>
                                                <!-- TÍTULO + TAGS -->
                                                <div
                                                    class="flex flex-wrap items-center gap-2"
                                                >
                                                    <h3
                                                        class="font-semibold"
                                                        :class="[
                                                            !notificacion.leida
                                                                ? 'text-slate-800'
                                                                : 'text-slate-600',
                                                        ]"
                                                    >
                                                        {{
                                                            notificacion.titulo
                                                        }}
                                                    </h3>

                                                    <Tag
                                                        :value="
                                                            formatearTipo(
                                                                notificacion.tipo,
                                                            )
                                                        "
                                                        :severity="
                                                            obtenerSeveridad(
                                                                notificacion.tipo,
                                                            )
                                                        "
                                                    />

                                                    <Tag
                                                        v-if="
                                                            notificacion.solucionada
                                                        "
                                                        value="Solucionada"
                                                        severity="success"
                                                    />
                                                </div>

                                                <!-- MENSAJE -->
                                                <p
                                                    class="text-sm text-slate-600 mt-2"
                                                >
                                                    {{ notificacion.mensaje }}
                                                </p>
                                            </div>

                                            <!-- FECHA -->
                                            <span
                                                class="text-xs text-slate-400 whitespace-nowrap"
                                            >
                                                {{
                                                    formatearFecha(
                                                        notificacion.created_at,
                                                    )
                                                }}
                                            </span>
                                        </div>

                                        <!-- ACCIONES -->
                                        <div class="flex flex-wrap gap-2 mt-4">
                                            <Button
                                                v-if="!notificacion.leida"
                                                label="Marcar como leída"
                                                icon="pi pi-check"
                                                size="small"
                                                severity="secondary"
                                                text
                                                @click="
                                                    marcarLeida(notificacion)
                                                "
                                            />

                                            <Button
                                                v-if="!notificacion.solucionada"
                                                label="Marcar solucionada"
                                                icon="pi pi-check-circle"
                                                size="small"
                                                severity="success"
                                                text
                                                @click="
                                                    solucionar(notificacion)
                                                "
                                            />

                                            <Button
                                                label="Eliminar"
                                                icon="pi pi-trash"
                                                size="small"
                                                severity="danger"
                                                text
                                                @click="eliminar(notificacion)"
                                            />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- PAGINACIÓN -->
                        <div
                            v-if="notificaciones.last_page > 1"
                            class="flex justify-center mt-6"
                        >
                            <Paginator
                                :rows="notificaciones.per_page"
                                :totalRecords="notificaciones.total"
                                :first="
                                    (notificaciones.current_page - 1) *
                                    notificaciones.per_page
                                "
                                @page="
                                    (event) => {
                                        router.get(
                                            route('notificaciones.index'),
                                            {
                                                ...props.filters,
                                                page: event.page + 1,
                                            },
                                            {
                                                preserveState: true,
                                                preserveScroll: true,
                                            },
                                        );
                                    }
                                "
                            />
                        </div>
                    </template>
                </Card>
            </template>
        </Card>
    </v-container>
</template>
