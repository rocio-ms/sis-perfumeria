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
import { usePage } from "@inertiajs/vue3";
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
                filtros.value[dependiente.field].value = null;
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

const inicializarFiltros = () => {
    const nuevosFiltros = {};

    props.columns.forEach((columna) => {
        if (columna.filter) {
            const valorInicial = props.serverFilters[columna.field] ?? null;
            nuevosFiltros[columna.field] = {
                value:
                    columna.type === "date" && Array.isArray(valorInicial)
                        ? valorInicial.map((fecha) =>
                              fecha ? new Date(`${fecha}T00:00:00`) : null,
                          )
                        : valorInicial,
            };
        }
    });

    filtros.value = nuevosFiltros;
};

inicializarFiltros();

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

            const valorFila = obtenerValor(fila, columna.field);

            /*
            |--------------------------------------------------------------------------
            | SELECT
            |--------------------------------------------------------------------------
            */

            if (columna.type === "select") {
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
            if (valor.length === 2 && valor[0] && valor[1]) {
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

    Object.keys(filtros.value).forEach((campo) => {
        filtros.value[campo].value = null;
    });
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
                    v-if="cantidadFiltros > 0"
                    icon="pi pi-filter-slash"
                    severity="secondary"
                    variant="text"
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
                    v-if="cantidadFiltros > 0"
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

                    <div v-if="columna.filter && columna.type === 'date'">
                        <label
                            class="block text-sm font-medium text-gray-600 mb-1.5"
                        >
                            {{ columna.header }}
                        </label>

                        <DatePicker
                            v-model="filtros[columna.field].value"
                            selectionMode="range"
                            dateFormat="dd/mm/yy"
                            placeholder="Seleccionar rango"
                            showIcon
                            :manualInput="false"
                            class="w-full"
                        />
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
            class="text-sm"
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
                sortable
            >
                <template #body="{ data }">
                    <!-- IMAGEN -->

                    <template v-if="columna.type === 'image'">
                        <img
                            :src="
                                obtenerValor(data, columna.field)
                                    ? `/storage/${obtenerValor(
                                          data,
                                          columna.field,
                                      )}`
                                    : '/images/producto_default.svg'
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
                v-if="showView || showEdit || showDelete || showClose"
                header="Acciones"
                :exportable="false"
                style="width: 150px"
            >
                <template #body="{ data }">
                    <div class="flex justify-center gap-1">
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
                    </div>
                </template>
            </Column>
        </DataTable>
    </div>
</template>
