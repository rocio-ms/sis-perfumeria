<script setup>
import { ref, computed, watch } from "vue";
import { useForm } from "@inertiajs/vue3";
import Swal from "sweetalert2";

const props = defineProps({
    proveedor: {
        type: Object,
        default: null,
    },
});

const visible = defineModel("visible");

const emit = defineEmits(["update:visible", "saved", "cancel"]);

const form = useForm({
    razon_social: "",
    nombre_comercial: "",
    tipo_documento: null,
    n_documento: "",
    nombre_contacto: "",
    direccion: "",
    telefono: "",
    email: "",
    activo: true,
});

const tiposDocumento = [
    {
        label: "RUC",
        value: "ruc",
    },
    {
        label: "DNI",
        value: "dni",
    },
];

const cantidadDigitosDoc = computed(() => {
    if (form.tipo_documento === "dni") {
        return 8;
    }
    if (form.tipo_documento === "ruc") {
        return 11;
    }

    return 0;
});

const nombreTipoDocumento = computed(() => {
    if (form.tipo_documento === "dni") {
        return "DNI";
    }
    if (form.tipo_documento === "ruc") {
        return "RUC";
    }

    return "documento";
});

const esEdicion = computed(() => {
    return props.proveedor !== null;
});

const titulo = computed(() => {
    return esEdicion.value ? "Editar proveedor" : "Nuevo proveedor";
});

const subtitulo = computed(() => {
    return esEdicion.value
        ? "Actualiza la información del proveedor"
        : "Registra un nuevo proveedor para tu inventario";
});

watch(
    () => props.proveedor,
    (proveedor) => {
        if (proveedor) {
            form.razon_social = proveedor.razon_social ?? "";
            form.nombre_comercial = proveedor.nombre_comercial ?? "";
            form.tipo_documento = proveedor.tipo_documento ?? null;
            form.n_documento = proveedor.n_documento ?? "";
            form.nombre_contacto = proveedor.nombre_contacto ?? "";
            form.direccion = proveedor.direccion ?? "";
            form.telefono = proveedor.telefono ?? "";
            form.email = proveedor.email ?? "";
            form.activo = Boolean(proveedor.activo);
        } else {
            form.reset();
            form.activo = true;
        }
        form.clearErrors();
    },
    { immediate: true },
);

//Guardar

const guardar = async () => {
    console.log("PROVEEDOR:", props.proveedor);
    console.log("ID:", props.proveedor?.id);
    if (esEdicion.value) {
        form.put(route("proveedores.update", props.proveedor.id), {
            onSuccess: async () => {
                emit("saved");

                await Swal.fire({
                    title: "¡Actualizado!",
                    text: "El proveedor fue actualizado correctamente.",
                    icon: "success",
                    confirmButtonText: "Aceptar",
                });
            },
        });
    } else {
        form.post(route("proveedores.store"), {
            onSuccess: async () => {
                emit("saved");

                await Swal.fire({
                    title: "¡Guardado!",
                    text: "El proveedor fue registrado correctamente.",
                    icon: "success",
                    confirmButtonText: "Aceptar",
                });
            },
        });
    }
};

//Cerrar

const cerrar = () => {
    visible.value = false;
    emit("cancel");
};

watch(
    () => form.tipo_documento,
    () => {
        if (cantidadDigitosDoc.value > 0) {
            form.n_documento = form.n_documento
                .replace(/\D/g, "")
                .slice(0, cantidadDigitosDoc.value);
        }
    },
);
</script>

<template>
    <Dialog
        v-model:visible="visible"
        modal
        :header="titulo"
        :draggable="false"
        :style="{ width: '500px' }"
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

        <div class="grid grid-cols-2 gap-x-5 gap-y-5 mt-3">
            <div class="flex flex-col gap-1 col-span-2">
                <FloatLabel variant="on">
                    <InputText
                        id="razon_social"
                        v-model="form.razon_social"
                        class="w-full"
                        :invalid="!!form.errors.razon_social"
                    />
                    <label for="razon_social"
                        >Razón social <span class="text-red-500">*</span>
                    </label>
                </FloatLabel>

                <small v-if="form.errors.razon_social" class="text-red-500">
                    {{ form.errors.razon_social }}
                </small>
            </div>
            <div class="flex flex-col gap-1">
                <FloatLabel variant="on">
                    <InputText
                        id="nombre_comercial"
                        v-model="form.nombre_comercial"
                        class="w-full"
                        :invalid="!!form.errors.nombre_comercial"
                    />
                    <label for="nombre_comercial">
                        Nombre comercial <span class="text-red-500">*</span>
                    </label>
                </FloatLabel>
                <small v-if="form.errors.nombre_comercial" class="text-red-500">
                    {{ form.errors.nombre_comercial }}
                </small>
            </div>
            <div class="flex flex-col gap-1">
                <FloatLabel variant="on">
                    <Select
                        id="tipo_documento"
                        v-model="form.tipo_documento"
                        :options="tiposDocumento"
                        optionLabel="label"
                        optionValue="value"
                        class="w-full"
                        :invalid="!!form.errors.tipo_documento"
                    />
                    <label for="tipo_documento">
                        Tipo de documento
                        <span class="text-red-500">*</span>
                    </label>
                </FloatLabel>

                <small v-if="form.errors.tipo_documento" class="text-red-500">
                    {{ form.errors.tipo_documento }}
                </small>
            </div>
            <div class="flex flex-col gap-1">
                <FloatLabel variant="on">
                    <InputText
                        id="n_documento"
                        v-model="form.n_documento"
                        :maxlength="cantidadDigitosDoc"
                        class="w-full"
                        :invalid="!!form.errors.n_documento"
                        inputmode="numeric"
                        @input="
                            form.n_documento = form.n_documento
                                .replace(/\D/g, '')
                                .slice(0, cantidadDigitosDoc)
                        "
                    />

                    <label for="n_documento">
                        N.º de documento
                        <span class="text-red-500">*</span>
                    </label>
                </FloatLabel>
                <small
                    v-if="
                        form.n_documento &&
                        cantidadDigitosDoc > 0 &&
                        form.n_documento.length !== cantidadDigitosDoc &&
                        !form.errors.n_documento
                    "
                    class="text-red-500 text-xs"
                >
                    El {{ nombreTipoDocumento }} debe tener
                    {{ cantidadDigitosDoc }} dígitos
                </small>
                <small v-if="form.errors.n_documento" class="text-red-500">
                    {{ form.errors.n_documento }}
                </small>
            </div>

            <div class="flex flex-col gap-1">
                <FloatLabel variant="on">
                    <InputText
                        id="nombre_contacto"
                        v-model="form.nombre_contacto"
                        class="w-full"
                        :invalid="!!form.errors.nombre_contacto"
                    />

                    <label for="nombre_contacto">
                        Nombre de contacto
                        <span class="text-red-500">*</span></label
                    >
                </FloatLabel>

                <small v-if="form.errors.nombre_contacto" class="text-red-500">
                    {{ form.errors.nombre_contacto }}
                </small>
            </div>
            <div class="flex flex-col gap-1 col-span-2">
                <FloatLabel variant="on">
                    <Textarea
                        id="direccion"
                        v-model="form.direccion"
                        class="w-full"
                        rows="3"
                        :invalid="!!form.errors.direccion"
                    />

                    <label for="direccion"> Dirección </label>
                </FloatLabel>
                <small v-if="form.errors.direccion" class="text-red-500">
                    {{ form.errors.direccion }}
                </small>
            </div>
            <div class="flex flex-col gap-1">
                <FloatLabel variant="on">
                    <InputText
                        id="telefono"
                        v-model="form.telefono"
                        class="w-full"
                        :invalid="!!form.errors.telefono"
                    />

                    <label for="telefono"> Teléfono </label>
                </FloatLabel>
                <small v-if="form.errors.telefono" class="text-red-500">
                    {{ form.errors.telefono }}
                </small>
            </div>

            <div class="flex flex-col gap-1">
                <FloatLabel variant="on">
                    <InputText
                        id="email"
                        v-model="form.email"
                        class="w-full"
                        :invalid="!!form.errors.email"
                    />

                    <label for="email"> Correo electrónico </label>
                </FloatLabel>
                <small v-if="form.errors.email" class="text-red-500">
                    {{ form.errors.email }}
                </small>
            </div>

            <div class="flex items-center justify-between col-span-2 pt-2">
                <div>
                    <label for="activo" class="font-medium block">
                        Estado del proveedor
                    </label>
                    <small class="text-gray-500">
                        {{
                            form.activo
                                ? "El proveedor está activo"
                                : "El proveedor está inactivo"
                        }}
                    </small>
                </div>
                <ToggleSwitch v-model="form.activo" inputId="activo" />
            </div>
        </div>

        <template #footer>
            <Button
                label="Cancelar"
                severity="secondary"
                variant="outlined"
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

<style scoped>
/* =========================================================
   HEADER
========================================================= */

.dialog-header {
    display: flex;
    align-items: center;
    gap: 0.9rem;
}

.header-icon {
    width: 44px;
    height: 44px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 10px;

    background: var(--primary-50);
    color: var(--primary-color);

    font-size: 1.2rem;
}

.header-content {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
}

.dialog-title {
    margin: 0;

    font-size: 1.15rem;
    font-weight: 700;

    color: var(--text-color);
}

.dialog-subtitle {
    font-size: 0.8rem;
    color: var(--text-color-secondary);
}

/* =========================================================
   CONTENEDOR
========================================================= */

.form-container {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

/* =========================================================
   SECCIONES
========================================================= */

.form-section {
    padding: 1rem;

    border: 1px solid var(--surface-border);
    border-radius: 10px;

    background: var(--surface-card);
}

.section-header {
    display: flex;
    align-items: center;
    gap: 0.7rem;
}

.section-icon {
    width: 36px;
    height: 36px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 8px;

    background: var(--primary-50);
    color: var(--primary-color);
}

.section-header h3 {
    margin: 0;

    font-size: 0.9rem;
    font-weight: 700;

    color: var(--text-color);
}

.section-header p {
    margin: 0.15rem 0 0;

    font-size: 0.72rem;

    color: var(--text-color-secondary);
}

/* =========================================================
   GRID DEL FORMULARIO
========================================================= */

.form-grid {
    display: grid;

    grid-template-columns: repeat(2, minmax(0, 1fr));

    gap: 1.35rem 1rem;
}

.form-field {
    display: flex;
    flex-direction: column;

    min-width: 0;
}

.required {
    color: var(--red-500);
    margin-left: 2px;
}

/* =========================================================
   ERRORES
========================================================= */

.error-text {
    margin-top: 0.35rem;

    font-size: 0.72rem;

    color: var(--red-500);
}

/* =========================================================
   ESTADO
========================================================= */

.status-section {
    display: flex;
    align-items: center;

    gap: 0.8rem;

    padding: 0.9rem 1rem;

    border-radius: 10px;

    border: 1px solid var(--surface-border);

    background: var(--surface-50);
}

.status-icon {
    width: 38px;
    height: 38px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 50%;

    background: var(--primary-50);
    color: var(--primary-color);

    flex-shrink: 0;
}

.status-content {
    flex: 1;
    min-width: 0;
}

.status-title {
    font-size: 0.85rem;
    font-weight: 700;

    color: var(--text-color);
}

.status-description {
    margin-top: 0.2rem;

    font-size: 0.72rem;
    line-height: 1.4;

    color: var(--text-color-secondary);
}

.status-control {
    display: flex;
    align-items: center;
    gap: 0.55rem;

    flex-shrink: 0;
}

.status-label {
    min-width: 55px;

    font-size: 0.78rem;
    font-weight: 700;
}

.status-label.active {
    color: var(--green-600);
}

.status-label.inactive {
    color: var(--red-500);
}

/* =========================================================
   FOOTER
========================================================= */

.dialog-footer {
    display: flex;
    justify-content: flex-end;

    gap: 0.6rem;

    width: 100%;
}

/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 700px) {
    .form-grid {
        grid-template-columns: 1fr;
    }

    .status-section {
        align-items: flex-start;
        flex-wrap: wrap;
    }

    .status-content {
        width: calc(100% - 55px);
    }

    .status-control {
        width: 100%;
        justify-content: flex-end;
    }

    .dialog-footer {
        flex-direction: column-reverse;
    }

    .dialog-footer .p-button {
        width: 100%;
    }
}
</style>
