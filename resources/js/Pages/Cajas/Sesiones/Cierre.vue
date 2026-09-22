<script setup>
import { computed, watch } from "vue";
import { useForm, usePage } from "@inertiajs/vue3";

const props = defineProps({
    visible: {
        type: Boolean,
        default: false,
    },

    sesion: {
        type: Object,
        default: null,
    },

    ingresosVarios: {
        type: Number,
        default: 0,
    },

    egresosVarios: {
        type: Number,
        default: 0,
    },
});

const emit = defineEmits([
    "update:visible",
    "confirmar-cierre",
]);

const page = usePage();

/*
|--------------------------------------------------------------------------
| USUARIO AUTENTICADO
|--------------------------------------------------------------------------
*/

const cajero = computed(() => {
    return (
        page.props.auth?.user?.name ||
        page.props.auth?.user?.nombre ||
        "—"
    );
});

const cajaMostrada = computed(() => {
    return props.sesion?.caja ?? "—";
});

const montoApertura = computed(() => {
    return Number(props.sesion?.monto_apertura ?? 0);
});

/*
|--------------------------------------------------------------------------
| FORMULARIO
|--------------------------------------------------------------------------
*/

const form = useForm({
    sesion_caja_id: null,
    monto_cierre_real: null,
    observaciones: "",
});

/*
|--------------------------------------------------------------------------
| MÉTODOS DE PAGO
|--------------------------------------------------------------------------
*/

const metodosPago = computed(() => [
    {
        key: "efectivo",
        label: "Efectivo",
        icon: "pi-money-bill",
        color: "emerald",
        monto: Number(props.sesion?.ventas_efectivo ?? 0),
    },
    {
        key: "yape",
        label: "Yape",
        icon: "pi-mobile",
        color: "purple",
        monto: Number(props.sesion?.ventas_yape ?? 0),
    },
    {
        key: "tarjeta",
        label: "Tarjeta",
        icon: "pi-credit-card",
        color: "blue",
        monto: Number(props.sesion?.ventas_tarjeta ?? 0),
    },
]);

/*
|--------------------------------------------------------------------------
| EFECTIVO ESPERADO
|--------------------------------------------------------------------------
|
| El cálculo existe desde el inicio, pero NO se muestra
| visualmente hasta que el cajero ingrese el monto real.
|
*/

const efectivoEsperado = computed(() => {
    return (
        montoApertura.value +
        Number(props.sesion?.ventas_efectivo ?? 0) +
        Number(props.ingresosVarios ?? 0) -
        Number(props.egresosVarios ?? 0)
    );
});

/*
|--------------------------------------------------------------------------
| TOTAL DE VENTAS
|--------------------------------------------------------------------------
*/

const totalVentasTurno = computed(() => {
    return metodosPago.value.reduce(
        (acc, metodo) => acc + metodo.monto,
        0
    );
});

/*
|--------------------------------------------------------------------------
| ¿YA SE INGRESÓ EL MONTO?
|--------------------------------------------------------------------------
*/

const montoIngresado = computed(() => {
    return (
        form.monto_cierre_real !== null &&
        form.monto_cierre_real !== ""
    );
});

/*
|--------------------------------------------------------------------------
| DIFERENCIA
|--------------------------------------------------------------------------
*/

const diferenciaEfectivo = computed(() => {
    if (!montoIngresado.value) {
        return 0;
    }

    return (
        Number(form.monto_cierre_real) -
        efectivoEsperado.value
    );
});

/*
|--------------------------------------------------------------------------
| ESTADO DEL ARQUEO
|--------------------------------------------------------------------------
*/

const estadoDiferencia = computed(() => {
    if (!montoIngresado.value) {
        return "pendiente";
    }

    if (Math.abs(diferenciaEfectivo.value) < 0.01) {
        return "exacto";
    }

    return diferenciaEfectivo.value > 0
        ? "sobrante"
        : "faltante";
});

/*
|--------------------------------------------------------------------------
| PORCENTAJE
|--------------------------------------------------------------------------
*/

const porcentajeContado = computed(() => {
    if (
        !montoIngresado.value ||
        efectivoEsperado.value <= 0
    ) {
        return 0;
    }

    return Math.min(
        150,
        Math.round(
            (Number(form.monto_cierre_real) /
                efectivoEsperado.value) *
                100
        )
    );
});

/*
|--------------------------------------------------------------------------
| FORMATO MONEDA
|--------------------------------------------------------------------------
*/

const formatoMoneda = (valor) => {
    return new Intl.NumberFormat("es-PE", {
        style: "currency",
        currency: "PEN",
    }).format(valor ?? 0);
};

/*
|--------------------------------------------------------------------------
| LIMPIAR FORMULARIO
|--------------------------------------------------------------------------
*/

const limpiarFormulario = () => {
    form.reset();

    form.sesion_caja_id =
        props.sesion?.id ?? null;

    form.monto_cierre_real = null;
    form.observaciones = "";

    form.clearErrors();
};

/*
|--------------------------------------------------------------------------
| CUANDO SE ABRE EL MODAL
|--------------------------------------------------------------------------
*/

watch(
    () => props.visible,
    (visible) => {
        if (visible) {
            limpiarFormulario();
        }
    }
);

/*
|--------------------------------------------------------------------------
| CERRAR MODAL
|--------------------------------------------------------------------------
*/

const cerrarModal = () => {
    if (form.processing) {
        return;
    }

    emit("update:visible", false);
};

/*
|--------------------------------------------------------------------------
| PROCESAR CIERRE
|--------------------------------------------------------------------------
*/

const procesarCierre = () => {
    console.log("=== INTENTANDO CERRAR CAJA ===");

    console.log(
        "Sesión seleccionada:",
        props.sesion
    );

    console.log(
        "ID sesión:",
        props.sesion?.id
    );

    console.log(
        "Monto cierre:",
        form.monto_cierre_real
    );

    if (!props.sesion?.id) {
        console.error(
            "NO EXISTE ID DE SESIÓN"
        );

        return;
    }

    if (
        form.monto_cierre_real === null ||
        form.monto_cierre_real === ""
    ) {
        form.setError(
            "monto_cierre_real",
            "Debes ingresar el efectivo contado en caja."
        );

        return;
    }

    if (
        Number(form.monto_cierre_real) < 0
    ) {
        form.setError(
            "monto_cierre_real",
            "El monto no puede ser negativo."
        );

        return;
    }

    form.sesion_caja_id =
        props.sesion.id;

    const url = route(
        "sesiones-caja.cerrar",
        {
            sesionCaja:
                props.sesion.id,
        }
    );

    console.log(
        "URL cierre:",
        url
    );

    console.log(
        "Datos enviados:",
        {
            sesion_caja_id:
                form.sesion_caja_id,

            monto_cierre_real:
                form.monto_cierre_real,

            observaciones:
                form.observaciones,
        }
    );

    form.put(url, {
        preserveScroll: true,

        onStart: () => {
            console.log(
                "PETICIÓN PUT INICIADA"
            );
        },

        onSuccess: (page) => {
            console.log(
                "CIERRE EXITOSO:",
                page
            );

            emit(
                "confirmar-cierre",
                {
                    efectivoEsperado:
                        efectivoEsperado.value,

                    efectivoContado:
                        form.monto_cierre_real,

                    diferencia:
                        diferenciaEfectivo.value,
                }
            );

            limpiarFormulario();

            emit(
                "update:visible",
                false
            );
        },

        onError: (errors) => {
            console.error(
                "ERROR DE VALIDACIÓN:",
                errors
            );
        },

        onFinish: () => {
            console.log(
                "PETICIÓN FINALIZADA"
            );
        },
    });
};
</script>

<template>
    <Dialog
        :visible="visible"
        @update:visible="cerrarModal"
        modal
        :style="{ width: '700px' }"
        :breakpoints="{
            '960px': '85vw',
            '640px': '95vw',
        }"
        class="cierre-caja-dialog"
    >
        <!-- HEADER -->

        <template #header>
            <h2 class="dialog-title">
                Arqueo y cierre de caja
            </h2>
        </template>

        <!-- CONTENIDO -->

        <div class="cierre-container">

            <!-- INFORMACIÓN DE SESIÓN -->

            <div class="session-info">

                <div class="session-item">
                    <i class="pi pi-desktop"></i>

                    <div>
                        <span class="session-label">
                            Caja
                        </span>

                        <strong>
                            {{ cajaMostrada }}
                        </strong>
                    </div>
                </div>

                <div class="session-item">
                    <i class="pi pi-user"></i>

                    <div>
                        <span class="session-label">
                            Cajero
                        </span>

                        <strong>
                            {{ cajero }}
                        </strong>
                    </div>
                </div>

                <Tag
                    value="TURNO EN CURSO"
                    severity="success"
                    class="estado-tag"
                />
            </div>

            <!-- RESUMEN -->

            <div class="balance-grid">

                <!-- RESUMEN DEL TURNO -->

                <div class="section-card">

                    <h4 class="card-subtitle">
                        Resumen de turno
                    </h4>

                    <!-- APERTURA -->

                    <div class="data-row">
                        <span>
                            Monto de apertura
                        </span>

                        <strong>
                            {{ formatoMoneda(montoApertura) }}
                        </strong>
                    </div>

                    <Divider class="my-2" />

                    <!-- MÉTODOS DE PAGO -->

                    <div
                        v-for="metodo in metodosPago"
                        :key="metodo.key"
                        class="metodo-row"
                    >
                        <span
                            class="metodo-label"
                            :class="`metodo-${metodo.color}`"
                        >
                            <i
                                class="pi"
                                :class="metodo.icon"
                            ></i>

                            {{ metodo.label }}
                        </span>

                        <strong>
                            {{ formatoMoneda(metodo.monto) }}
                        </strong>
                    </div>

                    <!-- INGRESOS -->

                    <div class="data-row mt-1">
                        <span>
                            Ingresos varios
                        </span>

                        <span class="text-emerald-600">
                            +{{ formatoMoneda(props.ingresosVarios) }}
                        </span>
                    </div>

                    <!-- EGRESOS -->

                    <div class="data-row">
                        <span>
                            Egresos / gastos
                        </span>

                        <span class="text-rose-600">
                            -{{ formatoMoneda(props.egresosVarios) }}
                        </span>
                    </div>

                    <Divider class="my-2" />

                    <!--
                    ======================================================
                    EFECTIVO ESPERADO

                    IMPORTANTE:
                    Solo se muestra después de que el cajero
                    ingrese el monto real.
                    ======================================================
                    -->

                    <div
                        v-if="montoIngresado"
                        class="efectivo-esperado"
                    >
                        <span>
                            Efectivo esperado
                        </span>

                        <strong>
                            {{ formatoMoneda(efectivoEsperado) }}
                        </strong>
                    </div>

                    <!-- MENSAJE INICIAL -->

                    <div
                        v-else
                        class="esperado-oculto"
                    >
                        <i class="pi pi-eye-slash"></i>

                        <span>
                            El efectivo esperado se mostrará
                            después de ingresar el monto contado.
                        </span>
                    </div>

                    <!-- TOTAL VENTAS -->

                    <div class="total-ventas">
                        <span>
                            Total ventas del turno
                        </span>

                        <strong>
                            {{ formatoMoneda(totalVentasTurno) }}
                        </strong>
                    </div>
                </div>

                <!-- CONTEO REAL -->

                <div
                    class="section-card highlight-card"
                >
                    <h4 class="card-subtitle">
                        Conteo real
                    </h4>

                    <p class="conteo-description">
                        Cuenta físicamente el dinero disponible
                        en el cajón e ingresa el monto total.
                    </p>

                    <!-- MONTO -->

                    <div class="monto-field">

                        <FloatLabel variant="on">

                            <IconField>

                                <InputIcon
                                    class="pi pi-calculator"
                                />

                                <InputNumber
                                    v-model="form.monto_cierre_real"
                                    inputId="monto_cierre_real"
                                    mode="currency"
                                    currency="PEN"
                                    locale="es-PE"
                                    :min="0"
                                    class="w-full input-conteo-lg"
                                    :disabled="form.processing"
                                />

                            </IconField>

                            <label for="monto_cierre_real">
                                Monto real contado en caja
                            </label>

                        </FloatLabel>

                        <small
                            v-if="form.errors.monto_cierre_real"
                            class="text-red-500 block mt-1"
                        >
                            {{ form.errors.monto_cierre_real }}
                        </small>
                    </div>

                    <!-- COMPARATIVA -->

                    <div
                        v-if="montoIngresado"
                        class="comparativa"
                    >

                        <div class="comparativa-track">

                            <div
                                class="comparativa-fill"
                                :class="`fill-${estadoDiferencia}`"
                                :style="{
                                    width:
                                        Math.min(
                                            porcentajeContado,
                                            100
                                        ) + '%',
                                }"
                            ></div>

                        </div>

                        <div class="comparativa-labels">

                            <span>
                                Contado
                                {{
                                    formatoMoneda(
                                        form.monto_cierre_real
                                    )
                                }}
                            </span>

                            <span>
                                Esperado
                                {{
                                    formatoMoneda(
                                        efectivoEsperado
                                    )
                                }}
                            </span>

                        </div>
                    </div>

                    <!-- RESULTADO -->

                    <div
                        v-if="montoIngresado"
                        class="balance-result-card"
                        :class="`result-${estadoDiferencia}`"
                    >

                        <div class="result-top">

                            <span>
                                Resultado del arqueo
                            </span>

                            <Tag
                                :value="
                                    estadoDiferencia.toUpperCase()
                                "
                                :severity="
                                    estadoDiferencia === 'exacto'
                                        ? 'success'
                                        : estadoDiferencia === 'sobrante'
                                          ? 'warn'
                                          : 'danger'
                                "
                            />

                        </div>

                        <div class="result-value">

                            <span
                                v-if="diferenciaEfectivo > 0"
                            >
                                +{{
                                    formatoMoneda(
                                        diferenciaEfectivo
                                    )
                                }}
                            </span>

                            <span
                                v-else-if="
                                    diferenciaEfectivo < 0
                                "
                            >
                                {{
                                    formatoMoneda(
                                        diferenciaEfectivo
                                    )
                                }}
                            </span>

                            <span v-else>
                                S/ 0.00 (cuadrado)
                            </span>

                        </div>
                    </div>

                    <!-- ALERTA DE FALTANTE -->

                    <Message
                        v-if="
                            estadoDiferencia === 'faltante'
                        "
                        severity="error"
                        :closable="false"
                        class="mt-2 text-xs"
                    >
                        Existe un faltante en el cajón.
                        Justifica la diferencia en observaciones.
                    </Message>

                </div>
            </div>

            <!-- OBSERVACIONES -->

            <div class="observaciones-field">

                <FloatLabel variant="on">

                    <Textarea
                        id="obs-cierre"
                        v-model="form.observaciones"
                        rows="3"
                        maxlength="255"
                        class="w-full"
                        :disabled="form.processing"
                    />

                    <label for="obs-cierre">
                        Observaciones (Opcional)
                    </label>

                </FloatLabel>

                <div class="observaciones-footer">

                    <small class="contador">
                        {{ form.observaciones?.length ?? 0 }}
                        / 255
                    </small>

                </div>

                <small
                    v-if="form.errors.observaciones"
                    class="text-red-500"
                >
                    {{ form.errors.observaciones }}
                </small>

            </div>
        </div>

        <!-- FOOTER -->

        <template #footer>

            <div class="dialog-footer">

                <Button
                    label="Cancelar"
                    icon="pi pi-times"
                    text
                    severity="secondary"
                    :disabled="form.processing"
                    @click="cerrarModal"
                />

                <Button
                    label="Cerrar caja"
                    icon="pi pi-lock"
                    severity="danger"
                    :disabled="
                        form.processing ||
                        !montoIngresado
                    "
                    :loading="form.processing"
                    @click="procesarCierre"
                />

            </div>
        </template>
    </Dialog>
</template>

<style scoped>

/* =========================================================
   HEADER
========================================================= */

.dialog-title {
    margin: 0;
    font-size: 1.15rem;
    font-weight: 700;
    color: #1e293b;
}

/* =========================================================
   CONTENEDOR
========================================================= */

.cierre-container {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

/* =========================================================
   INFORMACIÓN DE SESIÓN
========================================================= */

.session-info {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 0.25rem 0 0.85rem;
    border-bottom: 1px solid #e2e8f0;
}

.session-item {
    display: flex;
    align-items: center;
    gap: 0.55rem;
    min-width: 0;
}

.session-item > i {
    width: 2rem;
    height: 2rem;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 0.5rem;
    background: #f1f5f9;
    color: #64748b;
    font-size: 0.85rem;
    flex-shrink: 0;
}

.session-item div {
    display: flex;
    flex-direction: column;
    gap: 0.05rem;
}

.session-label {
    font-size: 0.68rem;
    color: #94a3b8;
    font-weight: 500;
}

.session-item strong {
    font-size: 0.82rem;
    color: #334155;
    font-weight: 700;
    white-space: nowrap;
}

.estado-tag {
    font-size: 0.62rem !important;
    font-weight: 700;
}

/* =========================================================
   GRID
========================================================= */

.balance-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}

/* =========================================================
   TARJETAS
========================================================= */

.section-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 1rem;
    display: flex;
    flex-direction: column;
}

.highlight-card {
    background: #f8fafc;
    border-color: #cbd5e1;
}

/* =========================================================
   TITULOS
========================================================= */

.card-subtitle {
    margin: 0 0 0.75rem;
    font-size: 0.85rem;
    font-weight: 700;
    color: #334155;
}

/* =========================================================
   DATOS
========================================================= */

.data-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.825rem;
    margin-bottom: 0.4rem;
    color: #475569;
}

.data-row strong {
    color: #1e293b;
}

/* =========================================================
   MÉTODOS DE PAGO
========================================================= */

.metodo-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.825rem;
    margin-bottom: 0.45rem;
    color: #1e293b;
}

.metodo-label {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    font-weight: 500;
}

.metodo-label i {
    width: 1.35rem;
    height: 1.35rem;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.72rem;
}

.metodo-emerald i {
    background: #ecfdf5;
    color: #059669;
}

.metodo-purple i {
    background: #f5f3ff;
    color: #7c3aed;
}

.metodo-blue i {
    background: #eff6ff;
    color: #2563eb;
}

/* =========================================================
   TOTAL
========================================================= */

.total-ventas {
    margin-top: 0.75rem;
    padding: 0.6rem;
    background: #f1f5f9;
    border-radius: 6px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.78rem;
    color: #475569;
}

.total-ventas strong {
    color: #1e293b;
}

/* =========================================================
   EFECTIVO ESPERADO
========================================================= */

.efectivo-esperado {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 0.5rem;
    padding: 0.75rem 0.85rem;
    border-radius: 8px;
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
    color: #166534;
    font-size: 0.9rem;
    font-weight: 700;
}

.efectivo-esperado strong {
    color: #16a34a;
    font-size: 1.35rem;
    font-weight: 800;
}

/* =========================================================
   EFECTIVO ESPERADO OCULTO
========================================================= */

.esperado-oculto {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-top: 0.5rem;
    padding: 0.65rem 0.75rem;
    border-radius: 8px;
    background: #f8fafc;
    border: 1px dashed #cbd5e1;
    color: #94a3b8;
    font-size: 0.72rem;
    line-height: 1.3;
}

.esperado-oculto i {
    font-size: 0.85rem;
    flex-shrink: 0;
}

/* =========================================================
   CONTEO REAL
========================================================= */

.conteo-description {
    font-size: 0.75rem;
    color: #64748b;
    margin: 0 0 1rem;
}

.monto-field {
    margin-top: 0.5rem;
}

.input-conteo-lg {
    width: 100%;
}

/* =========================================================
   INPUT MONTO
========================================================= */

.input-conteo-lg :deep(.p-inputtext) {
    font-size: 1.5rem !important;
    font-weight: 800 !important;
    text-align: center;
    color: #16a34a !important;
    background: #f0fdf4;
    border-color: #bbf7d0;
    padding: 0.75rem;
}

.input-conteo-lg :deep(.p-inputtext:focus) {
    border-color: #22c55e !important;
    box-shadow: 0 0 0 0.2rem rgba(34, 197, 94, 0.2) !important;
}

/* =========================================================
   COMPARATIVA
========================================================= */

.comparativa {
    margin-top: 0.85rem;
}

.comparativa-track {
    width: 100%;
    height: 8px;
    border-radius: 999px;
    background: #e2e8f0;
    overflow: hidden;
}

.comparativa-fill {
    height: 100%;
    border-radius: 999px;
    transition: width 0.25s ease;
}

.fill-exacto {
    background: #22c55e;
}

.fill-sobrante {
    background: #f59e0b;
}

.fill-faltante {
    background: #ef4444;
}

.comparativa-labels {
    display: flex;
    justify-content: space-between;
    font-size: 0.7rem;
    color: #64748b;
    margin-top: 0.35rem;
}

/* =========================================================
   RESULTADO
========================================================= */

.balance-result-card {
    padding: 0.75rem;
    border-radius: 8px;
    margin-top: 1rem;
    border: 1px solid transparent;
}

.result-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.75rem;
    font-weight: 600;
}

.result-value {
    font-size: 1.25rem;
    font-weight: 800;
    margin-top: 0.25rem;
    text-align: right;
}

.result-exacto {
    background: #f0fdf4;
    border-color: #bbf7d0;
    color: #16a34a;
}

.result-sobrante {
    background: #fffbeb;
    border-color: #fde68a;
    color: #d97706;
}

.result-faltante {
    background: #fef2f2;
    border-color: #fecaca;
    color: #dc2626;
}

/* =========================================================
   OBSERVACIONES
========================================================= */

.observaciones-field {
    width: 100%;
}

.observaciones-field :deep(.p-textarea) {
    resize: vertical;
    min-height: 75px;
}

.observaciones-footer {
    display: flex;
    justify-content: flex-end;
    margin-top: 0.25rem;
}

.contador {
    color: #94a3b8;
    font-size: 0.7rem;
}

/* =========================================================
   FOOTER
========================================================= */

.dialog-footer {
    display: flex;
    justify-content: flex-end;
    gap: 0.5rem;
}

/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 640px) {
    .balance-grid {
        grid-template-columns: 1fr;
    }

    .session-info {
        flex-wrap: wrap;
    }

    .session-item {
        flex: 1 1 auto;
    }

    .estado-tag {
        margin-left: auto;
    }
}

</style>
