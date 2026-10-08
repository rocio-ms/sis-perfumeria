<script setup>
import { ref, computed, watch } from "vue";
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

watch(tipo, () => {
    aplicarFiltros();
});

watch(estado, () => {
    aplicarFiltros();
});

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

    const nombres = {
        stock_bajo: "Stock bajo",
        vencimiento: "Próximo a vencer",
        vencido: "Producto vencido",
    };

    return nombres[tipo] || tipo;
}

let searchTimeout = null;

function aplicarFiltros() {
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
            replace: true,
        },
    );
}

function buscar() {
    clearTimeout(searchTimeout);

    searchTimeout = setTimeout(() => {
        aplicarFiltros();
    }, 400);
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
            preserveState: false,
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
                <!-- FILTROS -->
                <!-- ============================== -->

                <div class="flex flex-col md:flex-row gap-3 mb-6">
                    <!-- BUSCADOR -->
                    <IconField class="w-full md:flex-1">
                        <InputIcon class="pi pi-search" />

                        <InputText
                            v-model="search"
                            placeholder="Buscar producto o código..."
                            class="w-full"
                            @input="buscar"
                        />
                    </IconField>

                    <!-- TIPO -->
                    <Select
                        v-model="tipo"
                        :options="tiposOptions"
                        optionLabel="label"
                        optionValue="value"
                        placeholder="Todos los tipos"
                        class="w-full md:w-64"
                    />

                    <!-- ESTADO -->
                    <Select
                        v-model="estado"
                        :options="estados"
                        optionLabel="label"
                        optionValue="value"
                        placeholder="Todos los estados"
                        class="w-full md:w-64"
                    />
                </div>

                <!-- ============================== -->
                <!-- LISTA DE NOTIFICACIONES -->
                <!-- ============================== -->
                <div>
                    <!-- SIN NOTIFICACIONES -->
                    <div
                        v-if="notificaciones.data.length === 0"
                        class="py-16 text-center"
                    >
                        <Bell :size="48" class="mx-auto text-slate-300 mb-4" />

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
                        <Card
                            v-for="notificacion in notificaciones.data"
                            :key="notificacion.id"
                        >
                            <template #content>
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
                            </template>
                        </Card>
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
                                        search: search || undefined,
                                        tipo: tipo || undefined,
                                        estado: estado || undefined,
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
    </v-container>
</template>
