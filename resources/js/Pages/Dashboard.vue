<script setup>
import { computed, ref } from "vue";
import AppLayout from "@/Layouts/AppLayout.vue";
import Card from "@/Components/Card.vue";

import { router } from "@inertiajs/vue3";

defineOptions({
    layout: AppLayout,
});

const props = defineProps({
    estadisticas: {
        type: Object,
        default: () => ({
            ventas: 0,
            productos: 0,
            ingresos: 0,
            stock_bajo: 0,
        }),
    },

    graficoVentas: {
        type: Array,
        default: () => [],
    },

    productosMasVendidos: {
        type: Array,
        default: () => [],
    },

    ultimasVentas: {
        type: Array,
        default: () => [],
    },

    productosStockBajo: {
        type: Array,
        default: () => [],
    },
});

//Filtro de periodo (7, 15 y 30 días)

const periodos = ref([
    { label: "7 días", value: 7 },
    { label: "15 días", value: 15 },
    { label: "1 mes", value: 30 },
]);

const periodoSeleccionado = ref(7);


//Función para cambiar entre periodo
const cambiarPeriodo = () => {
    router.reload({
        data: { dias: periodoSeleccionado.value },
        only: ["graficoVentas", "estadisticas"],
        preserveScroll: true,
    });
};

//Gráfico

const chartData = computed(() => {
    return {
        labels: props.graficoVentas.map((item) => item.fecha),

        datasets: [
            {
                label: "Ventas",
                data: props.graficoVentas.map((item) => item.cantidad),
                borderColor: "#9f3154",
                backgroundColor: "rgba(159, 49, 84, 0.12)",
                pointBackgroundColor: "#9f3154",
                pointBorderColor: "#fff",
                pointBorderWidth: 2,
                pointRadius: 4,
                pointHoverRadius: 6,
                tension: 0.4,
                fill: true,
                borderWidth: 2.5,
            },
        ],
    };
});

const chartOptions = ref({
    responsive: true,
    maintainAspectRatio: false,

    interaction: {
        mode: "index",
        intersect: false,
    },

    plugins: {
        legend: {
            display: false,
        },
        tooltip: {
            backgroundColor: "#263238",
            padding: 10,
            cornerRadius: 8,
            displayColors: false,
        },
    },

    scales: {
        y: {
            beginAtZero: true,
            ticks: { precision: 0 },
            grid: { color: "#f0f2f5" },
        },
        x: {
            grid: { display: false },
        },
    },
});

//Navegación

const irVentas = () => router.visit(route("ventas.index"));
const registrarVenta = () => router.visit(route("ventas.create"));
const irProductos = () => router.visit(route("productos.index"));
const irStock = () => router.visit(route("productos.index"));
const verVenta = (venta) => router.visit(route("ventas.show", venta.id));

//Helpers

const obtenerSeveridadStock = (stock) => {
    if (stock <= 2) return "danger";
    if (stock <= 5) return "warn";
    return "success";
};

const porcentajeStock = (stock) => {
    return Math.min(100, Math.round((stock / 10) * 100));
};

//Formatera moneda
const formatoMoneda = (valor) => {
    return new Intl.NumberFormat("es-PE", {
        style: "currency",
        currency: "PEN",
    }).format(valor ?? 0);
};

const maxVendidos = computed(() => {
    return Math.max(1, ...props.productosMasVendidos.map((p) => p.cantidad));
});

const porcentajeVendidos = (cantidad) => {
    return Math.round((cantidad / maxVendidos.value) * 100);
};

const iniciales = (texto) => {
    if (!texto) return "?";
    return texto
        .split(" ")
        .slice(0, 2)
        .map((p) => p[0])
        .join("")
        .toUpperCase();
};
</script>

<template>
    <v-container fluid class="dashboard-container">

        <div class="dashboard-header">
            <div>
                <p class="dashboard-subtitle text-xl">Resumen general de tu negocio</p>
            </div>

            <SelectButton
                v-model="periodoSeleccionado"
                :options="periodos"
                optionLabel="label"
                optionValue="value"
                @change="cambiarPeriodo"
                class="periodo-selector"
            />
        </div>

        <!-- Grid de cards -->

        <div class="stats-grid">
            <!-- INGRESOS - destacada -->
            <div class="stat-card stat-featured" @click="irVentas">
                <div class="stat-top-row">
                    <span class="stat-label-featured">INGRESOS</span>
                    <div class="stat-icon-featured">
                        <i class="pi pi-money-bill"></i>
                    </div>
                </div>
                <span class="stat-value-featured">{{
                    formatoMoneda(estadisticas.ingresos)
                }}</span>
                <span class="stat-description-featured">Total vendido</span>
            </div>

            <!-- VENTAS -->
            <div class="stat-card" @click="irVentas">
                <div class="stat-top-row">
                    <span class="stat-label">VENTAS</span>
                    <div class="stat-icon ventas-icon">
                        <i class="pi pi-shopping-cart"></i>
                    </div>
                </div>
                <span class="stat-value">{{ estadisticas.ventas }}</span>
                <span class="stat-description">Ventas registradas</span>
            </div>

            <!-- PRODUCTOS -->
            <div class="stat-card" @click="irProductos">
                <div class="stat-top-row">
                    <span class="stat-label">PRODUCTOS</span>
                    <div class="stat-icon productos-icon">
                        <i class="pi pi-box"></i>
                    </div>
                </div>
                <span class="stat-value">{{ estadisticas.productos }}</span>
                <span class="stat-description">Productos activos</span>
            </div>

            <!-- STOCK BAJO -->
            <div class="stat-card" @click="irStock">
                <div class="stat-top-row">
                    <span class="stat-label">STOCK BAJO</span>
                    <div class="stat-icon stock-icon">
                        <i class="pi pi-exclamation-triangle"></i>
                    </div>
                </div>
                <span class="stat-value">{{ estadisticas.stock_bajo }}</span>
                <span class="stat-description">Productos por reponer</span>
            </div>
        </div>

        <!-- Graficos y má vendidos -->

        <div class="dashboard-grid">
            <!-- GRAFICO -->
            <Card class="dashboard-card">
                <template #title>
                    <div class="section-title">
                        <div>
                            <i class="pi pi-chart-line"></i>
                            <span>Ventas</span>
                        </div>
                        <small>Últimos {{ periodoSeleccionado }} días</small>
                    </div>
                </template>

                <template #content>
                    <div v-if="graficoVentas.length" class="chart-container">
                        <Chart
                            type="line"
                            :data="chartData"
                            :options="chartOptions"
                        />
                    </div>

                    <div v-else class="empty-state">
                        <i class="pi pi-chart-line"></i>
                        <span class="empty-state-title">Aún no hay ventas</span>
                        <span
                            >Cuando registres ventas, aquí verás la
                            tendencia.</span
                        >
                        <Button
                            label="Registrar venta"
                            size="small"
                            icon="pi pi-plus"
                            @click="irVentas"
                        />
                    </div>
                </template>
            </Card>

            <!-- MÁS VENDIDOS -->
            <Card class="dashboard-card">
                <template #title>
                    <div class="section-title">
                        <div>
                            <i class="pi pi-trophy"></i>
                            <span>Más vendidos</span>
                        </div>
                    </div>
                </template>

                <template #content>
                    <div
                        v-if="productosMasVendidos.length"
                        class="product-ranking"
                    >
                        <div
                            v-for="(producto, index) in productosMasVendidos"
                            :key="producto.id"
                            class="ranking-item"
                        >
                            <div
                                class="ranking-number"
                                :class="{ 'ranking-gold': index === 0 }"
                            >
                                {{ index + 1 }}
                            </div>

                            <div class="ranking-info">
                                <div class="ranking-top-row">
                                    <span class="ranking-name">{{
                                        producto.nombre
                                    }}</span>
                                    <strong class="ranking-quantity-inline">{{
                                        producto.cantidad
                                    }}</strong>
                                </div>

                                <ProgressBar
                                    :value="
                                        porcentajeVendidos(producto.cantidad)
                                    "
                                    :showValue="false"
                                    class="ranking-progress"
                                />
                            </div>
                        </div>
                    </div>

                    <div v-else class="empty-state">
                        <i class="pi pi-box"></i>
                        <span class="empty-state-title">Sin datos aún</span>
                        <span>No hay ventas registradas.</span>
                    </div>
                </template>
            </Card>
        </div>

        <!-- Últimas ventas y stock bajo -->

        <div class="dashboard-grid">
            <!-- ÚLTIMAS VENTAS -->
            <Card class="dashboard-card">
                <template #title>
                    <div class="section-title">
                        <div>
                            <i class="pi pi-receipt"></i>
                            <span>Últimas ventas</span>
                        </div>
                        <Button
                            label="Ver todas"
                            text
                            size="small"
                            icon="pi pi-arrow-right"
                            iconPos="right"
                            @click="irVentas"
                        />
                    </div>
                </template>

                <template #content>
                    <div v-if="ultimasVentas.length" class="sales-list">
                        <div
                            v-for="venta in ultimasVentas"
                            :key="venta.id"
                            class="sale-item"
                            @click="verVenta(venta)"
                        >
                            <Avatar
                                :label="iniciales(venta.cliente)"
                                shape="circle"
                                class="sale-avatar"
                            />

                            <div class="sale-info">
                                <span class="sale-number">{{
                                    venta.comprobante
                                }}</span>
                                <span class="sale-client">{{
                                    venta.cliente
                                }}</span>
                                <span class="sale-date">{{ venta.fecha }}</span>
                            </div>

                            <div class="sale-total">
                                {{ formatoMoneda(venta.total) }}
                            </div>
                        </div>
                    </div>

                    <div v-else class="empty-state">
                        <i class="pi pi-receipt"></i>
                        <span class="empty-state-title">Sin ventas aún</span>
                        <span>Registra tu primera venta para verla aquí.</span>
                        <Button
                            label="Registrar venta"
                            size="small"
                            icon="pi pi-plus"
                            @click="registrarVenta"
                        />
                    </div>
                </template>
            </Card>

            <!-- STOCK BAJO -->
            <Card class="dashboard-card">
                <template #title>
                    <div class="section-title">
                        <div>
                            <i class="pi pi-exclamation-triangle"></i>
                            <span>Stock bajo</span>
                        </div>
                        <Button
                            label="Ver productos"
                            text
                            size="small"
                            icon="pi pi-arrow-right"
                            iconPos="right"
                            @click="irStock"
                        />
                    </div>
                </template>

                <template #content>
                    <div v-if="productosStockBajo.length" class="stock-list">
                        <div
                            v-for="producto in productosStockBajo"
                            :key="producto.id"
                            class="stock-item"
                        >
                            <div class="stock-product-icon">
                                <i class="pi pi-box"></i>
                            </div>

                            <div class="stock-info">
                                <div class="stock-top-row">
                                    <span class="stock-name">{{
                                        producto.nombre
                                    }}</span>
                                    <Tag
                                        :value="`${producto.stock} und.`"
                                        :severity="
                                            obtenerSeveridadStock(
                                                producto.stock,
                                            )
                                        "
                                    />
                                </div>

                                <ProgressBar
                                    :value="porcentajeStock(producto.stock)"
                                    :showValue="false"
                                    :class="`stock-progress stock-progress-${obtenerSeveridadStock(producto.stock)}`"
                                />
                            </div>
                        </div>
                    </div>

                    <div v-else class="empty-state success-empty">
                        <i class="pi pi-check-circle"></i>
                        <span class="empty-state-title">Todo en orden</span>
                        <span>No hay productos con stock bajo.</span>
                    </div>
                </template>
            </Card>
        </div>
    </v-container>
</template>

<style scoped>
/* =====================================================
   DASHBOARD
===================================================== */

.dashboard-container {
    padding: 16px;
}

/* =====================================================
   PALETA VINO
===================================================== */

:deep(*) {
    --vino-primary: #7f1d3a;
    --vino-dark: #5b1028;
    --vino-medium: #9f3154;
    --vino-light: #f8e8ed;
    --vino-soft: #fcf5f7;
    --vino-border: #ead3da;
    --vino-text: #3f2029;
    --vino-muted: #8f6f78;
}

/* =====================================================
   HEADER
===================================================== */

.dashboard-header {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 10px;
    margin-bottom: 12px;
}

.dashboard-title {
    margin: 0;
    font-size: 26px;
    font-weight: 700;
    color: #3f2029;
}

.dashboard-subtitle {
    margin: 4px 0 0;
    color: #8f6f78;
    font-size: 15px;
}

.periodo-selector :deep(.p-button) {
    font-size: 13px;
    padding: 0.4rem 0.85rem;
}

/* Botón seleccionado del periodo */

.periodo-selector :deep(.p-button.p-highlight) {
    background: #7f1d3a;
    border-color: #7f1d3a;
    color: #ffffff;
}

.periodo-selector :deep(.p-button:hover) {
    background: #f8e8ed;
    border-color: #9f3154;
    color: #7f1d3a;
}

/* =====================================================
   STATS GRID
===================================================== */

.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 10px;
    margin-bottom: 10px;
}

.stat-card {
    background: #ffffff;
    border-radius: 10px;
    padding: 16px;
    cursor: pointer;
    border: 1px solid #eadfe3;
    display: flex;
    flex-direction: column;
    gap: 2px;
    transition:
        transform 0.15s ease,
        box-shadow 0.15s ease,
        border-color 0.15s ease;
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(91, 16, 40, 0.1);
    border-color: #dcbac5;
}

.stat-top-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 4px;
}

.stat-label {
    font-size: 11px;
    font-weight: 700;
    color: #9b7c85;
    letter-spacing: 0.5px;
}

.stat-icon {
    width: 30px;
    height: 30px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
}

/* =====================================================
   ICONOS STATS
===================================================== */

.ventas-icon {
    background: #f8e8ed;
    color: #7f1d3a;
}

.productos-icon {
    background: #f4e6eb;
    color: #9f3154;
}

.stock-icon {
    background: #f9e9ed;
    color: #8f2948;
}

/* =====================================================
   VALORES STATS
===================================================== */

.stat-value {
    font-size: 24px;
    font-weight: 700;
    color: #3f2029;
    line-height: 1.15;
}

.stat-description {
    font-size: 11px;
    color: #9b7c85;
}

/* =====================================================
   INGRESOS - DESTACADA
===================================================== */

.stat-featured {
    background: linear-gradient(135deg, #7f1d3a 0%, #68132f 100%);
    border: none;
    box-shadow: 0 4px 12px rgba(127, 29, 58, 0.18);
}

.stat-featured:hover {
    border-color: transparent;
    box-shadow: 0 8px 20px rgba(127, 29, 58, 0.25);
}

.stat-label-featured {
    font-size: 11px;
    font-weight: 700;
    color: #f3cbd6;
    letter-spacing: 0.5px;
}

.stat-icon-featured {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.13);
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 13px;
}

.stat-value-featured {
    font-size: 26px;
    font-weight: 700;
    color: #ffffff;
    line-height: 1.15;
}

.stat-description-featured {
    font-size: 11px;
    color: #e9b9c7;
}

/* =====================================================
   GRID PRINCIPAL
===================================================== */

.dashboard-grid {
    display: grid;
    grid-template-columns: minmax(0, 1.6fr) minmax(320px, 1fr);
    gap: 10px;
    margin-bottom: 10px;
}

.dashboard-card {
    min-width: 0;
}

.dashboard-card :deep(.p-card-body) {
    padding: 16px;
    height: 100%;
    display: flex;
    flex-direction: column;
}

.dashboard-card :deep(.p-card-title) {
    margin-bottom: 12px;
}

.dashboard-card :deep(.p-card-content) {
    flex: 1;
    display: flex;
    flex-direction: column;
}

/* =====================================================
   TITULOS DE SECCIÓN
===================================================== */

.section-title {
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 100%;
}

.section-title > div {
    display: flex;
    align-items: center;
    gap: 9px;
}

.section-title i {
    color: #7f1d3a;
    font-size: 17px;
}

.section-title span {
    font-size: 15px;
    font-weight: 700;
    color: #3f2029;
}

.section-title small {
    color: #9b7c85;
    font-size: 12px;
}

/* =====================================================
   GRAFICO
===================================================== */

.chart-container {
    height: 240px;
    width: 100%;
}

/* =====================================================
   MÁS VENDIDOS
===================================================== */

.product-ranking {
    display: flex;
    flex-direction: column;
}

.ranking-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 4px;
    border-bottom: 1px solid #f1e5e9;
}

.ranking-item:last-child {
    border-bottom: none;
}

.ranking-number {
    width: 26px;
    height: 26px;
    min-width: 26px;
    border-radius: 50%;
    background: #f8e8ed;
    color: #7f1d3a;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 12px;
}

.ranking-gold {
    background: #f5dce4;
    color: #7f1d3a;
}

.ranking-info {
    display: flex;
    flex-direction: column;
    flex: 1;
    min-width: 0;
    gap: 6px;
}

.ranking-top-row {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 8px;
}

.ranking-name {
    font-size: 13px;
    font-weight: 600;
    color: #4b3038;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.ranking-quantity-inline {
    font-size: 13px;
    color: #7f1d3a;
    white-space: nowrap;
}

.ranking-progress {
    height: 6px !important;
    border-radius: 4px;
}

.ranking-progress :deep(.p-progressbar-value) {
    background: #7f1d3a;
}

/* =====================================================
   ÚLTIMAS VENTAS
===================================================== */

.sales-list {
    display: flex;
    flex-direction: column;
}

.sale-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 6px;
    border-bottom: 1px solid #f1e5e9;
    cursor: pointer;
    transition: background 0.15s ease;
    border-radius: 8px;
}

.sale-item:last-child {
    border-bottom: none;
}

.sale-item:hover {
    background: #fcf5f7;
}

.sale-avatar {
    background: #f8e8ed !important;
    color: #7f1d3a !important;
    font-weight: 700;
    font-size: 12px;
    min-width: 38px;
}

.sale-info {
    display: flex;
    flex-direction: column;
    flex: 1;
    min-width: 0;
}

.sale-number {
    font-size: 13px;
    font-weight: 700;
    color: #4b3038;
}

.sale-client {
    font-size: 12px;
    color: #806872;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.sale-date {
    font-size: 10px;
    color: #a58b93;
    margin-top: 2px;
}

.sale-total {
    font-size: 14px;
    font-weight: 700;
    color: #7f1d3a;
    white-space: nowrap;
}

/* =====================================================
   STOCK
===================================================== */

.stock-list {
    display: flex;
    flex-direction: column;
}

.stock-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 4px;
    border-bottom: 1px solid #f1e5e9;
}

.stock-item:last-child {
    border-bottom: none;
}

.stock-product-icon {
    width: 38px;
    height: 38px;
    min-width: 38px;
    border-radius: 9px;
    background: #f8e8ed;
    color: #7f1d3a;
    display: flex;
    align-items: center;
    justify-content: center;
}

.stock-info {
    display: flex;
    flex-direction: column;
    flex: 1;
    min-width: 0;
    gap: 6px;
}

.stock-top-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
}

.stock-name {
    font-size: 13px;
    font-weight: 600;
    color: #4b3038;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.stock-progress {
    height: 6px !important;
    border-radius: 4px;
}

.stock-progress-danger :deep(.p-progressbar-value) {
    background: #b4233f;
}

.stock-progress-warn :deep(.p-progressbar-value) {
    background: #b4533c;
}

.stock-progress-success :deep(.p-progressbar-value) {
    background: #7f1d3a;
}

/* =====================================================
   EMPTY STATE
===================================================== */

.empty-state {
    flex: 1;
    min-height: 160px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 8px;
    color: #9b7c85;
    font-size: 13px;
    text-align: center;
    padding: 12px;
}

.empty-state i {
    font-size: 34px;
    color: #d8c1c8;
    margin-bottom: 2px;
}

.empty-state-title {
    font-size: 14px;
    font-weight: 600;
    color: #65424d;
}

.empty-state .p-button {
    margin-top: 6px;
}

.success-empty i {
    color: #7f1d3a;
}

/* =====================================================
   BOTONES PRIMEVUE
===================================================== */
.dashboard-container {
    padding: 16px;
    background: #fcf5f7;
}
.dashboard-container
    :deep(.p-button:not(.p-button-text):not(.p-button-outlined)) {
    background: #7f1d3a;
    border-color: #7f1d3a;
}

.dashboard-container
    :deep(.p-button:not(.p-button-text):not(.p-button-outlined):hover) {
    background: #68132f;
    border-color: #68132f;
}

.dashboard-container :deep(.p-button.p-button-text) {
    color: #7f1d3a;
}

.dashboard-container :deep(.p-button.p-button-text:hover) {
    background: #f8e8ed;
    color: #68132f;
}

/* =====================================================
   RESPONSIVE
===================================================== */

@media (max-width: 1100px) {
    .stats-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .dashboard-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 600px) {
    .dashboard-container {
        padding: 12px;
    }

    .stats-grid {
        grid-template-columns: 1fr;
    }

    .stat-value,
    .stat-value-featured {
        font-size: 22px;
    }

    .dashboard-header {
        align-items: flex-start;
    }

    .periodo-selector {
        width: 100%;
    }

    .periodo-selector :deep(.p-selectbutton) {
        width: 100%;
        display: flex;
    }

    .periodo-selector :deep(.p-button) {
        flex: 1;
    }
}
</style>
