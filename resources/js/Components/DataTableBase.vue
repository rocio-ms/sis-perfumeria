<script setup>
import { ref, computed, watch, onBeforeUnmount } from "vue";

import DataTable from "primevue/datatable";
import Column from "primevue/column";
import InputText from "primevue/inputtext";
import InputIcon from "primevue/inputicon";
import IconField from "primevue/iconfield";
import Button from "primevue/button";
import Select from "primevue/select";
import DatePicker from "primevue/datepicker";
import Tag from "primevue/tag";
import ToggleSwitch from "primevue/toggleswitch";
import { router, usePage } from "@inertiajs/vue3";
/*
|--------------------------------------------------------------------------
| PROPS
|--------------------------------------------------------------------------
*/

const page = usePage();

const tienePermiso = (permiso) => {
    if (!permiso) {
        return true;
    }

    return (page.props.auth?.permissions ?? []).includes(permiso);
};

const props = defineProps({
    data: {
        type: Array,
        default: () => [],
    },
    serverPagination: {
        type: Boolean,
        default: false,
    },
    totalRecords: {
        type: Number,
        default: 0,
    },
    currentPage: {
        type: Number,
        default: 1,
    },
    rows: {
        type: Number,
        default: 50,
    },
    serverFilters: {
        type: Object,
        default: () => ({}),
    },
    waitForCompleteDateRange: {
        type: Boolean,
        default: false,
    },
    searchPlaceholder: {
        type: String,
        default: "Buscar...",
    },
    searchIcon: {
        type: String,
        default: "pi pi-search",
    },

    columns: {
        type: Array,
        default: () => [],
    },

    mostrarCrear: {
        type: Boolean,
        default: false,
    },
    loading: {
        type: Boolean,
        default: false,
    },

    titleButton: {
        type: String,
        default: "Nuevo",
    },

    showView: {
        type: Boolean,
        default: true,
    },

    showEdit: {
        type: Boolean,
        default: true,
    },

    showDelete: {
        type: Boolean,
        default: true,
    },
    createPermission: {
        type: String,
        default: null,
    },

    viewPermission: {
        type: String,
        default: null,
    },

    editPermission: {
        type: String,
        default: null,
    },

    deletePermission: {
        type: String,
        default: null,
    },

    closePermission: {
        type: String,
        default: null,
    },

    showClose: {
        type: Boolean,
        default: true,
    },

    showFilters: {
        type: Boolean,
        default: true,
    },
    moduloEstado: {
        type: String,
        default: null,
    },
});

/*
|--------------------------------------------------------------------------
| EMITS
|--------------------------------------------------------------------------
*/

const emit = defineEmits([
    "nuevo",
    "ver",
    "editar",
    "eliminar",
    "cerrar",
    "server-query",
]);


/*
|--------------------------------------------------------------------------
| ESTADO
|--------------------------------------------------------------------------
*/

const mostrarFiltros = ref(false);

const busqueda = ref(props.serverFilters.search ?? "");
const first = ref(0);

const filtros = ref({});
const ordenamiento = ref({
    sortField: props.serverFilters.sort ?? "id",
    sortOrder: props.serverFilters.direction === "asc" ? 1 : -1,
});
let temporizadorBusqueda;

const puedeCrear = computed(() => tienePermiso(props.createPermission));
const puedeVer = computed(() => tienePermiso(props.viewPermission));
const puedeEditar = computed(() => tienePermiso(props.editPermission));
const puedeEliminar = computed(() => tienePermiso(props.deletePermission));
const puedeCerrar = computed(() => tienePermiso(props.closePermission));
const puedeCambiarEstado = computed(() => props.moduloEstado && (
    (page.props.auth?.roles ?? []).includes("Administrador") ||
    tienePermiso(`${props.moduloEstado}.editar`)
));
const cambioEstado = ref(null);
const errorEstado = ref("");

const estadoRegistro = (fila) => cambioEstado.value?.id === fila.id
    ? cambioEstado.value.activo
    : [true, 1, "1"].includes(fila.activo);

const cambiarEstado = (fila, activo) => {
    if (!puedeCambiarEstado.value || cambioEstado.value) return;

    cambioEstado.value = { id: fila.id, activo };
    errorEstado.value = "";

    router.patch(route("registros.estado", { modulo: props.moduloEstado, registro: fila.id }), { activo }, {
        preserveScroll: true,
        onError: (errores) => {
            errorEstado.value = errores.activo ?? "No se pudo cambiar el estado.";
        },
        onFinish: () => {
            cambioEstado.value = null;
        },
    });
};

const columnasFiltrables = computed(() =>
    props.columns.filter((columna) => columna.filter),
);

const opcionesFiltro = (columna) =>
    typeof columna.filterOptions === "function"
        ? columna.filterOptions(filtros.value)
        : columna.filterOptions;

const alCambiarFiltro = (columna) => {
    props.columns
        .filter((dependiente) => dependiente.filterDependsOn === columna.field)
        .forEach((dependiente) => {
            if (filtros.value[dependiente.field]) {
                filtros.value[dependiente.field].value =
                    dependiente.type === "date" ? [null, null] : null;
            }
        });
};

/*
|--------------------------------------------------------------------------
| OBTENER VALOR DE UNA PROPIEDAD ANIDADA
|--------------------------------------------------------------------------
|
| Ejemplo:
|
| cliente.nombre
|
|--------------------------------------------------------------------------
*/

const obtenerValor = (objeto, path) => {
    if (!objeto || !path) {
        return null;
    }

    return path.split(".").reduce((valor, propiedad) => {
        return valor?.[propiedad];
    }, objeto);
};

/*
|--------------------------------------------------------------------------
| FILTROS INICIALES
|--------------------------------------------------------------------------
*/

const inicializarFiltros = (valores) => {
    const nuevosFiltros = {};

    props.columns.forEach((columna) => {
        if (columna.filter) {
            const valorInicial = valores[columna.field] ?? null;
            const rango = Array.isArray(valorInicial) ? valorInicial : [];
            nuevosFiltros[columna.field] = {
                value:
                    columna.type === "date"
                        ? [rango[0], rango[1]].map((fecha) =>
                              fecha ? new Date(`${fecha}T00:00:00`) : null,
                          )
                        : valorInicial,
            };
        }
    });

    filtros.value = nuevosFiltros;
};

inicializarFiltros(props.serverFilters);

const emitirConsultaServidor = (page = 1, rows = props.rows) => {
    emit("server-query", {
        page,
        rows,
        search: busqueda.value.trim(),
        filters: Object.fromEntries(
            Object.entries(filtros.value).map(([field, filter]) => [
                field,
                filter.value,
            ]),
        ),
        sortField: ordenamiento.value.sortField,
        sortOrder: ordenamiento.value.sortOrder,
    });
};

const buscarAlPresionarEnter = () => {
    if (!props.serverPagination) {
        return;
    }

    clearTimeout(temporizadorBusqueda);
    emitirConsultaServidor();
};

watch(
    [busqueda, filtros],
    () => {
        if (!props.serverPagination) {
            return;
        }

        clearTimeout(temporizadorBusqueda);

        if (props.waitForCompleteDateRange) {
            const rangoFechaIncompleto = props.columns.some((columna) => {
                if (!columna.filter || columna.type !== "date") {
                    return false;
                }

                const rango = filtros.value[columna.field]?.value;

                return (
                    Array.isArray(rango) &&
                    rango.some(Boolean) &&
                    (!rango[0] || !rango[1])
                );
            });

            if (rangoFechaIncompleto) {
                return;
            }
        }

        temporizadorBusqueda = setTimeout(() => emitirConsultaServidor(), 300);
    },
    { deep: true },
);

onBeforeUnmount(() => clearTimeout(temporizadorBusqueda));

const cambiarPagina = (event) => {
    first.value = event.first;
    emitirConsultaServidor(event.page + 1, event.rows);
};

const cambiarOrden = (event) => {
    ordenamiento.value = {
        sortField: event.sortField,
        sortOrder: event.sortOrder,
    };
    emitirConsultaServidor(1, props.rows);
};

/*
|--------------------------------------------------------------------------
| DATOS FILTRADOS
|--------------------------------------------------------------------------
*/

const datosFiltrados = computed(() => {
    const lista = Array.isArray(props.data) ? props.data : [];

    if (props.serverPagination) {
        return lista;
    }

    return lista.filter((fila) => {
        /*
        |--------------------------------------------------------------------------
        | BÚSQUEDA GENERAL
        |--------------------------------------------------------------------------
        */

        const textoBusqueda = String(busqueda.value ?? "")
            .toLowerCase()
            .trim();

        let coincideBusqueda = true;

        if (textoBusqueda !== "") {
            coincideBusqueda = props.columns.some((columna) => {
                const valor = obtenerValor(fila, columna.field);

                return String(valor ?? "")
                    .toLowerCase()
                    .includes(textoBusqueda);
            });
        }

        if (!coincideBusqueda) {
            return false;
        }

        /*
        |--------------------------------------------------------------------------
        | FILTROS POR COLUMNA
        |--------------------------------------------------------------------------
        */

        for (const columna of props.columns) {
            if (!columna.filter) {
                continue;
            }

            const filtro = filtros.value[columna.field];

            if (!filtro) {
                continue;
            }

            const valorFiltro = filtro.value;

            /*
            |--------------------------------------------------------------------------
            | SIN FILTRO
            |--------------------------------------------------------------------------
            */

            if (
                valorFiltro === null ||
                valorFiltro === undefined ||
                valorFiltro === ""
            ) {
                continue;
            }

            const valorFila = obtenerValor(fila, columna.filterField ?? columna.field);

            /*
            |--------------------------------------------------------------------------
            | SELECT
            |--------------------------------------------------------------------------
            */

            if (columna.type === "select" || columna.filterType === "select") {
                if (String(valorFila ?? "") !== String(valorFiltro)) {
                    return false;
                }

                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | FECHA / RANGO
            |--------------------------------------------------------------------------
            */

            if (columna.type === "date") {
                if (
                    !Array.isArray(valorFiltro) ||
                    !valorFiltro[0] ||
                    !valorFiltro[1]
                ) {
                    continue;
                }

                const fechaSolo = typeof valorFila === "string"
                    ? /^(\d{4})-(\d{2})-(\d{2})$/.exec(valorFila)
                    : null;
                const fechaFila = fechaSolo
                    ? new Date(
                          Number(fechaSolo[1]),
                          Number(fechaSolo[2]) - 1,
                          Number(fechaSolo[3]),
                      )
                    : new Date(valorFila);

                const desde = new Date(valorFiltro[0]);

                const hasta = new Date(valorFiltro[1]);

                if (isNaN(fechaFila.getTime())) {
                    return false;
                }

                desde.setHours(0, 0, 0, 0);

                hasta.setHours(23, 59, 59, 999);

                if (fechaFila < desde || fechaFila > hasta) {
                    return false;
                }

                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | TEXTO
            |--------------------------------------------------------------------------
            */

            const textoFila = String(valorFila ?? "").toLowerCase();

            const textoFiltro = String(valorFiltro).toLowerCase().trim();

            if (!textoFila.includes(textoFiltro)) {
                return false;
            }
        }

        return true;
    });
});

/*
|--------------------------------------------------------------------------
| CANTIDAD DE FILTROS ACTIVOS
|--------------------------------------------------------------------------
*/

const cantidadFiltros = computed(() => {
    let cantidad = 0;

    Object.values(filtros.value).forEach((filtro) => {
        if (!filtro) {
            return;
        }

        const valor = filtro.value;

        if (Array.isArray(valor)) {
            if (valor.some(Boolean)) {
                cantidad++;
            }

            return;
        }

        if (valor !== null && valor !== undefined && valor !== "") {
            cantidad++;
        }
    });

    return cantidad;
});

/*
|--------------------------------------------------------------------------
| LIMPIAR FILTROS
|--------------------------------------------------------------------------
*/

const limpiarFiltros = () => {
    busqueda.value = "";
    first.value = 0;
    inicializarFiltros({});
};

/*
|--------------------------------------------------------------------------
| FORMATEAR MONEDA
|--------------------------------------------------------------------------
*/

const formatearMoneda = (valor) => {
    return Number(valor || 0).toLocaleString("es-PE", {
        style: "currency",
        currency: "PEN",
    });
};

/*
|--------------------------------------------------------------------------
| FORMATEAR FECHA
|--------------------------------------------------------------------------
*/

const formatearFecha = (valor) => {
    if (!valor) {
        return "—";
    }

    const fechaSolo = typeof valor === "string"
        ? /^(\d{4})-(\d{2})-(\d{2})(?:$|T00:00:00(?:\.0+)?Z$)/.exec(valor)
        : null;

    if (fechaSolo) {
        return new Date(
            Number(fechaSolo[1]),
            Number(fechaSolo[2]) - 1,
            Number(fechaSolo[3]),
        ).toLocaleDateString("es-PE", {
            day: "2-digit",
            month: "2-digit",
            year: "numeric",
        });
    }

    const fecha = new Date(valor);

    if (isNaN(fecha.getTime())) {
        return "—";
    }

    return fecha.toLocaleString("es-PE", {
        day: "2-digit",
        month: "2-digit",
        year: "numeric",
        hour: "2-digit",
        minute: "2-digit",
        hour12: true,
    });
};

const formatearMesAnio = (valor) => {
    if (!valor) {
        return "—";
    }

    const fecha = new Date(valor);

    if (isNaN(fecha.getTime())) {
        return "—";
    }

    return fecha.toLocaleDateString("es-PE", {
        month: "2-digit",
        year: "numeric",
    });
};

/*
|--------------------------------------------------------------------------
| TIPO DE SALIDA
|--------------------------------------------------------------------------
*/

const nombreTipoSalida = (valor) => {
    const tipos = {
        venta: {
            label: "Venta Automática",
            severity: "success",
        },

        consumo: {
            label: "Consumo Interno",
            severity: "info",
        },

        merma: {
            label: "Merma / Daño",
            severity: "danger",
        },

        devolucion: {
            label: "Devolución",
            severity: "warn",
        },
    };

    return (
        tipos[valor] ?? {
            label: valor || "—",
            severity: "secondary",
        }
    );
};

const estadoTag = (valor, columna) => {
    const texto = typeof valor === "string" ? valor : "";
    const normalizado = texto.toLowerCase();
    const activo = valor === true || valor === 1 || valor === "1";
    const inactivo = valor === false || valor === 0 || valor === "0";
    const positivo =
        activo || ["activo", "abierta", "abierto"].includes(normalizado);
    const negativo =
        inactivo || ["inactivo", "cerrada", "cerrado"].includes(normalizado);

    return {
        value: activo ? "Activo" : inactivo ? "Inactivo" : texto || "—",
        severity:
            typeof columna.severity === "function"
                ? columna.severity(valor)
                : positivo
                  ? "success"
                  : negativo
                    ? "danger"
                    : "secondary",
    };
};
</script>

<template>
    <div class="space-y-4">
        <p v-if="errorEstado" class="text-sm text-red-700" role="alert">{{ errorEstado }}</p>
        <!-- ===================================================== -->
        <!-- CABECERA -->
        <!-- ===================================================== -->

        <div
            class="flex flex-col md:flex-row md:items-center md:justify-between gap-4"
        >
            <div>
                <h2 v-if="title" class="text-lg font-semibold text-gray-800">
                    {{ title }}
                </h2>
            </div>

            <div class="flex items-center gap-2">
                <Button
                    v-if="showFilters"
                    :label="
                        cantidadFiltros > 0
                            ? `Filtros (${cantidadFiltros})`
                            : 'Filtros'
                    "
                    icon="pi pi-filter"
                    severity="secondary"
                    variant="outlined"
                    @click="mostrarFiltros = !mostrarFiltros"
                />

                <Button
                    v-if="cantidadFiltros > 0 || busqueda.trim()"
                    icon="pi pi-filter-slash"
                    severity="secondary"
                    variant="text"
                    aria-label="Limpiar filtros"
                    v-tooltip.top="'Limpiar filtros'"
                    @click="limpiarFiltros"
                />

                <Button
                    v-if="
                        mostrarCrear &&
                        titleButton &&
                        puedeCrear
                    "
                    :label="titleButton"
                    icon="pi pi-plus"
                    @click="emit('nuevo')"
                />
            </div>
        </div>

        <!-- ===================================================== -->
        <!-- BUSCADOR GENERAL -->
        <!-- ===================================================== -->

        <div class="flex flex-col md:flex-row gap-3">
            <IconField class="w-full">
                <InputIcon :class="searchIcon" />

                <InputText
                    v-model="busqueda"
                    :placeholder="searchPlaceholder"
                    class="w-full"
                    @keydown.enter.prevent="buscarAlPresionarEnter"
                />
            </IconField>
        </div>

        <!-- ===================================================== -->
        <!-- PANEL DE FILTROS -->
        <!-- ===================================================== -->

        <div
            v-if="mostrarFiltros"
            class="border border-gray-200 rounded-xl p-4 bg-gray-50"
        >
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-2">
                    <i class="pi pi-filter text-indigo-600"></i>

                    <span class="font-semibold text-gray-700"> Filtros </span>
                </div>

                <Button
                    v-if="cantidadFiltros > 0 || busqueda.trim()"
                    label="Limpiar"
                    icon="pi pi-filter-slash"
                    severity="secondary"
                    variant="text"
                    size="small"
                    @click="limpiarFiltros"
                />
            </div>

            <div
                class="grid gap-4"
                :class="
                    columnasFiltrables.length === 1
                        ? 'grid-cols-1'
                        : 'grid-cols-1 md:grid-cols-2'
                "
            >
                <template v-for="columna in columns" :key="columna.field">
                    <!-- ================================================= -->
                    <!-- FILTRO TEXTO -->
                    <!-- ================================================= -->

                    <div
                        v-if="
                            columna.filter &&
                            columna.filterType !== 'select' &&
                            columna.type !== 'select' &&
                            columna.type !== 'date'
                        "
                    >
                        <label
                            class="block text-sm font-medium text-gray-600 mb-1.5"
                        >
                            {{ columna.header }}
                        </label>

                        <InputText
                            v-model="filtros[columna.field].value"
                            :placeholder="`Filtrar ${columna.header.toLowerCase()}...`"
                            class="w-full"
                        />
                    </div>

                    <!-- ================================================= -->
                    <!-- FILTRO SELECT -->
                    <!-- ================================================= -->

                    <div
                        v-if="
                            columna.filter &&
                            (columna.type === 'select' ||
                                columna.filterType === 'select')
                        "
                        :class="{ 'col-span-full': columna.filterFullWidth }"
                    >
                        <label
                            class="block text-sm font-medium text-gray-600 mb-1.5"
                        >
                            {{ columna.header }}
                        </label>

                        <Select
                            v-model="filtros[columna.field].value"
                            :options="opcionesFiltro(columna)"
                            optionLabel="label"
                            optionValue="value"
                            :aria-label="columna.header"
                            :filter="columna.filterSearch === true"
                            placeholder="Todos"
                            showClear
                            size="large"
                            class="w-full"
                            @change="alCambiarFiltro(columna)"
                        />
                    </div>

                    <!-- ================================================= -->
                    <!-- FILTRO FECHA -->
                    <!-- ================================================= -->

                    <div
                        v-if="columna.filter && columna.type === 'date'"
                        class="md:col-span-2"
                    >
                        <label
                            class="block text-sm font-medium text-gray-600 mb-1.5"
                        >
                            {{ columna.header }}
                        </label>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label
                                    class="block text-xs font-medium text-gray-500 mb-1"
                                >
                                    Fecha inicio
                                </label>

                                <DatePicker
                                    v-model="filtros[columna.field].value[0]"
                                    dateFormat="dd/mm/yy"
                                    placeholder="Fecha inicio"
                                    showIcon
                                    :manualInput="false"
                                    class="w-full"
                                />
                            </div>

                            <div>
                                <label
                                    class="block text-xs font-medium text-gray-500 mb-1"
                                >
                                    Fecha fin
                                </label>

                                <DatePicker
                                    v-model="filtros[columna.field].value[1]"
                                    dateFormat="dd/mm/yy"
                                    placeholder="Fecha fin"
                                    showIcon
                                    :manualInput="false"
                                    class="w-full"
                                />
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <!-- ===================================================== -->
        <!-- DATATABLE -->
        <!-- ===================================================== -->

        <DataTable
            :value="datosFiltrados"
            :loading="loading"
            paginator
            :rows="rows"
            :first="serverPagination ? (currentPage - 1) * rows : first"
            :totalRecords="totalRecords"
            :lazy="serverPagination"
            :rowsPerPageOptions="[10, 20, 50]"
            stripedRows
            :sortField="ordenamiento.sortField"
            :sortOrder="ordenamiento.sortOrder"
            responsiveLayout="scroll"
            dataKey="id"
            class="tabla-registros text-sm"
            @page="cambiarPagina"
            @sort="cambiarOrden"
        >
            <!-- ================================================= -->
            <!-- SIN RESULTADOS -->
            <!-- ================================================= -->

            <template #empty>
                <div
                    class="flex flex-col items-center justify-center py-10 text-gray-400"
                >
                    <i class="pi pi-search text-3xl mb-3"></i>

                    <span> No se encontraron registros. </span>
                </div>
            </template>

            <!-- ================================================= -->
            <!-- COLUMNAS -->
            <!-- ================================================= -->

            <Column
                v-for="columna in columns"
                :key="columna.field"
                :field="columna.field"
                :header="columna.header"
                :hidden="columna.hidden === true"
                sortable
            >
                <template #body="{ data }">
                    <!-- IMAGEN -->

                    <template v-if="columna.type === 'image'">
                        <img
                            :src="
                                obtenerValor(data, columna.field) ||
                                '/images/producto_default.svg'
                            "
                            :alt="columna.header"
                            class="w-14 h-14 object-contain rounded-lg border border-gray-200 bg-white p-1"
                            @error="
                                (event) => {
                                    event.target.src =
                                        '/images/producto_default.svg';
                                }
                            "
                        />
                    </template>

                    <!-- FECHA -->

                    <template v-else-if="columna.type === 'date'">
                        {{
                            columna.dateFormat === "month-year"
                                ? formatearMesAnio(
                                      obtenerValor(data, columna.field),
                                  )
                                : formatearFecha(
                                      obtenerValor(data, columna.field),
                                  )
                        }}
                    </template>

                    <!-- MONEDA -->

                    <template v-else-if="columna.type === 'currency'">
                        {{ formatearMoneda(obtenerValor(data, columna.field)) }}
                    </template>

                    <!-- ESTADO -->

                    <template v-else-if="columna.type === 'status'">
                        <Tag
                            :value="
                                estadoTag(
                                    obtenerValor(data, columna.field),
                                    columna,
                                ).value
                            "
                            :severity="
                                estadoTag(
                                    obtenerValor(data, columna.field),
                                    columna,
                                ).severity
                            "
                        />
                    </template>

                    <!-- TIPO SALIDA -->

                    <template v-else-if="columna.type === 'salida'">
                        <Tag
                            :value="
                                nombreTipoSalida(
                                    obtenerValor(data, columna.field),
                                ).label
                            "
                            :severity="
                                nombreTipoSalida(
                                    obtenerValor(data, columna.field),
                                ).severity
                            "
                        />
                    </template>

                    <!-- SELECT -->

                    <template v-else-if="columna.type === 'select'">
                        {{
                            columna.filterOptions?.find(
                                (item) =>
                                    String(item.value) ===
                                    String(obtenerValor(data, columna.field)),
                            )?.label ??
                            obtenerValor(data, columna.field) ??
                            "—"
                        }}
                    </template>

                    <!-- TEXTO -->

                    <template v-else>
                        {{ obtenerValor(data, columna.field) ?? "N/A" }}
                    </template>
                </template>
            </Column>

            <!-- ================================================= -->
            <!-- ACCIONES -->
            <!-- ================================================= -->

            <Column
                v-if="showView || showEdit || showDelete || showClose || moduloEstado"
                header="Acciones"
                :exportable="false"
                :style="{ width: moduloEstado ? '12rem' : '150px' }"
            >
                <template #body="{ data }">
                    <div class="flex items-center justify-center gap-1">
                        <Button
                            v-if="showView && puedeVer"
                            icon="pi pi-eye"
                            severity="info"
                            v-tooltip.top="'Ver'"
                            @click="emit('ver', data)"
                        />

                        <Button
                            v-if="showEdit && puedeEditar"
                            icon="pi pi-pencil"
                            severity="warning"
                            v-tooltip.top="'Editar'"
                            @click="emit('editar', data)"
                        />

                        <Button
                            v-if="showDelete && puedeEliminar"
                            icon="pi pi-trash"
                            severity="danger"
                            v-tooltip.top="'Eliminar'"
                            @click="emit('eliminar', data)"
                        />

                        <Button
                            v-if="
                                showClose &&
                                puedeCerrar &&
                                (data.sesion_estado === 'Abierta' ||
                                    data.estado === 'Abierta')
                            "
                            icon="pi pi-lock"
                            severity="danger"
                            v-tooltip.top="'Cerrar caja'"
                            @click="emit('cerrar', data)"
                        />

                        <ToggleSwitch
                            v-if="moduloEstado"
                            :model-value="estadoRegistro(data)"
                            :disabled="!puedeCambiarEstado || cambioEstado !== null"
                            :aria-label="`Estado del registro ${data.id}`"
                            v-tooltip.top="estadoRegistro(data) ? 'Activo' : 'Inactivo'"
                            class="interruptor-estado ml-2 shrink-0"
                            @update:model-value="cambiarEstado(data, $event)"
                        />
                    </div>
                </template>
            </Column>
        </DataTable>
    </div>
</template>

<style scoped>
.interruptor-estado {
    --p-toggleswitch-background: #b91c1c;
    --p-toggleswitch-hover-background: #991b1b;
    --p-toggleswitch-checked-background: #15803d;
    --p-toggleswitch-checked-hover-background: #166534;
    --p-toggleswitch-handle-background: #fff;
    --p-toggleswitch-handle-hover-background: #fff;
    --p-toggleswitch-handle-checked-background: #fff;
    --p-toggleswitch-handle-checked-hover-background: #fff;
    --p-toggleswitch-disabled-background: #b91c1c;
    --p-toggleswitch-handle-disabled-background: #fff;
}

.interruptor-estado.p-toggleswitch-checked {
    --p-toggleswitch-disabled-background: #15803d;
}

.tabla-registros :deep(.p-datatable-thead) {
    position: sticky;
    top: 0;
    z-index: 2;
    background: var(--p-datatable-header-cell-background, #fff);
}

.tabla-registros :deep(.p-datatable-thead > tr > th) {
    position: static;
}

.tabla-registros :deep(.p-datatable-tbody) {
    position: relative;
    z-index: 0;
}

@media print {
    .tabla-registros :deep(.p-datatable-thead) {
        position: static;
    }
}
</style>
