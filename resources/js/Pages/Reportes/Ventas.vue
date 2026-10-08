<script setup>
import { computed, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';

import Card from 'primevue/card';
import Select from 'primevue/select';
import DatePicker from 'primevue/datepicker';
import Button from 'primevue/button';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Tag from 'primevue/tag';
import Divider from 'primevue/divider';

import AppLayout from '@/Layouts/AppLayout.vue';

/*
|--------------------------------------------------------------------------
| PROPS
|--------------------------------------------------------------------------
*/

const props = defineProps({
    ventas: {
        type: Array,
        default: () => [],
    },

    resumen: {
        type: Object,
        default: () => ({
            total_ventas: 0,
            cantidad_ventas: 0,
            ticket_promedio: 0,
        }),
    },

    ventasPorDia: {
        type: Array,
        default: () => [],
    },

    ventasPorEmpresa: {
        type: Array,
        default: () => [],
    },

    ventasPorMetodoPago: {
        type: Array,
        default: () => [],
    },

    ventasPorComprobante: {
        type: Array,
        default: () => [],
    },

    empresas: {
        type: Array,
        default: () => [],
    },

    filtros: {
        type: Object,
        default: () => ({
            periodo: 'mes',
            empresa_id: null,
            fecha_inicio: null,
            fecha_fin: null,
        }),
    },
});

/*
|--------------------------------------------------------------------------
| FILTROS
|--------------------------------------------------------------------------
*/

const periodo = ref(props.filtros.periodo ?? 'mes');

const empresaId = ref(
    props.filtros.empresa_id
        ? Number(props.filtros.empresa_id)
        : null
);

const fechaInicio = ref(
    props.filtros.fecha_inicio
        ? new Date(`${props.filtros.fecha_inicio}T00:00:00`)
        : null
);

const fechaFin = ref(
    props.filtros.fecha_fin
        ? new Date(`${props.filtros.fecha_fin}T00:00:00`)
        : null
);

/*
|--------------------------------------------------------------------------
| OPCIONES DE PERÍODO
|--------------------------------------------------------------------------
*/

const periodos = [
    {
        label: 'Hoy',
        value: 'dia',
    },
    {
        label: 'Esta semana',
        value: 'semana',
    },
    {
        label: 'Este mes',
        value: 'mes',
    },
    {
        label: 'Este año',
        value: 'anio',
    },
    {
        label: 'Personalizado',
        value: 'personalizado',
    },
];

/*
|--------------------------------------------------------------------------
| EMPRESAS
|--------------------------------------------------------------------------
*/

const opcionesEmpresas = computed(() => {
    return [
        {
            id: null,
            razon_social: 'Todas las empresas',
        },
        ...props.empresas,
    ];
});

/*
|--------------------------------------------------------------------------
| FORMATEAR MONEDA
|--------------------------------------------------------------------------
*/

const formatoMoneda = (valor) => {
    const numero = Number(valor ?? 0);

    return new Intl.NumberFormat('es-PE', {
        style: 'currency',
        currency: 'PEN',
        minimumFractionDigits: 2,
    }).format(numero);
};

/*
|--------------------------------------------------------------------------
| FORMATEAR FECHA
|--------------------------------------------------------------------------
*/

const formatoFecha = (fecha) => {
    if (!fecha) {
        return '-';
    }

    return new Intl.DateTimeFormat('es-PE', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    }).format(new Date(fecha));
};

/*
|--------------------------------------------------------------------------
| FORMATEAR FECHA Y HORA
|--------------------------------------------------------------------------
*/

const formatoFechaHora = (fecha) => {
    if (!fecha) {
        return '-';
    }

    return new Intl.DateTimeFormat('es-PE', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(fecha));
};

/*
|--------------------------------------------------------------------------
| FORMATEAR NOMBRE DEL PAGO
|--------------------------------------------------------------------------
*/

const nombreMetodoPago = (metodo) => {
    if (!metodo) {
        return 'Sin especificar';
    }

    return metodo;
};

/*
|--------------------------------------------------------------------------
| GENERAR REPORTE
|--------------------------------------------------------------------------
*/

const generarReporte = () => {

    const data = {
        periodo: periodo.value,
        empresa_id: empresaId.value,
    };

    /*
    |--------------------------------------------------------------------------
    | FECHAS PERSONALIZADAS
    |--------------------------------------------------------------------------
    */

    if (periodo.value === 'personalizado') {

        data.fecha_inicio = fechaInicio.value
            ? formatearFechaInput(fechaInicio.value)
            : null;

        data.fecha_fin = fechaFin.value
            ? formatearFechaInput(fechaFin.value)
            : null;
    }

    router.get(
        route('reportes.ventas'),
        data,
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        }
    );
};

/*
|--------------------------------------------------------------------------
| FORMATO YYYY-MM-DD
|--------------------------------------------------------------------------
*/

const formatearFechaInput = (fecha) => {

    if (!fecha) {
        return null;
    }

    const year = fecha.getFullYear();

    const month = String(
        fecha.getMonth() + 1
    ).padStart(2, '0');

    const day = String(
        fecha.getDate()
    ).padStart(2, '0');

    return `${year}-${month}-${day}`;
};

/*
|--------------------------------------------------------------------------
| LIMPIAR FILTROS
|--------------------------------------------------------------------------
*/

const limpiarFiltros = () => {

    periodo.value = 'mes';

    empresaId.value = null;

    fechaInicio.value = null;

    fechaFin.value = null;

    generarReporte();
};

/*
|--------------------------------------------------------------------------
| CAMBIO AUTOMÁTICO DE PERÍODO
|--------------------------------------------------------------------------
*/

watch(periodo, (nuevoPeriodo) => {

    if (nuevoPeriodo !== 'personalizado') {
        generarReporte();
    }
});

/*
|--------------------------------------------------------------------------
| DATOS PARA LA GRÁFICA
|--------------------------------------------------------------------------
*/

const maxVentaDia = computed(() => {

    if (!props.ventasPorDia.length) {
        return 1;
    }

    return Math.max(
        ...props.ventasPorDia.map(
            item => Number(item.total)
        ),
        1
    );
});

const porcentajeBarra = (total) => {

    return Math.max(
        5,
        (Number(total) / maxVentaDia.value) * 100
    );
};

/*
|--------------------------------------------------------------------------
| COLOR / SEVERIDAD DEL COMPROBANTE
|--------------------------------------------------------------------------
*/

const severidadComprobante = (tipo) => {

    const texto = String(tipo ?? '').toLowerCase();

    if (texto.includes('factura')) {
        return 'info';
    }

    if (texto.includes('boleta')) {
        return 'success';
    }

    return 'secondary';
};

/*
|--------------------------------------------------------------------------
| EMPRESA DE UNA VENTA
|--------------------------------------------------------------------------
*/

const nombreEmpresa = (venta) => {

    return venta.empresa_emisora?.razon_social
        ?? venta.empresaEmisora?.razon_social
        ?? 'Sin empresa';
};

/*
|--------------------------------------------------------------------------
| CLIENTE
|--------------------------------------------------------------------------
*/

const nombreCliente = (venta) => {

    if (!venta.cliente) {
        return 'CLIENTES VARIOS';
    }

    return venta.cliente.razon_social
        ?? venta.cliente.nombre_completo
        ?? venta.cliente.nombre
        ?? 'Cliente';
};

/*
|--------------------------------------------------------------------------
| COMPROBANTE
|--------------------------------------------------------------------------
*/

const nombreComprobante = (venta) => {

    return venta.tipo_comprobante?.nombre
        ?? venta.tipoComprobante?.nombre
        ?? 'Comprobante';
};

/*
|--------------------------------------------------------------------------
| NÚMERO DE COMPROBANTE
|--------------------------------------------------------------------------
*/

const numeroComprobante = (venta) => {

    return `${venta.serie ?? ''}-${venta.correlativo ?? ''}`;
};

</script>

<template>

    <AppLayout>

        <div class="p-6">

            <!-- =========================================================
                 ENCABEZADO
            ========================================================== -->

            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">

                <div>

                    <h1 class="text-2xl font-bold text-slate-800">
                        Reporte de ventas
                    </h1>

                    <p class="text-sm text-slate-500 mt-1">
                        Consulta y analiza las ventas realizadas
                    </p>

                </div>

            </div>


            <!-- =========================================================
                 FILTROS
            ========================================================== -->

            <Card class="mb-6">

                <template #content>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">

                        <!-- EMPRESA -->

                        <div>

                            <label class="block text-sm font-medium text-slate-700 mb-2">
                                Empresa
                            </label>

                            <Select
                                v-model="empresaId"
                                :options="opcionesEmpresas"
                                optionLabel="razon_social"
                                optionValue="id"
                                placeholder="Todas las empresas"
                                class="w-full"
                            />

                        </div>


                        <!-- PERÍODO -->

                        <div>

                            <label class="block text-sm font-medium text-slate-700 mb-2">
                                Período
                            </label>

                            <Select
                                v-model="periodo"
                                :options="periodos"
                                optionLabel="label"
                                optionValue="value"
                                class="w-full"
                            />

                        </div>


                        <!-- FECHA INICIO -->

                        <div v-if="periodo === 'personalizado'">

                            <label class="block text-sm font-medium text-slate-700 mb-2">
                                Fecha inicio
                            </label>

                            <DatePicker
                                v-model="fechaInicio"
                                dateFormat="dd/mm/yy"
                                showIcon
                                class="w-full"
                                inputClass="w-full"
                            />

                        </div>


                        <!-- FECHA FIN -->

                        <div v-if="periodo === 'personalizado'">

                            <label class="block text-sm font-medium text-slate-700 mb-2">
                                Fecha fin
                            </label>

                            <DatePicker
                                v-model="fechaFin"
                                dateFormat="dd/mm/yy"
                                showIcon
                                class="w-full"
                                inputClass="w-full"
                            />

                        </div>

                    </div>


                    <!-- BOTONES -->

                    <div class="flex flex-wrap justify-end gap-2 mt-5">

                        <Button
                            label="Limpiar"
                            icon="pi pi-filter-slash"
                            severity="secondary"
                            outlined
                            @click="limpiarFiltros"
                        />

                        <Button
                            label="Generar reporte"
                            icon="pi pi-search"
                            @click="generarReporte"
                        />

                    </div>

                </template>

            </Card>


            <!-- =========================================================
                 RESUMEN
            ========================================================== -->

            <div class="grid grid-cols-1 md:grid-cols-3 gap-5 mb-6">

                <!-- TOTAL -->

                <Card>

                    <template #content>

                        <div class="flex items-center gap-4">

                            <div
                                class="w-12 h-12 rounded-xl flex items-center justify-center bg-indigo-100"
                            >
                                <i class="pi pi-money-bill text-xl text-indigo-600"></i>
                            </div>

                            <div>

                                <p class="text-sm text-slate-500">
                                    Total vendido
                                </p>

                                <p class="text-2xl font-bold text-slate-800">
                                    {{ formatoMoneda(resumen.total_ventas) }}
                                </p>

                            </div>

                        </div>

                    </template>

                </Card>


                <!-- CANTIDAD -->

                <Card>

                    <template #content>

                        <div class="flex items-center gap-4">

                            <div
                                class="w-12 h-12 rounded-xl flex items-center justify-center bg-emerald-100"
                            >
                                <i class="pi pi-shopping-cart text-xl text-emerald-600"></i>
                            </div>

                            <div>

                                <p class="text-sm text-slate-500">
                                    Ventas realizadas
                                </p>

                                <p class="text-2xl font-bold text-slate-800">
                                    {{ resumen.cantidad_ventas }}
                                </p>

                            </div>

                        </div>

                    </template>

                </Card>


                <!-- TICKET -->

                <Card>

                    <template #content>

                        <div class="flex items-center gap-4">

                            <div
                                class="w-12 h-12 rounded-xl flex items-center justify-center bg-blue-100"
                            >
                                <i class="pi pi-chart-line text-xl text-blue-600"></i>
                            </div>

                            <div>

                                <p class="text-sm text-slate-500">
                                    Ticket promedio
                                </p>

                                <p class="text-2xl font-bold text-slate-800">
                                    {{ formatoMoneda(resumen.ticket_promedio) }}
                                </p>

                            </div>

                        </div>

                    </template>

                </Card>

            </div>


            <!-- =========================================================
                 GRÁFICA SIMPLE DE VENTAS POR DÍA
            ========================================================== -->

            <Card class="mb-6">

                <template #title>
                    Ventas por día
                </template>

                <template #content>

                    <div
                        v-if="ventasPorDia.length"
                        class="space-y-4"
                    >

                        <div
                            v-for="item in ventasPorDia"
                            :key="item.fecha"
                            class="grid grid-cols-[90px_1fr_120px] items-center gap-3"
                        >

                            <span class="text-sm text-slate-600">
                                {{ formatoFecha(item.fecha) }}
                            </span>

                            <div class="h-8 bg-slate-100 rounded-lg overflow-hidden">

                                <div
                                    class="h-full rounded-lg flex items-center px-3 transition-all"
                                    :style="{
                                        width: `${porcentajeBarra(item.total)}%`
                                    }"
                                >

                                    <span class="text-xs font-semibold text-white">
                                        {{ formatoMoneda(item.total) }}
                                    </span>

                                </div>

                            </div>

                            <span class="text-sm text-right text-slate-500">
                                {{ item.cantidad }} ventas
                            </span>

                        </div>

                    </div>

                    <div
                        v-else
                        class="py-10 text-center text-slate-400"
                    >

                        <i class="pi pi-chart-bar text-3xl mb-3"></i>

                        <p>
                            No hay ventas para el período seleccionado.
                        </p>

                    </div>

                </template>

            </Card>


            <!-- =========================================================
                 RESUMEN POR EMPRESA Y PAGO
            ========================================================== -->

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">


                <!-- EMPRESAS -->

                <Card>

                    <template #title>
                        Ventas por empresa
                    </template>

                    <template #content>

                        <div
                            v-if="ventasPorEmpresa.length"
                            class="space-y-4"
                        >

                            <div
                                v-for="empresa in ventasPorEmpresa"
                                :key="empresa.empresa_id ?? empresa.empresa"
                                class="flex items-center justify-between border-b border-slate-100 pb-3"
                            >

                                <div>

                                    <p class="font-medium text-slate-700">
                                        {{ empresa.empresa }}
                                    </p>

                                    <p class="text-xs text-slate-400">
                                        {{ empresa.cantidad }} ventas
                                    </p>

                                </div>

                                <span class="font-semibold text-slate-800">
                                    {{ formatoMoneda(empresa.total) }}
                                </span>

                            </div>

                        </div>

                        <div
                            v-else
                            class="py-8 text-center text-slate-400"
                        >
                            No hay información disponible.
                        </div>

                    </template>

                </Card>


                <!-- MÉTODOS DE PAGO -->

                <Card>

                    <template #title>
                        Ventas por método de pago
                    </template>

                    <template #content>

                        <div
                            v-if="ventasPorMetodoPago.length"
                            class="space-y-4"
                        >

                            <div
                                v-for="pago in ventasPorMetodoPago"
                                :key="pago.tipo_pago_id ?? pago.tipo_pago"
                                class="flex items-center justify-between border-b border-slate-100 pb-3"
                            >

                                <div>

                                    <p class="font-medium text-slate-700">
                                        {{ nombreMetodoPago(pago.tipo_pago) }}
                                    </p>

                                    <p class="text-xs text-slate-400">
                                        {{ pago.cantidad }} ventas
                                    </p>

                                </div>

                                <span class="font-semibold text-slate-800">
                                    {{ formatoMoneda(pago.total) }}
                                </span>

                            </div>

                        </div>

                        <div
                            v-else
                            class="py-8 text-center text-slate-400"
                        >
                            No hay información disponible.
                        </div>

                    </template>

                </Card>

            </div>


            <!-- =========================================================
                 COMPROBANTES
            ========================================================== -->

            <Card class="mb-6">

                <template #title>
                    Ventas por tipo de comprobante
                </template>

                <template #content>

                    <div
                        v-if="ventasPorComprobante.length"
                        class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4"
                    >

                        <div
                            v-for="comprobante in ventasPorComprobante"
                            :key="comprobante.tipo_comprobante_id ?? comprobante.tipo_comprobante"
                            class="border border-slate-200 rounded-xl p-4"
                        >

                            <div class="flex justify-between items-center mb-3">

                                <span class="font-medium text-slate-700">
                                    {{ comprobante.tipo_comprobante }}
                                </span>

                                <Tag
                                    :value="comprobante.cantidad"
                                    :severity="severidadComprobante(comprobante.tipo_comprobante)"
                                />

                            </div>

                            <p class="text-xl font-bold text-slate-800">
                                {{ formatoMoneda(comprobante.total) }}
                            </p>

                            <p class="text-xs text-slate-400 mt-1">
                                {{ comprobante.cantidad }} comprobantes
                            </p>

                        </div>

                    </div>

                    <div
                        v-else
                        class="py-8 text-center text-slate-400"
                    >
                        No hay información disponible.
                    </div>

                </template>

            </Card>


            <!-- =========================================================
                 DETALLE DE VENTAS
            ========================================================== -->

            <Card>

                <template #title>
                    Detalle de ventas
                </template>

                <template #content>

                    <DataTable
                        :value="ventas"
                        paginator
                        :rows="10"
                        :rowsPerPageOptions="[10, 20, 50]"
                        stripedRows
                        responsiveLayout="scroll"
                        dataKey="id"
                        emptyMessage="No hay ventas para mostrar."
                    >

                        <!-- FECHA -->

                        <Column
                            header="Fecha"
                            style="min-width: 150px"
                        >

                            <template #body="{ data }">

                                <span class="text-sm">
                                    {{ formatoFechaHora(data.fecha_emision) }}
                                </span>

                            </template>

                        </Column>


                        <!-- COMPROBANTE -->

                        <Column
                            header="Comprobante"
                            style="min-width: 150px"
                        >

                            <template #body="{ data }">

                                <div class="flex flex-col gap-1">

                                    <span class="font-medium">
                                        {{ numeroComprobante(data) }}
                                    </span>

                                    <Tag
                                        :value="nombreComprobante(data)"
                                        :severity="severidadComprobante(nombreComprobante(data))"
                                        class="w-fit"
                                    />

                                </div>

                            </template>

                        </Column>


                        <!-- CLIENTE -->

                        <Column
                            header="Cliente"
                            style="min-width: 180px"
                        >

                            <template #body="{ data }">

                                {{ nombreCliente(data) }}

                            </template>

                        </Column>


                        <!-- EMPRESA -->

                        <Column
                            header="Empresa"
                            style="min-width: 180px"
                        >

                            <template #body="{ data }">

                                {{ nombreEmpresa(data) }}

                            </template>

                        </Column>


                        <!-- VENDEDOR -->

                        <Column
                            header="Vendedor"
                            style="min-width: 140px"
                        >

                            <template #body="{ data }">

                                {{ data.usuario?.name ?? 'Sin vendedor' }}

                            </template>

                        </Column>


                        <!-- TOTAL -->

                        <Column
                            header="Total"
                            style="min-width: 120px"
                        >

                            <template #body="{ data }">

                                <span class="font-semibold">
                                    {{ formatoMoneda(data.importe_total) }}
                                </span>

                            </template>

                        </Column>

                    </DataTable>

                </template>

            </Card>

        </div>

    </AppLayout>

</template>
