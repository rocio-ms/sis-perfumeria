<script setup>
import { ref, computed, watch } from "vue";
import { router } from "@inertiajs/vue3";

import AppLayout from "@/Layouts/AppLayout.vue";
import Card from "@/Components/Card.vue";

import Select from "primevue/select";
import Button from "primevue/button";
import DataTable from "primevue/datatable";
import Column from "primevue/column";
import Tag from "primevue/tag";
import Divider from "primevue/divider";
import Chart from "primevue/chart";

defineOptions({
    layout: AppLayout,
});

const props = defineProps({
    resumen: {
        type: Object,
        default: () => ({
            hoy: 0,
            semana: 0,
            mes: 0,
        }),
    },

    ventasPorPeriodo: {
        type: Array,
        default: () => [],
    },

    ventasPorEmpresa: {
        type: Array,
        default: () => [],
    },

    ventasPorTipoPago: {
        type: Array,
        default: () => [],
    },

    productosMasVendidos: {
        type: Array,
        default: () => [],
    },

    stockBajo: {
        type: Array,
        default: () => [],
    },

    proximosVencer: {
        type: Array,
        default: () => [],
    },

    movimientosCaja: {
        type: Array,
        default: () => [],
    },

    empresas: {
        type: Array,
        default: () => [],
    },

    tiposPago: {
        type: Array,
        default: () => [],
    },

    filtros: {
        type: Object,
        default: () => ({
            empresa_id: null,
            tipo_pago_id: null,
        }),
    },
});

/*
|--------------------------------------------------------------------------
| BREADCRUMB
|--------------------------------------------------------------------------
*/

const home = ref({
    icon: "pi pi-home",
});

const items = ref([
    {
        label: "Reportes",
    },
]);

/*
|--------------------------------------------------------------------------
| FILTROS
|--------------------------------------------------------------------------
*/

const empresaId = ref(props.filtros?.empresa_id ?? 0);

const tipoPagoId = ref(props.filtros?.tipo_pago_id ?? null);

/*
|--------------------------------------------------------------------------
| OPCIONES DE EMPRESA
|--------------------------------------------------------------------------
*/

const empresasOptions = computed(() => [
    {
        id: 0,
        razon_social: "Todas las empresas",
    },
    ...props.empresas,
]);

/*
|--------------------------------------------------------------------------
| OPCIONES DE TIPO DE PAGO
|--------------------------------------------------------------------------
*/

const tiposPagoOptions = computed(() => [
    {
        id: null,
        nombre: "Todos los tipos de pago",
    },
    ...props.tiposPago,
]);

/*
|--------------------------------------------------------------------------
| APLICAR FILTROS
|--------------------------------------------------------------------------
*/

function aplicarFiltros() {
    router.get(
        route("reportes.index"),
        {
            empresa_id: empresaId.value || null,
            tipo_pago_id: tipoPagoId.value || null,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
}

watch([empresaId, tipoPagoId], () => {
    aplicarFiltros();
});

/*
|--------------------------------------------------------------------------
| FORMATO MONEDA
|--------------------------------------------------------------------------
*/

function moneda(valor) {
    return new Intl.NumberFormat("es-PE", {
        style: "currency",
        currency: "PEN",
    }).format(Number(valor || 0));
}

/*
|--------------------------------------------------------------------------
| EXPORTAR VENTAS PDF
|--------------------------------------------------------------------------
*/

function exportarVentasPdf() {
    const params = new URLSearchParams();

    if (empresaId.value) {
        params.append("empresa_id", empresaId.value);
    }

    if (tipoPagoId.value) {
        params.append("tipo_pago_id", tipoPagoId.value);
    }

    const query = params.toString();

    window.open(
        route("reportes.ventas.pdf") + (query ? `?${query}` : ""),
        "_blank",
    );
}

/*
|--------------------------------------------------------------------------
| EXPORTAR PRODUCTOS MÁS VENDIDOS
|--------------------------------------------------------------------------
*/

function exportarProductosMasVendidosPdf() {
    const params = new URLSearchParams();

    if (empresaId.value) {
        params.append("empresa_id", empresaId.value);
    }

    if (tipoPagoId.value) {
        params.append("tipo_pago_id", tipoPagoId.value);
    }

    window.open(
        route("reportes.productos-mas-vendidos.pdf") +
            (params.toString() ? `?${params.toString()}` : ""),
        "_blank",
    );
}

/*
|--------------------------------------------------------------------------
| EXPORTAR STOCK BAJO
|--------------------------------------------------------------------------
*/

function exportarStockBajoPdf() {
    const params = new URLSearchParams();

    if (empresaId.value) {
        params.append("empresa_id", empresaId.value);
    }

    window.open(
        route("reportes.stock-bajo.pdf") +
            (params.toString() ? `?${params.toString()}` : ""),
        "_blank",
    );
}

/*
|--------------------------------------------------------------------------
| EXPORTAR PRÓXIMOS A VENCER
|--------------------------------------------------------------------------
*/

function exportarProximosVencerPdf() {
    const params = new URLSearchParams();

    if (empresaId.value) {
        params.append("empresa_id", empresaId.value);
    }

    window.open(
        route("reportes.proximos-vencer.pdf") +
            (params.toString() ? `?${params.toString()}` : ""),
        "_blank",
    );
}

/*
|--------------------------------------------------------------------------
| REPORTE COMPLETO DE EMPRESA
|--------------------------------------------------------------------------
*/

function exportarReporteEmpresaPdf() {
    if (!empresaId.value) {
        return;
    }

    window.open(
        route("reportes.empresa.pdf", {
            empresa_id: empresaId.value,
        }),
        "_blank",
    );
}

/*
|--------------------------------------------------------------------------
| EMPRESA SELECCIONADA
|--------------------------------------------------------------------------
*/

const empresaSeleccionada = computed(() => {
    if (!empresaId.value) {
        return null;
    }

    return props.empresas.find(
        (empresa) => Number(empresa.id) === Number(empresaId.value),
    );
});

const mostrarVentasPorEmpresa = computed(() => !empresaId.value);

/*
|--------------------------------------------------------------------------
| GRÁFICO - VENTAS POR DÍA
|--------------------------------------------------------------------------
*/

const ventasPorDiaChart = computed(() => ({
    labels: props.ventasPorPeriodo.map((item) => item.fecha),

    datasets: [
        {
            label: "Ventas",
            data: props.ventasPorPeriodo.map((item) => Number(item.total || 0)),
            fill: true,
            tension: 0.4,
            borderWidth: 3,
            pointRadius: 4,
            pointHoverRadius: 7,
        },
    ],
}));

const ventasPorDiaOptions = {
    responsive: true,
    maintainAspectRatio: false,

    plugins: {
        legend: {
            display: false,
        },

        tooltip: {
            callbacks: {
                label: (context) => {
                    return `Ventas: ${moneda(context.raw)}`;
                },
            },
        },
    },

    scales: {
        y: {
            beginAtZero: true,

            ticks: {
                callback: (value) => {
                    return `S/ ${Number(value).toFixed(0)}`;
                },
            },
        },

        x: {
            grid: {
                display: false,
            },
        },
    },
};

/*
|--------------------------------------------------------------------------
| GRÁFICO - VENTAS POR EMPRESA
|--------------------------------------------------------------------------
*/

const ventasPorEmpresaChart = computed(() => ({
    labels: props.ventasPorEmpresa.map((item) => item.empresa),

    datasets: [
        {
            data: props.ventasPorEmpresa.map((item) => Number(item.total || 0)),
        },
    ],
}));

const ventasPorEmpresaOptions = {
    responsive: true,
    maintainAspectRatio: false,

    plugins: {
        legend: {
            position: "bottom",
        },

        tooltip: {
            callbacks: {
                label: (context) => {
                    return `${context.label}: ${moneda(context.raw)}`;
                },
            },
        },
    },
};

/*
|--------------------------------------------------------------------------
| GRÁFICO - TIPO DE PAGO
|--------------------------------------------------------------------------
*/

const ventasPorTipoPagoChart = computed(() => ({
    labels: props.ventasPorTipoPago.map((item) => item.tipo_pago),

    datasets: [
        {
            data: props.ventasPorTipoPago.map((item) =>
                Number(item.total || 0),
            ),
        },
    ],
}));

const ventasPorTipoPagoOptions = {
    responsive: true,
    maintainAspectRatio: false,

    plugins: {
        legend: {
            position: "bottom",
        },

        tooltip: {
            callbacks: {
                label: (context) => {
                    return `${context.label}: ${moneda(context.raw)}`;
                },
            },
        },
    },
};

/*
|--------------------------------------------------------------------------
| GRÁFICO - PRODUCTOS MÁS VENDIDOS
|--------------------------------------------------------------------------
*/

const productosMasVendidosChart = computed(() => {
    const productos = props.productosMasVendidos.slice(0, 10);

    return {
        labels: productos.map((item) => item.producto),

        datasets: [
            {
                label: "Unidades vendidas",

                data: productos.map((item) => Number(item.cantidad || 0)),

                borderRadius: 6,
            },
        ],
    };
});

const productosMasVendidosOptions = {
    indexAxis: "y",

    responsive: true,

    maintainAspectRatio: false,

    plugins: {
        legend: {
            display: false,
        },

        tooltip: {
            callbacks: {
                label: (context) => {
                    return `${context.raw} unidades`;
                },
            },
        },
    },

    scales: {
        x: {
            beginAtZero: true,

            ticks: {
                precision: 0,
            },
        },

        y: {
            grid: {
                display: false,
            },
        },
    },
};

/*
|--------------------------------------------------------------------------
| TOTAL PRODUCTOS PRÓXIMOS A VENCER
|--------------------------------------------------------------------------
*/

const totalProximosVencer = computed(() => {
    return props.proximosVencer.length;
});

const totalCriticos = computed(() => {
    return props.proximosVencer.filter(
        (item) => Number(item.dias_restantes || 0) <= 7,
    ).length;
});

const totalProximos = computed(() => {
    return props.proximosVencer.filter((item) => {
        const dias = Number(item.dias_restantes || 0);

        return dias > 7 && dias <= 15;
    }).length;
});

const totalPorVencer = computed(() => {
    return props.proximosVencer.filter((item) => {
        const dias = Number(item.dias_restantes || 0);

        return dias > 15;
    }).length;
});

/*
|--------------------------------------------------------------------------
| SEVERIDAD STOCK
|--------------------------------------------------------------------------
*/

function severidadStock(producto) {
    const actual = Number(producto.stock_actual || 0);

    const minimo = Number(producto.stock_minimo || 0);

    if (actual <= minimo) {
        return "danger";
    }

    return "warn";
}

/*
|--------------------------------------------------------------------------
| SEVERIDAD VENCIMIENTO
|--------------------------------------------------------------------------
*/

function severidadVencimiento(dias) {
    const diasRestantes = Number(dias || 0);

    if (diasRestantes <= 7) {
        return "danger";
    }

    if (diasRestantes <= 15) {
        return "warn";
    }

    return "info";
}

/*
|--------------------------------------------------------------------------
| TOTAL DE MOVIMIENTOS DE CAJA
|--------------------------------------------------------------------------
*/

const totalMovimientosCaja = computed(() => {
    return props.movimientosCaja.length;
});

/*
|--------------------------------------------------------------------------
| TOTAL VENTAS DE CAJA
|--------------------------------------------------------------------------
*/

const totalVentasCaja = computed(() => {
    return props.movimientosCaja.reduce(
        (total, movimiento) => total + Number(movimiento.total_ventas || 0),
        0,
    );
});
</script>

<template>
    <v-container fluid>
        <!-- ========================================================= -->
        <!-- CARD PRINCIPAL -->
        <!-- ========================================================= -->

        <Card>
            <template #title>
                <!-- BREADCRUMB -->

                <div class="card flex justify-content-center mb-4">
                    <Breadcrumb :home="home" :model="items" />
                </div>

                <!-- TÍTULO -->

                <div
                    class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4"
                >
                    <div>
                        <h1 class="text-2xl font-bold text-gray-800">
                            Reportes
                        </h1>

                        <p class="text-sm text-gray-500 mt-1">
                            Consulta y analiza las ventas, inventario y
                            movimientos de caja.
                        </p>
                    </div>
                </div>
            </template>

            <template #content>
                <!-- ================================================= -->
                <!-- FILTROS -->
                <!-- ================================================= -->

                <div class="grid grid-cols-1 gap-4 mb-8">
                    <!-- EMPRESA -->

                    <FloatLabel variant="on">
                        <Select
                            v-model="empresaId"
                            inputId="empresa"
                            :options="empresasOptions"
                            optionLabel="razon_social"
                            optionValue="id"
                            class="w-full"
                        />

                        <label>Empresa</label>
                    </FloatLabel>
                </div>

                <!-- ================================================= -->
                <!-- KPIs -->
                <!-- ================================================= -->

                <div class="mb-8">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <!-- HOY -->

                        <div
                            class="border border-slate-200 rounded-xl p-5 bg-white"
                        >
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm text-slate-500">
                                        Ventas de hoy
                                    </p>

                                    <p
                                        class="text-2xl font-bold text-slate-800 mt-2"
                                    >
                                        {{ moneda(resumen.hoy) }}
                                    </p>
                                </div>

                                <i
                                    class="pi pi-calendar text-indigo-500 text-2xl"
                                ></i>
                            </div>
                        </div>

                        <!-- SEMANA -->

                        <div
                            class="border border-slate-200 rounded-xl p-5 bg-white"
                        >
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm text-slate-500">
                                        Ventas de esta semana
                                    </p>

                                    <p
                                        class="text-2xl font-bold text-slate-800 mt-2"
                                    >
                                        {{ moneda(resumen.semana) }}
                                    </p>
                                </div>

                                <i
                                    class="pi pi-chart-line text-indigo-500 text-2xl"
                                ></i>
                            </div>
                        </div>

                        <!-- MES -->

                        <div
                            class="border border-slate-200 rounded-xl p-5 bg-white"
                        >
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm text-slate-500">
                                        Ventas del mes
                                    </p>

                                    <p
                                        class="text-2xl font-bold text-slate-800 mt-2"
                                    >
                                        {{ moneda(resumen.mes) }}
                                    </p>
                                </div>

                                <i
                                    class="pi pi-wallet text-indigo-500 text-2xl"
                                ></i>
                            </div>
                        </div>
                    </div>
                </div>

                <Divider />

                <!-- ================================================= -->
                <!-- VENTAS POR DÍA -->
                <!-- ================================================= -->

                <div class="mb-8">
                    <div
                        class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-4"
                    >
                        <div>
                            <h2 class="text-lg font-semibold text-slate-800">
                                Ventas por día
                            </h2>

                            <p class="text-sm text-slate-500">
                                Comportamiento de las ventas durante el mes.
                            </p>
                        </div>
                    </div>

                    <div
                        v-if="ventasPorPeriodo.length"
                        class="border border-slate-200 rounded-xl p-5 bg-white"
                    >
                        <div class="h-[350px]">
                            <Chart
                                type="line"
                                :data="ventasPorDiaChart"
                                :options="ventasPorDiaOptions"
                                class="h-full"
                            />
                        </div>
                    </div>

                    <div
                        v-else
                        class="text-center py-10 text-slate-500 border border-slate-200 rounded-xl"
                    >
                        <i class="pi pi-chart-line text-3xl mb-3"></i>

                        <p>No hay ventas registradas para este periodo.</p>
                    </div>
                </div>

                <Divider />

                <!-- ================================================= -->
                <!-- EMPRESA + TIPO DE PAGO -->
                <!-- ================================================= -->

                <div
                    class="grid grid-cols-1 gap-6 mb-8"
                    :class="{ 'lg:grid-cols-2': mostrarVentasPorEmpresa }"
                >
                    <!-- EMPRESAS (solo cuando NO hay empresa filtrada) -->

                    <div
                        v-if="mostrarVentasPorEmpresa"
                        class="border border-slate-200 rounded-xl p-5 bg-white"
                    >
                        <div class="mb-4">
                            <h2 class="text-lg font-semibold text-slate-800">
                                Ventas por empresa
                            </h2>

                            <p class="text-sm text-slate-500">
                                Distribución de las ventas por empresa.
                            </p>
                        </div>

                        <div v-if="ventasPorEmpresa.length" class="h-[350px]">
                            <Chart
                                type="doughnut"
                                :data="ventasPorEmpresaChart"
                                :options="ventasPorEmpresaOptions"
                                class="h-full"
                            />
                        </div>

                        <div v-else class="text-center py-10 text-slate-500">
                            <i class="pi pi-chart-pie text-3xl mb-3"></i>

                            <p>No hay datos de empresas.</p>
                        </div>
                    </div>

                    <!-- TIPO DE PAGO (siempre visible) -->

                    <div
                        class="border border-slate-200 rounded-xl p-5 bg-white"
                    >
                        <div class="mb-4">
                            <h2 class="text-lg font-semibold text-slate-800">
                                Ventas por tipo de pago
                            </h2>

                            <p class="text-sm text-slate-500">
                                <template v-if="empresaSeleccionada">
                                    Distribución según forma de pago de
                                    {{ empresaSeleccionada.razon_social }}.
                                </template>
                                <template v-else>
                                    Distribución según forma de pago.
                                </template>
                            </p>
                        </div>

                        <div v-if="ventasPorTipoPago.length" class="h-[350px]">
                            <Chart
                                type="doughnut"
                                :data="ventasPorTipoPagoChart"
                                :options="ventasPorTipoPagoOptions"
                                class="h-full"
                            />
                        </div>

                        <div v-else class="text-center py-10 text-slate-500">
                            <i class="pi pi-chart-pie text-3xl mb-3"></i>

                            <p>No hay datos de tipos de pago.</p>
                        </div>
                    </div>
                </div>

                <Divider />

                <!-- ================================================= -->
                <!-- TABLA VENTAS POR EMPRESA -->
                <!-- ================================================= -->

                <div class="mb-8">
                    <div class="mb-4">
                        <h2 class="text-lg font-semibold text-slate-800">
                            Detalle de ventas por empresa
                        </h2>

                        <p class="text-sm text-slate-500">
                            Resumen detallado de las ventas realizadas.
                        </p>
                    </div>

                    <DataTable
                        :value="ventasPorEmpresa"
                        size="small"
                        stripedRows
                        responsiveLayout="scroll"
                        emptyMessage="No hay datos"
                    >
                        <Column field="empresa" header="Empresa" />

                        <Column field="cantidad" header="Ventas" />

                        <Column header="Total">
                            <template #body="{ data }">
                                <span class="font-semibold">
                                    {{ moneda(data.total) }}
                                </span>
                            </template>
                        </Column>
                    </DataTable>
                </div>

                <Divider />

                <!-- ================================================= -->
                <!-- PRODUCTOS MÁS VENDIDOS -->
                <!-- ================================================= -->

                <div class="mb-8">
                    <div
                        class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-4"
                    >
                        <div>
                            <h2 class="text-lg font-semibold text-slate-800">
                                Productos más vendidos
                            </h2>

                            <p class="text-sm text-slate-500">
                                Los 10 productos con mayor cantidad de unidades
                                vendidas.
                            </p>
                        </div>
                    </div>

                    <div
                        v-if="productosMasVendidos.length"
                        class="border border-slate-200 rounded-xl p-5 bg-white mb-5"
                    >
                        <div class="h-[420px]">
                            <Chart
                                type="bar"
                                :data="productosMasVendidosChart"
                                :options="productosMasVendidosOptions"
                                class="h-full"
                            />
                        </div>
                    </div>

                </div>

                <Divider />

                <!-- ================================================= -->
                <!-- STOCK BAJO -->
                <!-- ================================================= -->

                <div class="mb-8">
                    <div
                        class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-4"
                    >
                        <div>
                            <h2 class="text-lg font-semibold text-slate-800">
                                Productos con stock bajo
                            </h2>

                            <p class="text-sm text-slate-500">
                                Productos que necesitan reposición.
                            </p>
                        </div>

                    </div>

                    <DataTable
                        :value="stockBajo"
                        stripedRows
                        responsiveLayout="scroll"
                        emptyMessage="No hay productos con stock bajo"
                    >
                        <Column field="codigo" header="Código" />

                        <Column field="producto" header="Producto" />

                        <Column field="stock_actual" header="Stock actual" />

                        <Column field="stock_minimo" header="Stock mínimo" />

                        <Column header="Estado">
                            <template #body="{ data }">
                                <Tag
                                    value="Stock bajo"
                                    :severity="severidadStock(data)"
                                />
                            </template>
                        </Column>
                    </DataTable>
                </div>

                <Divider />

                <!-- ================================================= -->
                <!-- PRÓXIMOS A VENCER -->
                <!-- ================================================= -->

                <div class="mb-8">
                    <div
                        class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-4"
                    >
                        <div>
                            <h2 class="text-lg font-semibold text-slate-800">
                                Productos próximos a vencer
                            </h2>

                            <p class="text-sm text-slate-500">
                                Lotes con fecha de vencimiento dentro de los
                                próximos 30 días.
                            </p>
                        </div>

           
                    </div>

                    <!-- RESUMEN VENCIMIENTO -->

                    <DataTable
                        :value="proximosVencer"
                        stripedRows
                        responsiveLayout="scroll"
                        emptyMessage="No hay productos próximos a vencer"
                    >
                        <Column field="codigo" header="Código" />

                        <Column field="producto" header="Producto" />

                        <Column field="lote" header="Lote" />

                        <Column
                            field="stock_actual"
                            header="Stock disponible"
                        />

                        <Column
                            field="fecha_vencimiento"
                            header="Vencimiento"
                        />

                        <Column header="Tiempo restante">
                            <template #body="{ data }">
                                <Tag
                                    :value="`${data.dias_restantes} días`"
                                    :severity="
                                        severidadVencimiento(
                                            data.dias_restantes,
                                        )
                                    "
                                />
                            </template>
                        </Column>

                        <Column header="Estado">
                            <template #body="{ data }">
                                <Tag
                                    v-if="Number(data.dias_restantes) <= 7"
                                    value="Crítico"
                                    severity="danger"
                                />

                                <Tag
                                    v-else-if="
                                        Number(data.dias_restantes) <= 15
                                    "
                                    value="Próximo a vencer"
                                    severity="warn"
                                />

                                <Tag
                                    v-else
                                    value="Por vencer"
                                    severity="info"
                                />
                            </template>
                        </Column>
                    </DataTable>
                </div>

                <Divider />
            </template>
        </Card>
    </v-container>
</template>
