<script setup>
import { ref, watch, computed } from "vue";
import { useForm } from "@inertiajs/vue3";
import Swal from "sweetalert2";

const props = defineProps({
    caja: {
        type: Object,
        default: null,
    },
});

const visible = defineModel("visible");

const emit = defineEmits(["update:visible", "saved", "cancel"]);

const form = useForm({
    nombre: "",
    turno: null,
    activo: true,
});

const errores = ref({});
const processing = ref(false);

const turnos = [
    { label: "Mañana", value: "mañana" },
    { label: "Tarde", value: "tarde" },
    { label: "Noche", value: "noche" },
];

const esEdicion = computed(() => props.caja !== null);

const titulo = computed(() => (esEdicion.value ? "Editar caja" : "Nueva caja"));

watch(
    () => props.caja,
    (caja) => {
        if (caja) {
            form.nombre = caja.nombre ?? "";
            form.turno = caja.turno ?? null;
            form.activo = Boolean(caja.activo);
        } else {
            form.reset();
            form.nombre = "";
            form.turno = null;
            form.activo = true;
        }

        form.clearErrors();
    },
    { immediate: true },
);

const guardar = async () => {
    form.clearErrors();
    if (esEdicion.value) {
        form.put(route("cajas.update", props.caja.id), {
            preserveScroll: true,
            onSuccess: () => {
                visible.value = false;

                emit("saved");

                Swal.fire({
                    title: "¡Actualizado!",
                    text: "La caja fue actualizada correctamente.",
                    icon: "success",
                    confirmButtonText: "Aceptar",
                });
            },
            onError: (errors) => {
                console.error("Errores al actualizar caja:", errors);
            },
        });
    } else {
        form.post(route("cajas.store"), {
            preserveScroll: true,
            onSuccess: () => {
                visible.value = false;

                emit("saved");

                Swal.fire({
                    title: "¡Guardado!",
                    text: "La caja fue registrada correctamente.",
                    icon: "success",
                    confirmButtonText: "Aceptar",
                });
            },
            onError: (errors) => {
                console.error("Errores al registrar caja:", errors);
            },
        });
    }
};

const cerrar = () => {
    if (form.processing) return;

    visible.value = false;

    form.clearErrors();

    emit("cancel");
};
</script>

<template>
    <Dialog
        v-model:visible="visible"
        :header="titulo"
        modal
        :style="{ width: '500px' }"
        :closable="!processing"
        :closeOnEscape="!form.processing"
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
                    <InputText
                        id="nombre"
                        v-model="form.nombre"
                        class="w-full"
                        :invalid="!!errores.nombre"
                        :disabled="form.processing"
                        autofocus
                    />
                    <label for="nombre">
                        Nombre de la caja<span class="text-red-500">*</span>
                    </label>
                </FloatLabel>

                <small v-if="form.errors.nombre" class="text-red-500">
                    {{ form.errors.nombre }}
                </small>
            </div>

            <div class="flex flex-col gap-1">
                <FloatLabel variant="on">
                    <Select
                        id="turno"
                        v-model="form.turno"
                        :options="turnos"
                        optionLabel="label"
                        optionValue="value"
                        class="w-full"
                        :invalid="!!errores.turno"
                        :disabled="form.processing"
                    />
                    <label for="turno">
                        Turno<span class="text-red-500">*</span>
                    </label>
                </FloatLabel>

                <small v-if="form.errors.turno" class="text-red-500">
                    {{ form.errors.turno }}
                </small>
            </div>

            <div class="flex items-center justify-between">
                <div>
                    <label for="activo" class="font-medium block">
                        Estado de la caja
                    </label>

                    <small class="text-gray-500">
                        {{
                            form.activo
                                ? "La caja está activa"
                                : "La caja está inactiva"
                        }}
                    </small>
                </div>

                <ToggleSwitch
                    v-model="form.activo"
                    inputId="activo"
                    :disabled="form.processing"
                />
            </div>
        </div>

        <template #footer>
            <Button
                label="Cancelar"
                severity="secondary"
                text
                :disabled="processing"
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
