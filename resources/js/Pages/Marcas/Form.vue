<script setup>
import { computed, watch } from "vue";
import { useForm } from "@inertiajs/vue3";

import Swal from "sweetalert2";

const props = defineProps({
    marca: {
        type: [Object, null],
        default: null,
    },
    empresas: {
        type: Array,
        default: () => [],
    },
});

const visible = defineModel("visible");

const emit = defineEmits(["update:visible", "saved", "cancel"]);

const form = useForm({
    nombre: "",
    empresa_id: null,
    activo: true,
});

const esEdicion = computed(() => {
    return props.marca != null;
});

const titulo = computed(() => {
    return esEdicion.value ? "Editar marca" : "Nueva marca";
});

const subtitulo = computed(() => {
    return esEdicion.value
        ? "Actualiza la información de la marca"
        : "Registra una nueva marca para tus productos";
});

//Limpiar form

const limpiarFormulario = () => {
    form.reset();
    form.clearErrors();

    form.nombre = "";
    form.empresa_id = null;
    form.activo = true;
};

//Cargar datos de edición
const cargarMarca = (marca) => {
    form.clearErrors();

    form.nombre = marca.nombre ?? "";
    form.empresa_id = marca.empresa_id ?? null;
    form.activo = Boolean(marca.activo);
};

//Cuando cambia la marca

watch(
    () => props.marca,
    (marca) => {
        if (marca) {
            cargarMarca(marca);
        } else {
            limpiarFormulario();
        }
    },
    {
        immediate: true,
    },
);

//Cuando se abre el dialog
watch(visible, (nuevoValor) => {
    if (!nuevoValor) {
        return;
    }
    if (props.marca) {
        cargarMarca(props.marca);
    } else {
        limpiarFormulario();
    }
});

//Guardar

const guardar = async () => {
    if (esEdicion.value) {
        form.put(route("marcas.update", props.marca.id), {
            onSuccess: async () => {
                limpiarFormulario();

                visible.value = false;

                emit("saved");

                await Swal.fire({
                    title: "¡Actualizado!",
                    text: "La marca fue actualizada correctamente.",
                    icon: "success",
                    confirmButtonText: "Aceptar",
                });
            },
        });
    } else {
        form.post(route("marcas.store"), {
            onSuccess: async () => {
                limpiarFormulario();

                visible.value = false;

                emit("saved");

                await Swal.fire({
                    title: "¡Guardado!",
                    text: "La marca fue registrada correctamente.",
                    icon: "success",
                    confirmButtonText: "Aceptar",
                });
            },
        });
    }
};
// Cerrar formulario
const cerrar = () => {
    limpiarFormulario();

    visible.value = false;
    emit("cancel");
};
</script>

<template>
    <Dialog v-model:visible="visible" modal :style="{ width: '500px' }">
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
            <!-- NOMBRE -->
            <div class="flex flex-col gap-1">
                <FloatLabel variant="on">
                    <InputText
                        id="nombre"
                        v-model="form.nombre"
                        class="w-full"
                        :invalid="!!form.errors.nombre"
                        autofocus
                    />

                    <label for="nombre"
                        >Nombre <span class="text-red-500">*</span></label
                    >
                </FloatLabel>

                <small v-if="form.errors.nombre" class="text-red-500">
                    {{ form.errors.nombre }}
                </small>
            </div>
            <!-- EMPRESA -->
            <div class="flex flex-col gap-1">
                <FloatLabel variant="on">
                    <Select
                        id="empresa_id"
                        v-model="form.empresa_id"
                        :options="empresas"
                        optionLabel="razon_social"
                        optionValue="id"
                        class="w-full"
                        :invalid="!!form.errors.empresa_id"
                        filter
                        placeholder="Busca y selecciona una empresa"
                        emptyMessage="No existen empresas disponibles"
                    />

                    <label for="empresa_id">
                        Empresa <span class="text-red-500">*</span>
                    </label>
                </FloatLabel>

                <small v-if="form.errors.empresa_id" class="text-red-500">
                    {{ form.errors.empresa_id }}
                </small>
            </div>
            <!-- Estado -->
            <div class="flex items-center justify-between">
                <div>
                    <label for="activo" class="font-medium block">
                        Estado de la marca
                    </label>

                    <small class="text-gray-500">
                        {{
                            form.activo
                                ? "La marca está activa"
                                : "La marca está inactiva"
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
                :loading="processing"
                @click="guardar"
            />
        </template>
    </Dialog>
</template>
