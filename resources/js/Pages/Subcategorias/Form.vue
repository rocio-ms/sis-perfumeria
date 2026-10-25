<script setup>
import { computed, watch } from "vue";
import { useForm } from "@inertiajs/vue3";
import Swal from "sweetalert2";

const props = defineProps({
    subcategoria: {
        type: [String, Object],
        default: null,
    },
    categorias: {
        type: Array,
        default: () => [],
    },
});

const visible = defineModel("visible");
const emit = defineEmits(["update:visible", "saved", "cancel"]);

const form = useForm({
    categoria_id: null,
    nombre: "",
    descripcion: "",
    activo: true,
});

const esEdicion = computed(() => {
    return props.subcategoria !== null;
});

const titulo = computed(() => {
    return esEdicion.value ? "Editar subcategoría" : "Nueva subcategoría";
});

const subtitulo = computed(() => {
    return esEdicion.value
        ? "Actualiza la información de la subcategoría"
        : "Registra una nueva subcategoría para organizar tus productos";
});

const descripcionLength = computed(() => form.descripcion?.length ?? 0);

const limpiarFormulario = () => {
    form.reset();
    form.clearErrors();
    form.categoria_id = null;
    form.nombre = "";
    form.descripcion = "";
    form.activo = true;
};

const cargarSubcategoria = (subcategoria) => {
    form.clearErrors();
    form.categoria_id = subcategoria.categoria_id ?? subcategoria.categoria?.id ?? null;
    form.nombre = subcategoria.nombre ?? "";
    form.descripcion = subcategoria.descripcion ?? "";
    form.activo = Boolean(subcategoria.activo);
};

watch(
    () => props.subcategoria,
    (subcategoria) => {
        if (subcategoria) {
            cargarSubcategoria(subcategoria);
        } else {
            limpiarFormulario();
        }
    },
    { immediate: true },
);

watch(visible, (nuevoValor) => {
    if (!nuevoValor) {
        return;
    }

    if (props.subcategoria) {
        cargarSubcategoria(props.subcategoria);
    } else {
        limpiarFormulario();
    }
});

const guardar = async () => {
    if (esEdicion.value) {
        form.put(route("subcategorias.update", props.subcategoria.id), {
            onSuccess: async () => {
                limpiarFormulario();
                visible.value = false;
                emit("saved");

                await Swal.fire({
                    title: "¡Actualizado!",
                    text: "La subcategoría fue actualizada correctamente.",
                    icon: "success",
                    confirmButtonText: "Aceptar",
                });
            },
        });
    } else {
        form.post(route("subcategorias.store"), {
            onSuccess: async () => {
                limpiarFormulario();
                visible.value = false;
                emit("saved");

                await Swal.fire({
                    title: "¡Guardado!",
                    text: "La subcategoría fue registrada correctamente.",
                    icon: "success",
                    confirmButtonText: "Aceptar",
                });
            },
        });
    }
};

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
        :style="{ width: '550px' }"
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
            <div class="flex flex-col gap-1">
                <FloatLabel variant="on">
                    <Select
                        id="categoria_id"
                        v-model="form.categoria_id"
                        :options="categorias"
                        optionLabel="nombre"
                        optionValue="id"
                        class="w-full"
                        :invalid="!!form.errors.categoria_id"
                    />

                    <label for="categoria_id">
                        Categoría <span class="text-red-500">*</span>
                    </label>
                </FloatLabel>

                <small v-if="form.errors.categoria_id" class="text-red-500">
                    {{ form.errors.categoria_id }}
                </small>
            </div>

            <div class="flex flex-col gap-1">
                <FloatLabel variant="on">
                    <InputText
                        id="nombre"
                        v-model="form.nombre"
                        class="w-full"
                        :invalid="!!form.errors.nombre"
                        autofocus
                    />

                    <label for="nombre">
                        Nombre <span class="text-red-500">*</span>
                    </label>
                </FloatLabel>

                <small v-if="form.errors.nombre" class="text-red-500">
                    {{ form.errors.nombre }}
                </small>
            </div>

            <div class="flex flex-col gap-1">
                <FloatLabel variant="on">
                    <Textarea
                        id="descripcion"
                        v-model="form.descripcion"
                        class="w-full"
                        rows="4"
                    />

                    <label for="descripcion">Descripción</label>
                </FloatLabel>

                <div class="flex justify-end">
                    <small class="text-gray-400">
                        {{ descripcionLength }} / 255
                    </small>
                </div>
            </div>

            <div class="flex items-center justify-between">
                <div>
                    <label for="activo" class="font-medium block">
                        Estado de la subcategoría
                    </label>

                    <small class="text-gray-500">
                        {{
                            form.activo
                                ? "La subcategoría está activa"
                                : "La subcategoría está inactiva"
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
