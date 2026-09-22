<script setup>
import { computed, ref } from "vue";
import { router } from "@inertiajs/vue3";
import MovimientosCajaDialog from "./MovimientosCajaDialog.vue";

const props = defineProps({
    visible: {
        type: Boolean,
        default: false,
    },

    sesion: {
        type: Object,
        default: null,
    },
});

const emit = defineEmits(["update:visible"]);

const cajaMostrada = computed(() => {
    return props.sesion?.caja?.nombre || props.sesion?.caja || "—";
});

const usuarioMostrado = computed(() => {
    return props.sesion?.usuario?.name || props.sesion?.usuario || "—";
});

const montoApertura = computed(() => {
    return Number(props.sesion?.monto_apertura ?? 0);
});

const montoCierreTeorico = computed(() => {
    return Number(props.sesion?.monto_cierre_teorico ?? 0);
});

const montoCierreReal = computed(() => {
    return Number(props.sesion?.monto_cierre_real ?? 0);
});

const diferencia = computed(() => {
    return Number(props.sesion?.diferencia ?? 0);
});

const estado = computed(() => {
    return props.sesion?.estado
        ? props.sesion.estado.charAt(0).toUpperCase() +
              props.sesion.estado.slice(1)
        : "—";
});

const esCerrada = computed(() => {
    return String(props.sesion?.estado || "").toLowerCase() === "cerrada";
});

const formatoMoneda = (valor) => {
    return new Intl.NumberFormat("es-PE", {
        style: "currency",
        currency: "PEN",
    }).format(Number(valor ?? 0));
};

const formatearFecha = (fecha) => {
    if (!fecha) return "—";

    return new Date(fecha).toLocaleString("es-PE", {
        day: "2-digit",
        month: "2-digit",
        year: "numeric",
        hour: "2-digit",
        minute: "2-digit",
    });
};

const cerrarModal = () => {
    emit("update:visible", false);
};

const mostrarMovimientos = ref(false);

const verMovimientos = () => {
    console.log("SESION EN SHOW:", props.sesion);
    console.log("VENTAS EN SHOW:", props.sesion?.ventas);
    console.log(
        "CANTIDAD DE VENTAS EN SHOW:",
        props.sesion?.ventas?.length ?? 0,
    );

    mostrarMovimientos.value = true;
};
</script>

<template>
    <Dialog
        :visible="visible"
        @update:visible="(value) => emit('update:visible', value)"
        modal
        header="Resumen de caja"
        :style="{ width: '650px' }"
        :breakpoints="{
            '960px': '80vw',
            '640px': '95vw',
        }"
        class="show-caja-dialog"
        :pt="{
            header: {
                class: 'pb-3',
            },
        }"
    >
        <div class="resumen-container">
            <!-- INFORMACIÓN DE LA SESIÓN -->
            <div class="sesion-header">
                <div class="sesion-info">
                    <div>
                        <span class="info-label">Caja</span>
                        <strong class="info-value">
                            {{ cajaMostrada }}
                        </strong>
                    </div>

                    <div>
                        <span class="info-label">Cajero</span>
                        <strong class="info-value">
                            {{ usuarioMostrado }}
                        </strong>
                    </div>

                    <div>
                        <span class="info-label">Apertura</span>
                        <span class="info-value">
                            {{ formatearFecha(props.sesion?.fecha_apertura) }}
                        </span>
                    </div>
                </div>

                <Tag
                    :value="estado.toUpperCase()"
                    :severity="esCerrada ? 'secondary' : 'success'"
                    class="estado-tag"
                />
            </div>

            <!-- RESUMEN PRINCIPAL -->
            <div class="resumen-grid">
                <!-- APERTURA -->
                <div class="resumen-card">
                    <span class="resumen-label"> Monto de apertura </span>

                    <strong class="resumen-value">
                        {{ formatoMoneda(montoApertura) }}
                    </strong>
                </div>

                <!-- CIERRE TEÓRICO -->
                <div class="resumen-card">
                    <span class="resumen-label"> Cierre teórico </span>

                    <strong class="resumen-value">
                        {{ formatoMoneda(montoCierreTeorico) }}
                    </strong>
                </div>

                <!-- CIERRE REAL -->
                <div class="resumen-card">
                    <span class="resumen-label"> Cierre real </span>

                    <strong class="resumen-value">
                        {{ formatoMoneda(montoCierreReal) }}
                    </strong>
                </div>
            </div>

            <!-- RESULTADO -->
            <div
                v-if="esCerrada"
                class="resultado-card"
                :class="{
                    'resultado-exacto': diferencia === 0,
                    'resultado-sobrante': diferencia > 0,
                    'resultado-faltante': diferencia < 0,
                }"
            >
                <div>
                    <span class="resultado-label"> Diferencia de caja </span>

                    <span class="resultado-description">
                        Comparación entre el cierre teórico y el efectivo
                        contado
                    </span>
                </div>

                <strong class="resultado-value">
                    <span v-if="diferencia > 0">
                        +{{ formatoMoneda(diferencia) }}
                    </span>

                    <span v-else>
                        {{ formatoMoneda(diferencia) }}
                    </span>
                </strong>
            </div>

            <Divider />

            <!-- INFORMACIÓN DEL CIERRE -->
            <div class="detalle-section">
                <h4>Información de la sesión</h4>

                <div class="detalle-row">
                    <span>Fecha de apertura</span>
                    <strong>
                        {{ formatearFecha(props.sesion?.fecha_apertura) }}
                    </strong>
                </div>

                <div class="detalle-row">
                    <span>Fecha de cierre</span>

                    <strong
                        v-if="esCerrada && props.sesion?.fecha_cierre"
                        class="fecha-cierre"
                    >
                        {{ formatearFecha(props.sesion.fecha_cierre) }}
                    </strong>

                    <span v-else-if="!esCerrada" class="caja-abierta">
                        <i class="pi pi-lock-open"></i>
                        La caja está abierta
                    </span>

                    <strong v-else class="fecha-pendiente"> Pendiente </strong>
                </div>

                <div class="detalle-row">
                    <span>Estado</span>

                    <Tag
                        :value="estado"
                        :severity="esCerrada ? 'secondary' : 'success'"
                    />
                </div>

                <div v-if="props.sesion?.observaciones" class="observaciones">
                    <span>Observaciones</span>

                    <p>
                        {{ props.sesion.observaciones }}
                    </p>
                </div>
            </div>
        </div>

        <!-- FOOTER -->
        <template #footer>
            <div class="dialog-footer">
                <Button
                    label="Cerrar"
                    icon="pi pi-times"
                    text
                    severity="secondary"
                    @click="cerrarModal"
                />

                <Button
                    label="Ver movimientos"
                    icon="pi pi-list"
                    severity="primary"
                    :disabled="!props.sesion?.id"
                    @click="verMovimientos"
                />
            </div>
        </template>
    </Dialog>

    <MovimientosCajaDialog
        v-model:visible="mostrarMovimientos"
        :ventas="props.sesion?.ventas ?? []"
    />
</template>

<style scoped>
.resumen-container {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

/* INFORMACIÓN DE SESIÓN */

.sesion-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;

    padding: 1rem 1.1rem;

    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
}

.sesion-info {
    display: flex;
    align-items: center;
    gap: 1.5rem;
    flex-wrap: wrap;
}

.sesion-info > div {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
}

.info-label {
    font-size: 0.7rem;
    color: #64748b;
    font-weight: 500;
}

.info-value {
    font-size: 0.85rem;
    color: #1e293b;
}

.estado-tag {
    font-size: 0.65rem !important;
    font-weight: 700;
    white-space: nowrap;
}

/* RESUMEN */

.resumen-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 0.75rem;
}

.resumen-card {
    padding: 1rem;

    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 10px;

    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}

.resumen-label {
    font-size: 0.75rem;
    color: #64748b;
}

.resumen-value {
    font-size: 1.15rem;
    color: #1e293b;
}

/* RESULTADO */

.resultado-card {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;

    padding: 1rem;

    border-radius: 10px;
    border: 1px solid transparent;
}

.resultado-label {
    display: block;
    font-size: 0.8rem;
    font-weight: 700;
}

.resultado-description {
    display: block;
    margin-top: 0.2rem;
    font-size: 0.7rem;
    opacity: 0.75;
}

.resultado-value {
    font-size: 1.25rem;
    white-space: nowrap;
}

.resultado-exacto {
    background: #f0fdf4;
    border-color: #bbf7d0;
    color: #15803d;
}

.resultado-sobrante {
    background: #fffbeb;
    border-color: #fde68a;
    color: #b45309;
}

.resultado-faltante {
    background: #fef2f2;
    border-color: #fecaca;
    color: #dc2626;
}

/* DETALLES */

.detalle-section {
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
}

.detalle-section h4 {
    margin: 0 0 0.25rem;

    font-size: 0.85rem;
    font-weight: 700;
    color: #334155;
}

.detalle-row {
    display: flex;
    justify-content: space-between;
    align-items: center;

    font-size: 0.8rem;
    color: #64748b;
}

.detalle-row strong {
    color: #1e293b;
    font-weight: 600;
}

.observaciones {
    padding: 0.75rem;

    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
}

.observaciones span {
    display: block;

    font-size: 0.75rem;
    font-weight: 700;
    color: #475569;

    margin-bottom: 0.35rem;
}

.observaciones p {
    margin: 0;

    font-size: 0.78rem;
    color: #64748b;
    line-height: 1.5;
}

/* FOOTER */

.dialog-footer {
    width: 100%;

    display: flex;
    justify-content: flex-end;
    align-items: center;

    gap: 0.5rem;
}

/* RESPONSIVE */

@media (max-width: 640px) {
    .sesion-header {
        align-items: flex-start;
        flex-direction: column;
    }

    .sesion-info {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.65rem;
    }

    .resumen-grid {
        grid-template-columns: 1fr;
    }

    .resultado-card {
        align-items: flex-start;
        flex-direction: column;
    }
}
.fecha-cierre {
    color: #1e293b;
    font-weight: 600;
}

.caja-abierta {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.3rem 0.6rem;
    border-radius: 6px;
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
    color: #15803d;
    font-size: 0.75rem;
    font-weight: 600;
}

.caja-abierta i {
    font-size: 0.7rem;
}

.fecha-pendiente {
    color: #94a3b8;
    font-weight: 500;
}
</style>
