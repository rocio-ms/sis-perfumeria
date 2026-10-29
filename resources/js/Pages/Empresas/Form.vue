<script setup>
import { computed, ref, watch } from "vue";
import { useForm } from "@inertiajs/vue3";

import Swal from "sweetalert2";

const props = defineProps({
    empresa: {
        type: [Object, null],
        default: null,
    },

    tiendas: {
        type: Array,
        default: () => [],
    },
});

const visible = defineModel("visible");

const emit = defineEmits(["update:visible", "saved", "cancel"]);

const correlativoBoleta = ref(0);
const correlativoFactura = ref(0);

const form = useForm({
    tienda_id: null,

    ruc: "",

    razon_social: "",

    activo: true,

    series: {
        boleta: {
            serie: "B001",
        },

        factura: {
            serie: "F001",
        },
    },
});

const esEdicion = computed(() => {
    return props.empresa != null;
});

const titulo = computed(() => {
    return esEdicion.value ? "Editar empresa" : "Nueva empresa";
});

const subtitulo = computed(() => {
    return esEdicion.value
        ? "Actualiza la información de la empresa"
        : "Registra una nueva empresa para tu tienda";
});

const formatearCorrelativo = (valor) => {
    const numero = Number(valor ?? 0);

    return String(Number.isFinite(numero) && numero >= 0 ? numero : 0).padStart(
        6,
        "0",
    );
};

const limpiarFormulario = () => {
    form.reset();

    form.clearErrors();

    form.tienda_id = null;

    form.ruc = "";

    form.razon_social = "";

    form.activo = true;

    form.series = {
        boleta: {
            serie: "B001",
        },

        factura: {
            serie: "F001",
        },
    };

    correlativoBoleta.value = 0;

    correlativoFactura.value = 0;
};

const buscarSerie = (series, palabrasClave) => {
    if (!Array.isArray(series)) {
        return null;
    }

    return (
        series.find((serie) => {
            const nombre =
                serie?.tipo_comprobante?.nombre?.toString().toLowerCase() ?? "";

            return palabrasClave.some((palabra) =>
                nombre.includes(palabra.toLowerCase()),
            );
        }) ?? null
    );
};

const cargarEmpresa = (empresa) => {
    form.clearErrors();

    form.tienda_id = empresa.tienda_id ?? null;

    form.ruc = empresa.ruc ?? "";

    form.razon_social = empresa.razon_social ?? "";

    form.activo = Boolean(empresa.activo);

    const seriesEmpresa = empresa.series_comprobantes ?? empresa.series ?? [];

    const serieBoleta = buscarSerie(seriesEmpresa, ["boleta", "boleto"]);

    const serieFactura = buscarSerie(seriesEmpresa, ["factura"]);

    form.series = {
        boleta: {
            serie: serieBoleta?.serie ?? "B001",
        },

        factura: {
            serie: serieFactura?.serie ?? "F001",
        },
    };

    correlativoBoleta.value = Number(serieBoleta?.correlativo_actual ?? 0);

    correlativoFactura.value = Number(serieFactura?.correlativo_actual ?? 0);
};

watch(
    () => props.empresa,

    (empresa) => {
        if (empresa) {
            cargarEmpresa(empresa);
        } else {
            limpiarFormulario();
        }
    },

    {
        immediate: true,
    },
);

watch(
    visible,

    (nuevoValor) => {
        if (!nuevoValor) {
            return;
        }

        if (props.empresa) {
            cargarEmpresa(props.empresa);
        } else {
            limpiarFormulario();
        }
    },
);

const normalizarSerie = (tipo) => {
    if (!form.series?.[tipo]) {
        return;
    }

    form.series[tipo].serie = String(form.series[tipo].serie ?? "")
        .toUpperCase()
        .replace(/[^A-Z0-9]/g, "")
        .slice(0, 4);
};

// Guardar

const guardar = async () => {
    if (form.processing) {
        return;
    }

    normalizarSerie("boleta");

    normalizarSerie("factura");

    if (!form.series.boleta.serie || form.series.boleta.serie.length !== 4) {
        await Swal.fire({
            title: "Serie inválida",

            text: "La serie de Boleta debe tener exactamente 4 caracteres.",

            icon: "warning",

            confirmButtonText: "Aceptar",
        });

        return;
    }

    if (!form.series.factura.serie || form.series.factura.serie.length !== 4) {
        await Swal.fire({
            title: "Serie inválida",

            text: "La serie de Factura debe tener exactamente 4 caracteres.",

            icon: "warning",

            confirmButtonText: "Aceptar",
        });

        return;
    }

    if (esEdicion.value) {
        form.put(
            route("empresas.update", props.empresa.id),

            {
                preserveScroll: true,

                onSuccess: async () => {
                    limpiarFormulario();

                    visible.value = false;

                    emit("saved");

                    await Swal.fire({
                        title: "¡Actualizado!",

                        text: "La empresa y sus series fueron actualizadas correctamente.",

                        icon: "success",

                        confirmButtonText: "Aceptar",
                    });
                },

                onError: () => {},
            },
        );

        return;
    }

    form.post(
        route("empresas.store"),

        {
            preserveScroll: true,

            onSuccess: async () => {
                limpiarFormulario();

                visible.value = false;

                emit("saved");

                await Swal.fire({
                    title: "¡Guardado!",

                    text: "La empresa y sus series fueron registradas correctamente.",

                    icon: "success",

                    confirmButtonText: "Aceptar",
                });
            },

            onError: () => {},
        },
    );
};

// Cerrar formulario

const cerrar = () => {
    limpiarFormulario();

    visible.value = false;

    emit("cancel");
};
</script>

<template>
    <Dialog
        v-model:visible="visible"
        modal
        :style="{ width: '700px' }"
        :closable="!form.processing"
        :dismissableMask="!form.processing"
    >
        <template #header>
            <div>
                <h2 class="text-xl font-semibold m-0">
                    {{ titulo }}
                </h2>

                <p class="text-sm text-gray-500 mt-1 mb-0">
                    {{ subtitulo }}
                </p>
            </div>
        </template>

        <div class="flex flex-col gap-5 mt-3">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- TIENDA -->

                <div class="flex flex-col gap-1">
                    <FloatLabel variant="on">
                        <Select
                            id="tienda_id"
                            v-model="form.tienda_id"
                            :options="props.tiendas"
                            optionLabel="nombre_comercial"
                            optionValue="id"
                            class="w-full"
                            :class="{
                                'p-invalid': form.errors.tienda_id,
                            }"
                            emptyMessage="No existen tiendas disponibles"
                        />

                        <label for="tienda_id">
                            Tienda

                            <span class="text-red-500"> * </span>
                        </label>
                    </FloatLabel>

                    <small
                        v-if="form.errors.tienda_id"
                        class="text-red-500 mt-1 block"
                    >
                        {{ form.errors.tienda_id }}
                    </small>
                </div>

                <!-- RUC -->

                <div class="flex flex-col gap-1">
                    <FloatLabel variant="on">
                        <InputText
                            id="ruc"
                            v-model="form.ruc"
                            class="w-full"
                            :class="{
                                'p-invalid': form.errors.ruc,
                            }"
                            maxlength="11"
                            inputmode="numeric"
                            @input="
                                form.ruc = form.ruc
                                    .replace(/\D/g, '')
                                    .slice(0, 11)
                            "
                        />

                        <label for="ruc">
                            RUC

                            <span class="text-red-500"> * </span>
                        </label>
                    </FloatLabel>

                    <small
                        v-if="
                            form.ruc &&
                            form.ruc.length !== 11 &&
                            !form.errors.ruc
                        "
                        class="text-red-500 text-xs"
                    >
                        El RUC debe tener 11 dígitos
                    </small>

                    <small
                        v-if="form.errors.ruc"
                        class="text-red-500 mt-1 block"
                    >
                        {{ form.errors.ruc }}
                    </small>
                </div>
            </div>

            <div class="flex flex-col gap-1">
                <FloatLabel variant="on">
                    <InputText
                        id="razon_social"
                        v-model="form.razon_social"
                        class="w-full"
                        :invalid="!!form.errors.razon_social"
                    />

                    <label for="razon_social">
                        Razón social

                        <span class="text-red-500"> * </span>
                    </label>
                </FloatLabel>

                <small v-if="form.errors.razon_social" class="text-red-500">
                    {{ form.errors.razon_social }}
                </small>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="flex flex-col gap-1">
                    <FloatLabel variant="on">
                        <InputText
                            id="serie_boleta"
                            v-model="form.series.boleta.serie"
                            class="w-full"
                            :class="{
                                'p-invalid': form.errors['series.boleta.serie'],
                            }"
                            maxlength="4"
                            @input="normalizarSerie('boleta')"
                        />

                        <label for="serie_boleta">
                            Serie de Boleta

                            <span class="text-red-500"> * </span>
                        </label>
                    </FloatLabel>
                </div>

                <div class="flex flex-col gap-1">
                    <FloatLabel variant="on">
                        <InputText
                            id="serie_factura"
                            v-model="form.series.factura.serie"
                            class="w-full"
                            :class="{
                                'p-invalid':
                                    form.errors['series.factura.serie'],
                            }"
                            maxlength="4"
                            @input="normalizarSerie('factura')"
                        />

                        <label for="serie_factura">
                            Serie de Factura

                            <span class="text-red-500"> * </span>
                        </label>
                    </FloatLabel>

                    <small
                        v-if="form.errors['series.factura.serie']"
                        class="text-red-500"
                    >
                        {{ form.errors["series.factura.serie"] }}
                    </small>
                </div>
            </div>
            <div class="flex gap-3 mt-4 p-3 rounded-lg bg-gray-50">
                <i class="pi pi-info-circle text-primary mt-1"></i>

                <div>
                    <p class="text-sm font-medium m-0">Sobre la numeración</p>

                    <p class="text-xs text-gray-500 mt-1 mb-0">
                        Cada empresa tiene sus propias series y correlativos. El
                        número correlativo se incrementará automáticamente al
                        registrar una venta y no puede modificarse manualmente.
                    </p>
                </div>
            </div>

            <div class="grid grid.cols.zz">
                <!-- ===================================================== -->
                <!-- ESTADO -->
                <!-- ===================================================== -->

                <div class="flex items-center justify-between">
                    <div>
                        <label for="activo" class="font-medium block">
                            Estado de la empresa
                        </label>

                        <small class="text-gray-500">
                            {{
                                form.activo
                                    ? "La empresa está activa"
                                    : "La empresa está inactiva"
                            }}
                        </small>
                    </div>

                    <ToggleSwitch v-model="form.activo" inputId="activo" />
                </div>
            </div>
        </div>
        <!-- ========================================================= -->
        <!-- FOOTER -->
        <!-- ========================================================= -->

        <template #footer>
            <Button
                label="Cancelar"
                severity="secondary"
                variant="outlined"
                :disabled="form.processing"
                @click="cerrar"
            />

            <Button
                :label="esEdicion ? 'Actualizar' : 'Guardar'"
                icon="pi pi-save"
                :loading="form.processing"
                @click="guardar"
            />
        </template>
    </Dialog>
</template>
