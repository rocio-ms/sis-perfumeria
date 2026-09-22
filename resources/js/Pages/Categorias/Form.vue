<script setup>
import { computed, watch } from "vue";
import { useForm } from "@inertiajs/vue3";

import Swal from "sweetalert2";

const props = defineProps({
    categoria: {
        type: [String, Object],
        default: null,
    },
});

const visible = defineModel("visible");

const emit = defineEmits(["update:visible", "saved", "cancel"]);

const form = useForm({
    nombre: "",
    descripcion: "",
    activo: true,
});

const esEdicion = computed(() => {
    return props.categoria !== null;
});

const titulo = computed(() => {
    return esEdicion.value ? "Editar categoría" : "Nueva categoría";
});

const subtitulo = computed(() => {
    return esEdicion.value
        ? "Actualiza la información de la categoría"
        : "Registra una nueva categoría para organizar tus productos";
});

//Limpiar form
const limpiarFormulario = () => {
    form.reset();
    form.clearErrors();

    form.nombre = "";
    form.activo = true;
};

//Cargar datos de edición
const cargarCategoria = (categoria) => {
    form.clearErrors();

    form.nombre = categoria.nombre ?? "";
    form.descripcion = categoria.descripcion ?? "";
    form.activo = Boolean(categoria.activo);
}

//Limite para el tamaño de la descripción
const descripcionLength = computed(() => form.descripcion?.length ?? 0);


//Cuando cambia la categoria
watch(
    () => props.categoria,
    (categoria) => {
        if (categoria) {
            cargarCategoria(categoria);
        } else {
            limpiarFormulario();
        }
    },
    { immediate: true },
);

//Cuando se abre el dialog
watch(visible, (nuevoValor) => {
    if(!nuevoValor){
        return;
    }
    if(props.categoria){
        cargarCategoria(props.categoria);
    } else {
        limpiarFormulario();
    }
})

//Guardar
const guardar = async () => {
    if (esEdicion.value) {
        form.put(route("categorias.update", props.categoria.id), {
            onSuccess: async () => {

                limpiarFormulario();

                visible.value = false;

                emit("saved");

                await Swal.fire({
                    title: "¡Actualizado!",
                    text: "La categoría fue actualizada correctamente.",
                    icon: "success",
                    confirmButtonText: "Aceptar",
                });
            },
        });
    } else {
        form.post(route("categorias.store"), {
            onSuccess: async () => {

                limpiarFormulario();

                visible.value = false;

                emit("saved");

                await Swal.fire({
                    title: "¡Guardado!",
                    text: "La categoría fue registrada correctamente.",
                    icon: "success",
                    confirmButtonText: "Aceptar",
                });
            },
        });
    }
};

//Cerrar formulario
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

                    <label for="nombre">
                        Nombre <span class="text-red-500">*</span></label
                    >
                </FloatLabel>
                <small v-if="form.errors.nombre" class="text-red-500">
                    {{ form.errors.nombre }}
                </small>
            </div>

            <!-- DESCRIPCION -->
            <div class="flex flex-col gap-1">
                <FloatLabel variant="on">
                    <Textarea
                        id="descripcion"
                        v-model="form.descripcion"
                        class="w-full"
                        rows="4"
                    />

                    <label for="descripcion"> Descripción </label>
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
                        Estado de la categoría
                    </label>

                    <small class="text-gray-500">
                        {{
                            form.activo
                                ? "La categoría está activa"
                                : "La categoría está inactiva"
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
