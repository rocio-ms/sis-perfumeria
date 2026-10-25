<script setup>
import { computed, watch } from "vue";
import { useForm } from "@inertiajs/vue3";
import Swal from "sweetalert2";

const props = defineProps({
    subcategoria: {
        type: Object,
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

const esEdicion = computed(() => props.subcategoria !== null);

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
    form.categoria_id = subcategoria.categoria_id ?? null;
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

watch(visible, (estaVisible) => {
    if (!estaVisible) {
        return;
    }

    if (props.subcategoria) {
        cargarSubcategoria(props.subcategoria);
    } else {
        limpiarFormulario();
    }
});

const guardar = () => {
    const opciones = {
        onSuccess: async () => {
            limpiarFormulario();
            visible.value = false;
            emit("saved");

            await Swal.fire({
                title: esEdicion.value ? "¡Actualizada!" : "¡Guardada!",
                text: esEdicion.value
                    ? "La subcategoría se actualizó correctamente."
                    : "La subcategoría se registró correctamente.",
                icon: "success",
                confirmButtonText: "Aceptar",
            });
        },
    };

    if (esEdicion.value) {
        form.put(route("subcategorias.update", props.subcategoria.id), opciones);
        return;
    }

    form.post(route("subcategorias.store"), opciones);
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
        :header="esEdicion ? 'Editar subcategoría' : 'Nueva subcategoría'"
        :style="{ width: '550px' }"
    >
        <div class="flex flex-col gap-5 mt-3">
            <div class="flex flex-col gap-1">
                <FloatLabel variant="on">
                    <Select
                        id="categoria_id"
                        v-model="form.categoria_id"
                        :options="categorias"
                        option-label="nombre"
                        option-value="id"
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
                <small v-if="form.errors.descripcion" class="text-red-500">
                    {{ form.errors.descripcion }}
                </small>
            </div>

            <div class="flex items-center justify-between">
                <div>
                    <label for="activo" class="font-medium block">
                        Estado de la subcategoría
                    </label>
                    <small class="text-gray-500">
                        {{ form.activo ? "Activa" : "Inactiva" }}
                    </small>
                </div>
                <ToggleSwitch v-model="form.activo" input-id="activo" />
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
