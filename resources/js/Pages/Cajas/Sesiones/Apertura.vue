<script setup>
import { computed, watch } from "vue";
import { useForm, usePage } from "@inertiajs/vue3";

const page = usePage();

const props = defineProps({
    visible: {
        type: Boolean,
        default: false,
    },

    // Cajas disponibles para abrir
    cajas: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(["update:visible", "confirmar-apertura"]);

const usuarioActual = computed(() => page.props.auth?.user);

const form = useForm({
    caja_id: null,
    monto_apertura: null,
    observaciones: "",
});

// Caja seleccionada
const cajaSeleccionada = computed(() => {
    return props.cajas.find((caja) => caja.id === form.caja_id);
});

const puedeGuardar = computed(() => {
    return (
        !!form.caja_id &&
        form.monto_apertura !== null &&
        form.monto_apertura !== undefined &&
        Number(form.monto_apertura) >= 0 &&
        props.cajas.length > 0 &&
        !form.processing
    );
});

// Fecha y hora
const fechaHoraActual = computed(() => {
    const ahora = new Date();

    return ahora.toLocaleString("es-PE", {
        dateStyle: "medium",
        timeStyle: "short",
    });
});

//Limite para el tamaño de la descripción
const observacionLength = computed(() => form.observaciones?.length ?? 0);

// Limpiar formulario cuando se abre
const limpiarFormulario = () => {
    form.reset();
    form.caja_id = null;
    form.monto_apertura = null;
    form.observaciones = "";
    form.clearErrors();
};

//Cuando se abre el modal
watch(
    () => props.visible,
    (visible) => {
        if (visible) {
            limpiarFormulario();
        }
    },
);

//Cerrar modal

const cerrarModal = () => {
    if (form.processing) return;
    emit("update:visible", false);
};

// Procesar apertura
const procesarApertura = () => {
    if (!form.caja_id) {
        form.setError("caja_id", "Debes seleccionar una caja.");
    }

    if (form.monto_apertura === null || form.monto_apertura < 0) {
        form.setError(
            "monto_apertura",
            "El monto de apertura no puede ser negativo.",
        );
        return;
    }

    form.post(route("sesiones-caja.abrir"), {
        preserveScroll: true,
        onSuccess: () => {
            emit("confirmar-apertura");
            limpiarFormulario();
            emit("update:visible", false);
        },
    });
};

const montoEscrito = (event) => {
    form.monto_apertura = event.value;
};
</script>

<template>
    <Dialog
        :visible="visible"
        @update:visible="cerrarModal"
        modal
        header="Apertura de Caja"
        :style="{ width: '450px' }"
        :breakpoints="{
            '960px': '75vw',
            '640px': '95vw',
        }"
        class="apertura-caja-dialog"
    >
        <div class="apertura-container">
            <!-- INFORMACIÓN DE LA APERTURA -->
            <div class="info-card">
                <!-- CAJA -->
                <div class="form-group">
                    <label for="caja"> Caja </label>

                    <Select
                        id="caja"
                        v-model="form.caja_id"
                        :options="props.cajas"
                        optionLabel="nombre"
                        optionValue="id"
                        placeholder="Selecciona una caja"
                        class="w-full"
                        :disabled="form.processing"
                    >
                        <template #empty>
                            No hay cajas registradas
                        </template>
                    </Select>

                    <small v-if="form.errors.caja_id" class="text-red-500">
                        {{ form.errors.caja_id }}
                    </small>

                    <small v-if="cajaSeleccionada" class="caja-seleccionada">
                        <i class="pi pi-check-circle"></i>

                        Se abrirá:
                        <strong>
                            {{ cajaSeleccionada.nombre }}
                        </strong>
                    </small>
                </div>

                <!-- CAJERO -->
                <div class="info-row">
                    <span class="info-label">
                        <i class="pi pi-user"></i>
                        Cajero:
                    </span>

                    <strong class="info-value">
                        {{ usuarioActual?.name || "Usuario actual" }}
                    </strong>
                </div>

                <!-- FECHA -->
                <div class="info-row">
                    <span class="info-label">
                        <i class="pi pi-calendar"></i>
                        Fecha:
                    </span>

                    <span class="info-value-subtle">
                        {{ fechaHoraActual }}
                    </span>
                </div>
            </div>

            <!-- SIN CAJAS -->
            <Message
                v-if="props.cajas.length === 0"
                severity="warn"
                :closable="false"
                icon="pi pi-exclamation-triangle"
            >
                No hay cajas disponibles para abrir.
            </Message>

            <!-- ADVERTENCIA -->
            <Message
                v-else
                severity="warn"
                :closable="false"
                icon="pi pi-exclamation-triangle"
                class="alerta-apertura"
            >
                Asegúrate de contar físicamente el dinero en efectivo antes de
                confirmar la apertura.
            </Message>

            <!-- MONTO INICIAL -->
            <div>
                <FloatLabel variant="on">
                    <IconField>
                        <InputIcon class="pi pi-calculator" />
                        <InputNumber
                            v-model="form.monto_apertura"
                            inputId="monto_apertura"
                            mode="currency"
                            currency="PEN"
                            locale="es-PE"
                            :min="0"
                            class="w-full input-monto-lg"
                            :disabled="form.processing"
                            @input="montoEscrito"
                        />
                    </IconField>
                    <label for="monto_apertura">
                        Monto inicial en efectivo
                    </label>
                </FloatLabel>
            </div>

            <!-- OBSERVACIONES -->
            <div class="form-group">
                <label for="observaciones">
                    Observaciones
                    <span class="opcional">(Opcional)</span>
                </label>

                <Textarea
                    id="observaciones"
                    v-model="form.observaciones"
                    rows="2"
                    class="w-full"
                    :disabled="form.processing"
                />

                <div class="flex justify-end">
                    <small class="text-gray-400">
                        {{ observacionLength }} / 255
                    </small>
                </div>
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
                    label="Abrir caja"
                    icon="pi pi-lock-open"
                    severity="success"
                    :loading="form.processing"
                    :disabled="!puedeGuardar"
                    @click="procesarApertura"
                />
            </div>
        </template>
    </Dialog>
</template>

<style scoped>
.apertura-container {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
    padding-top: 0.5rem;
}

/* INFORMACIÓN */
.info-card {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 1rem;
    display: flex;
    flex-direction: column;
    gap: 0.8rem;
}

.info-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
    font-size: 0.875rem;
}

.info-label {
    color: #64748b;
    display: flex;
    align-items: center;
    gap: 0.4rem;
    white-space: nowrap;
}

.info-value {
    color: #0f172a;
    text-align: right;
}

.info-value-subtle {
    color: #475569;
    font-size: 0.8rem;
}

/* FORMULARIO */
.form-group {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
}

.form-group label {
    font-size: 0.85rem;
    font-weight: 600;
    color: #334155;
}

.opcional {
    font-weight: 400;
    color: #94a3b8;
}

/* CAJA SELECCIONADA */
.caja-seleccionada {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    color: #047857;
    font-size: 0.75rem;
    margin-top: 0.1rem;
}

/* ALERTA */
.alerta-apertura {
    margin: 0 !important;
    font-size: 0.8rem;
}

/* INPUT MONTO */
.input-monto-lg :deep(.p-inputtext) {
    font-size: 1.5rem !important;
    font-weight: 700;
    text-align: center;
    color: #16a34a;
    background: #f0fdf4;
    border-color: #bbf7d0;
}

.input-monto-lg :deep(.p-inputtext:focus) {
    border-color: #22c55e;
    box-shadow: 0 0 0 0.2rem rgba(34, 197, 94, 0.2);
}

.campo-ayuda {
    color: #94a3b8;
    font-size: 0.75rem;
}

/* FOOTER */
.dialog-footer {
    display: flex;
    justify-content: flex-end;
    gap: 0.5rem;
}

/* INPUT MONTO DE APERTURA */
.input-monto-lg :deep(.p-inputtext) {
    font-size: 1.6rem !important;
    font-weight: 700 !important;
    text-align: center;
    color: #16a34a !important;
    background-color: #f0fdf4;
    border-color: #86efac;
    padding: 0.8rem;
}

/* Cuando el input está enfocado */
.input-monto-lg :deep(.p-inputtext:focus) {
    border-color: #22c55e !important;
    box-shadow: 0 0 0 0.2rem rgba(34, 197, 94, 0.15) !important;
}
</style>
