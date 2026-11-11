<script setup>
import { ref, onMounted, computed } from "vue";
import { router, usePage } from "@inertiajs/vue3";
import axios from "axios";

import AppLayout from "@/Layouts/AppLayout.vue";
import Card from "@/Components/Card.vue";
import DataTableBase from "@/Components/DataTableBase.vue";
import Apertura from "./Apertura.vue";
import Cierre from "./Cierre.vue";
import Show from "./Show.vue";

defineOptions({
    layout: AppLayout,
});

const props = defineProps({
    sesiones: {
        type: Array,
        default: () => [],
    },

    cajasDisponibles: {
        type: Array,
        default: () => [],
    },

    filters: {
        type: Object,
        default: () => ({
            search: "",
        }),
    },
});

const mostrarApertura = ref(false);
const mostrarCierre = ref(false);
const mostrarMovimiento = ref(false);
const guardandoMovimiento = ref(false);
const errorMovimiento = ref("");
const movimiento = ref({ tipo: "ingreso", concepto: "", monto: null });
const tiposMovimiento = [
    { label: "Ingreso", value: "ingreso", icon: "pi pi-arrow-down-left" },
    { label: "Egreso", value: "egreso", icon: "pi pi-arrow-up-right" },
];
const page = usePage();
const sesionPropia = computed(() => props.sesiones.find(
    (sesion) => sesion.estado === "abierta" && sesion.user_id === page.props.auth?.user?.id,
));

const abrirMovimiento = () => {
    movimiento.value = { tipo: "ingreso", concepto: "", monto: null };
    errorMovimiento.value = "";
    mostrarMovimiento.value = true;
};

const registrarMovimiento = async () => {
    if (!sesionPropia.value || guardandoMovimiento.value) return;

    const { tipo, monto } = movimiento.value;
    const concepto = movimiento.value.concepto.trim();
    if (!concepto || !Number.isFinite(monto) || monto < 0.01) {
        errorMovimiento.value = "Ingresa un concepto y un monto de al menos S/ 0.01.";
        return;
    }

    errorMovimiento.value = "";
    guardandoMovimiento.value = true;
    try {
        await axios.post(route("movimientos-caja.store"), {
            sesion_caja_id: sesionPropia.value.id,
            tipo,
            concepto,
            monto,
        });
        mostrarMovimiento.value = false;
        router.reload({ only: ["sesiones"] });
    } catch (error) {
        errorMovimiento.value = Object.values(error.response?.data?.errors ?? {}).flat()[0]
            ?? error.response?.data?.message
            ?? "No se pudo registrar el movimiento.";
    } finally {
        guardandoMovimiento.value = false;
    }
};

const sesionSeleccionada = ref(null);

const mostrarShow = ref(false);

const home = ref({
    icon: "pi pi-home",
    command: () => {
        router.visit("/dashboard");
    },
});

const items = ref([
    {
        label: "Sesiones de Caja",
    },
]);

const columns = [
    {
        field: "caja",
        header: "Caja",
    },
    {
        field: "usuario",
        header: "Usuario",
    },
    {
        field: "fecha_apertura",
        header: "Fecha apertura",
        type: "date",
    },
    {
        field: "fecha_cierre",
        header: "Fecha cierre",
        type: "date",
    },
    {
        field: "monto_apertura",
        header: "Monto apertura",
    },
    {
        field: "monto_cierre_real",
        header: "Monto cierre",
    },
    {
        field: "estado",
        header: "Estado",
    },
];

const sesionesTabla = computed(() => {
    return props.sesiones.map((sesion) => ({
        ...sesion,

        caja: sesion.caja?.nombre ?? "Sin caja",

        usuario: sesion.usuario?.name ?? "Sin usuario",

        estado: sesion.estado
            ? sesion.estado.charAt(0).toUpperCase() + sesion.estado.slice(1)
            : "",

        fecha_apertura: sesion.fecha_apertura,

        fecha_cierre: sesion.fecha_cierre ? sesion.fecha_cierre : "Pendiente",
        monto_apertura: Number(sesion.monto_apertura ?? 0).toFixed(2),

        monto_cierre_teorico: Number(sesion.monto_cierre_teorico ?? 0).toFixed(
            2,
        ),

        monto_cierre_real: Number(sesion.monto_cierre_real ?? 0).toFixed(2),

        diferencia: Number(sesion.diferencia ?? 0).toFixed(2),
    }));
});

const abrirApertura = () => {
    sesionSeleccionada.value = null;
    mostrarApertura.value = true;
};

const abrirCierre = (sesion) => {
    console.log("SESION SELECCIONADA PARA CIERRE:", sesion);
    sesionSeleccionada.value = sesion;
    mostrarCierre.value = true;
};

const verSesion = (sesion) => {
    console.log("ID SELECCIONADO:", sesion.id);

    const sesionOriginal = props.sesiones.find(
        (item) => item.id === sesion.id,
    );

    console.log("SESION ORIGINAL:", sesionOriginal);
    console.log("VENTAS DE SESION ORIGINAL:", sesionOriginal?.ventas);
    console.log("CANTIDAD DE VENTAS:", sesionOriginal?.ventas?.length ?? 0);

    sesionSeleccionada.value = sesionOriginal;
    mostrarShow.value = true;
};
const formularioGuardado = () => {
    mostrarApertura.value = false;
    mostrarCierre.value = false;
    mostrarShow.value = false;
    sesionSeleccionada.value = null;

    router.reload({
        only: ["sesiones"],
    });
};

const cerrarForm = () => {
    mostrarApertura.value = false;
    mostrarCierre.value = false;
    mostrarShow.value = false;
    sesionSeleccionada.value = null;
};
</script>

<template>
    <v-container fluid>
        <Card>
            <template #title>
                <div class="card flex justify-content-center mb-4">
                    <Breadcrumb :home="home" :model="items" />
                </div>

                <!-- TÍTULO -->
                <div
                    class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4"
                >
                    <div>
                        <h1 class="text-2xl font-bold text-gray-800">
                            Sesiones de Caja
                        </h1>

                        <p class="text-sm text-gray-500">
                            Administra las aperturas y cierres de caja, controla
                            los movimientos y consulta el estado de cada sesión
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <Button
                            v-if="sesionPropia"
                            label="Registrar movimiento"
                            icon="pi pi-wallet"
                            severity="secondary"
                            outlined
                            @click="abrirMovimiento"
                        />
                        <Button
                            label="Abrir caja"
                            icon="pi pi-lock-open"
                            severity="success"
                            @click="abrirApertura"
                        />
                    </div>
                </div>
            </template>

            <template #content>
                <DataTableBase
                    :data="sesionesTabla"
                    :columns="columns"
                    :mostrarCrear="false"
                    :loading="false"
                    :showView="true"
                    :showEdit="false"
                    :showDelete="false"
                    :showClose="true"
                    @ver="verSesion"
                    @cerrar="abrirCierre"
                />
            </template>
        </Card>

        <!-- Apertura -->
        <Apertura
            v-model:visible="mostrarApertura"
            :cajas="props.cajasDisponibles"
            @confirmar-apertura="formularioGuardado"
            @cancel="cerrarForm"
        />

        <!-- Cierre -->
        <Cierre
            v-model:visible="mostrarCierre"
            :sesion="sesionSeleccionada"
            :ingresos-varios="Number(sesionSeleccionada?.ingresos_varios ?? 0)"
            :egresos-varios="Number(sesionSeleccionada?.egresos_varios ?? 0)"
            @confirmar-cierre="formularioGuardado"
            @cancel="cerrarForm"
        />

        <Show v-model:visible="mostrarShow" :sesion="sesionSeleccionada" />

        <Dialog
            v-model:visible="mostrarMovimiento"
            header="Registrar movimiento de caja"
            modal
            :style="{ width: '29rem', maxWidth: '92vw' }"
            :closable="!guardandoMovimiento"
            :close-on-escape="!guardandoMovimiento"
        >
            <form id="movimiento-caja-form" class="grid gap-5 pt-1" @submit.prevent="registrarMovimiento">
                <div v-if="sesionPropia" class="flex items-center gap-3 rounded-xl bg-rose-50 p-3">
                    <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-white text-rose-800">
                        <i class="pi pi-wallet" aria-hidden="true"></i>
                    </span>
                    <div class="min-w-0">
                        <p class="text-xs text-rose-700">Caja abierta</p>
                        <p class="break-words font-semibold text-slate-900">{{ sesionPropia.caja?.nombre }}</p>
                    </div>
                </div>

                <div class="grid gap-2">
                    <span id="movimiento-tipo" class="text-sm font-medium text-slate-700">Tipo de movimiento</span>
                    <SelectButton
                        v-model="movimiento.tipo"
                        :options="tiposMovimiento"
                        option-label="label"
                        option-value="value"
                        aria-labelledby="movimiento-tipo"
                        :allow-empty="false"
                        :disabled="guardandoMovimiento"
                        class="w-full [&>button]:flex-1"
                    >
                        <template #option="{ option }">
                            <i :class="option.icon" aria-hidden="true"></i>
                            <span>{{ option.label }}</span>
                        </template>
                    </SelectButton>
                </div>

                <div class="grid gap-2">
                    <label for="movimiento-concepto" class="text-sm font-medium text-slate-700">Concepto</label>
                    <IconField>
                        <InputIcon class="pi pi-file-edit" />
                        <InputText
                            id="movimiento-concepto"
                            v-model="movimiento.concepto"
                            placeholder="Ej. Pago a proveedor"
                            required
                            maxlength="255"
                            :disabled="guardandoMovimiento"
                            class="w-full"
                        />
                    </IconField>
                </div>

                <div class="grid gap-2">
                    <label for="movimiento-monto" class="text-sm font-medium text-slate-700">Monto</label>
                    <InputNumber
                        v-model="movimiento.monto"
                        input-id="movimiento-monto"
                        mode="currency"
                        currency="PEN"
                        locale="es-PE"
                        placeholder="S/ 0.00"
                        :min="0"
                        :min-fraction-digits="2"
                        :max-fraction-digits="2"
                        :disabled="guardandoMovimiento"
                        input-class="w-full font-semibold"
                        fluid
                    />
                </div>

                <Message v-if="errorMovimiento" severity="error" :closable="false">
                    {{ errorMovimiento }}
                </Message>
            </form>

            <template #footer>
                <Button
                    type="button"
                    label="Cancelar"
                    severity="secondary"
                    outlined
                    :disabled="guardandoMovimiento"
                    @click="mostrarMovimiento = false"
                />
                <Button
                    type="submit"
                    form="movimiento-caja-form"
                    label="Guardar movimiento"
                    icon="pi pi-check"
                    :loading="guardandoMovimiento"
                    :disabled="!sesionPropia"
                />
            </template>
        </Dialog>
    </v-container>
</template>
