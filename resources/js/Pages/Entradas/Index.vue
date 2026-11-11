<script setup>
import { router } from "@inertiajs/vue3";
import { ref } from "vue";
import AppLayout from "@/Layouts/AppLayout.vue";
import DataTableBase from "@/Components/DataTableBase.vue";

import Card from "@/Components/Card.vue";

import Form from "./Form.vue";
import Show from "./Show.vue";

defineOptions({
    layout: AppLayout,
});

const props = defineProps({
    entradas: {
        type: Object,
        default: () => ({ data: [], total: 0, current_page: 1, per_page: 20 }),
    },

    proveedores: {
        type: Array,
        default: () => [],
    },

    productos: {
        type: Array,
        default: () => [],
    },
    codigoIngreso: {
        type: String,
        default: "",
    },
    categorias: {
        type: Array,
        default: () => [],
    },
    marcas: {
        type: Array,
        default: () => [],
    },
    subcategorias: {
        type: Array,
        default: () => [],
    },
    ubicaciones: {
        type: Array,
        default: () => [],
    },
    empresas: {
        type: Array,
        default: () => [],
    },
    filtros: {
        type: Object,
        default: () => ({}),
    },
});

const cargandoEntradas = ref(false);
const mostrarForm = ref(false);
const entradaSeleccionada = ref(null);

const mostrarShow = ref(false);

const home = ref({
    icon: "pi pi-home",
    command: () => {
        router.visit("/dashboard");
    },
});

const items = ref([{ label: "Entradas" }]);

const columns = [
    {
        field: "codigo_ingreso",
        header: "Código",
        sortable: true,
    },
    {
        field: "tipo_comprobante",
        header: "Comprobante",
    },
    {
        field: "numero_comprobante",
        header: "N° comprobante",
        sortable: true,
    },
    {
        field: "proveedor.nombre_comercial",
        header: "Proveedor",
        sortable: true,
    },
    {
        field: "fecha_ingreso",
        header: "Fecha de ingreso",
        type: "date",
        filter: true,
    },
    {
        field: "total_compra",
        header: "Total",
        type: "currency",
    },
];

const formatearFechaConsulta = (fecha) => {
    if (!fecha) return "";
    if (typeof fecha === "string") return fecha.slice(0, 10);

    const year = fecha.getFullYear();
    const month = String(fecha.getMonth() + 1).padStart(2, "0");
    const day = String(fecha.getDate()).padStart(2, "0");
    return `${year}-${month}-${day}`;
};

const buscarEntradas = (consulta) => {
    const fechas = consulta.filters.fecha_ingreso;
    router.get(
        route("entradas.index"),
        {
            page: consulta.page,
            per_page: consulta.rows,
            search: consulta.search,
            fecha_desde: formatearFechaConsulta(fechas?.[0]),
            fecha_hasta: formatearFechaConsulta(fechas?.[1]),
            sort: consulta.sortField,
            direction: consulta.sortOrder === 1 ? "asc" : "desc",
        },
        {
            only: ["entradas", "filtros"],
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onStart: () => (cargandoEntradas.value = true),
            onFinish: () => (cargandoEntradas.value = false),
        },
    );
};

const abrirForm = () => {
    console.log("1. CLICK NUEVA ENTRADA");

    entradaSeleccionada.value = null;

    console.log("2. ENTRADA SELECCIONADA:", entradaSeleccionada.value);

    mostrarForm.value = true;

    console.log("3. MOSTRAR FORM:", mostrarForm.value);
};

const verEntrada = (entrada) => {
    entradaSeleccionada.value = entrada;
    mostrarShow.value = true;
};

const editarEntrada = (entrada) => {
    entradaSeleccionada.value = entrada;
    mostrarForm.value = true;
};

const formularioGuardado = async (accion) => {
    // Primero cerrar el formulario
    mostrarForm.value = false;
    entradaSeleccionada.value = null;

    // Esperar a que Vue actualice el DOM
    await new Promise((resolve) => setTimeout(resolve, 100));

    // Mostrar SweetAlert DESPUÉS de cerrar el formulario
    await Swal.fire({
        title: accion === "actualizado" ? "¡Actualizado!" : "¡Guardado!",

        text:
            accion === "actualizado"
                ? "La entrada fue actualizada correctamente."
                : "La entrada fue registrada correctamente.",

        icon: "success",
        confirmButtonText: "Aceptar",
    });

    // Recargar únicamente las entradas
    router.reload({
        only: ["entradas"],
    });
};

const cerrarForm = () => {
    mostrarForm.value = false;
    entradaSeleccionada.value = null;

    router.reload({
        only: ["entradas"],
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
                            Histórico de Entradas de Inventario
                        </h1>
                        <p class="text-sm text-gray-500">
                            Registro acumulado de compras, recepciones y
                            entradas manuales de productos.
                        </p>
                    </div>
                </div>
            </template>
            <template #content>
                <DataTableBase
                    title="Entradas"
                    :data="props.entradas.data"
                    :columns="columns"
                    server-pagination
                    :total-records="props.entradas.total"
                    :current-page="props.entradas.current_page"
                    :rows="props.entradas.per_page"
                    :server-filters="props.filtros"
                    wait-for-complete-date-range
                    :loading="cargandoEntradas"
                    titleButton="Nueva entrada"
                    :show-view="true"
                    :show-edit="true"
                    edit-permission="entradas.editar"
                    :show-delete="false"
                    :show-filters="true"
                    :mostrarCrear="true"
                    @nuevo="abrirForm"
                    @ver="verEntrada"
                    @editar="editarEntrada"
                    @server-query="buscarEntradas"
                />
            </template>
        </Card>

        <Form
            v-model:visible="mostrarForm"
            :entrada="entradaSeleccionada"
            :proveedores="props.proveedores"
            :categorias="props.categorias"
            :marcas="props.marcas"
            :subcategorias="props.subcategorias"
            :ubicaciones="props.ubicaciones"
            :empresas="props.empresas"
            :productos="props.productos"
            :codigo-ingreso="codigoIngreso"
            @saved="formularioGuardado"
            @cancel="cerrarForm"
        />

        <Show
            v-if="mostrarShow && entradaSeleccionada"
            v-model:visible="mostrarShow"
            :entrada="entradaSeleccionada"
        />
    </v-container>
</template>
