<script setup>
import { ref, computed, watch } from "vue";
import { useForm } from "@inertiajs/vue3";

import AppLayout from "@/Layouts/AppLayout.vue";
import Card from "@/Components/Card.vue";

import Swal from "sweetalert2";

defineOptions({
    layout: AppLayout,
});

const props = defineProps({
    tienda: {
        type: Object,
        default: null,
    },
});

const home = ref({
    icon: "pi pi-home",
});

const items = ref([
    {
        label: "Configuración",
    },
    {
        label: "Tienda",
    },
]);

const form = useForm({
    nombre_comercial: "",
    direccion: "",
    departamento: "",
    provincia: "",
    distrito: "",
    telefono: "",
    email: "",
    logo: null,
});

const logoPreview = ref(null);

const esEdicion = computed(() => {
    return props.tienda !== null;
});

watch(
    () => props.tienda,
    (tienda) => {

        console.log("TIENDA RECIBIDA:", tienda);
        if (tienda) {
            form.nombre_comercial = tienda.nombre_comercial ?? "";
            form.direccion = tienda.direccion ?? "";
            form.departamento = tienda.departamento ?? "";
            form.provincia = tienda.provincia ?? "";
            form.distrito = tienda.distrito ?? "";
            form.telefono = tienda.telefono ?? "";
            form.email = tienda.email ?? "";
            form.logo = null;

            if (tienda.logo_url) {
                logoPreview.value = `/storage/${tienda.logo_url}`;
            } else {
                logoPreview.value = null;
            }
        } else {
            logoPreview.value = null;
        }
    },
    {
        immediate: true,
    },
);

const seleccionarLogo = (event) => {
    const file = event.files?.[0];

    if (file) {
        form.logo = file;

        logoPreview.value = URL.createObjectURL(file);
    }
};

const guardar = () => {
    if (esEdicion.value) {
        form.transform((data) => ({
            ...data,
            _method: "PUT",
        })).post(route("tiendas.update", props.tienda.id), {
            forceFormData: true,

            onSuccess: () => {
                Swal.fire({
                    title: "¡Actualizado!",
                    text: "La información de la tienda fue actualizada correctamente.",
                    icon: "success",
                    confirmButtonText: "Aceptar",
                });
            },

            onError: (errors) => {
                console.log("Errores de validación:", errors);
            },
        });
    } else {
        form.post(route("tiendas.store"), {
            forceFormData: true,

            onSuccess: () => {
                Swal.fire({
                    title: "¡Guardado!",
                    text: "La información de la tienda fue guardada correctamente.",
                    icon: "success",
                    confirmButtonText: "Aceptar",
                });
            },

            onError: (errors) => {
                console.log("Errores de validación:", errors);
            },
        });
    }
};
</script>

<template>
    <v-container fluid>
        <Card>
            <template #title>
                <div class="flex flex-col gap-3">
                    <div class="flex justify-content-center">
                        <Breadcrumb :home="home" :model="items" />
                    </div>

                    <div
                        class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4"
                    >
                        <div>
                            <h1 class="text-2xl font-bold text-gray-800">
                                Configuración de la tienda
                            </h1>

                            <p class="text-sm text-gray-500">
                                {{
                                    props.tienda
                                        ? "Actualiza los datos de tu tienda y la información utilizada en tus comprobantes."
                                        : "Registra los datos de tu tienda que serán utilizados en tus comprobantes."
                                }}
                            </p>
                        </div>
                    </div>
                </div>
            </template>

            <template #content>
                <div class="flex flex-col gap-6">
                    <!-- DATOS DE LA TIENDA -->
                    <div>
                        <h2 class="text-lg font-semibold text-gray-800">
                            Datos de la tienda
                        </h2>

                        <p class="text-sm text-gray-500 mt-1">
                            Información que aparecerá en tus boletas y facturas.
                        </p>
                    </div>

                    <Divider />

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <!-- Nombre comercial -->
                        <div class="flex flex-col gap-1">
                            <FloatLabel variant="on">
                                <InputText
                                    id="nombre_comercial"
                                    v-model="form.nombre_comercial"
                                    class="w-full"
                                    :invalid="!!form.errors.nombre_comercial"
                                />

                                <label for="nombre_comercial">
                                    Nombre comercial
                                </label>
                            </FloatLabel>
                            <small
                                v-if="form.errors.nombre_comercial"
                                class="text-red-500"
                            >
                                {{ form.errors.nombre_comercial }}
                            </small>
                        </div>

                        <!-- Teléfono -->
                        <div class="flex flex-col gap-1">
                            <FloatLabel variant="on">
                                <InputText
                                    id="telefono"
                                    v-model="form.telefono"
                                    class="w-full"
                                />

                                <label for="telefono"> Teléfono </label>
                            </FloatLabel>
                        </div>

                        <!-- Correo -->
                        <div class="flex flex-col gap-1 md:col-span-2">
                            <FloatLabel variant="on">
                                <InputText
                                    id="email"
                                    v-model="form.email"
                                    class="w-full"
                                />

                                <label for="email"> Correo electrónico </label>
                            </FloatLabel>
                        </div>

                        <!-- Dirección -->
                        <div class="flex flex-col gap-1 md:col-span-2">
                            <FloatLabel variant="on">
                                <Textarea
                                    id="direccion"
                                    v-model="form.direccion"
                                    class="w-full"
                                    rows="3"
                                    autoResize
                                    :invalid="!!form.errors.direccion"
                                />

                                <label for="direccion">
                                    Dirección
                                    <span class="text-red-500">*</span>
                                </label>
                            </FloatLabel>
                            <small
                                v-if="form.errors.direccion"
                                class="text-red-500"
                            >
                                {{ form.errors.direccion }}
                            </small>
                        </div>
                    </div>

                    <!-- UBICACIÓN -->
                    <div class="mt-3">
                        <h2 class="text-lg font-semibold text-gray-800">
                            Ubicación
                        </h2>

                        <p class="text-sm text-gray-500 mt-1">
                            Ubicación física de la tienda.
                        </p>
                    </div>

                    <Divider />

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                        <!-- Departamento -->
                        <FloatLabel variant="on">
                            <InputText
                                id="departamento"
                                v-model="form.departamento"
                                class="w-full"
                            />

                            <label for="departamento"> Departamento </label>
                        </FloatLabel>

                        <!-- Provincia -->
                        <FloatLabel variant="on">
                            <InputText
                                id="provincia"
                                v-model="form.provincia"
                                class="w-full"
                            />

                            <label for="provincia"> Provincia </label>
                        </FloatLabel>

                        <!-- Distrito -->
                        <FloatLabel variant="on">
                            <InputText
                                id="distrito"
                                v-model="form.distrito"
                                class="w-full"
                            />

                            <label for="distrito"> Distrito </label>
                        </FloatLabel>
                    </div>

                    <!-- LOGO -->
                    <div class="mt-3">
                        <h2 class="text-lg font-semibold text-gray-800">
                            Logo de la tienda
                        </h2>

                        <p class="text-sm text-gray-500 mt-1">
                            El logo podrá mostrarse en tus comprobantes.
                        </p>
                    </div>

                    <Divider />

                    <div
                        class="grid grid-cols-1 md:grid-cols-2 gap-6 items-center"
                    >
                        <!-- PREVISUALIZACIÓN -->
                        <div class="flex flex-col gap-2">
                            <span class="text-sm font-medium text-gray-700">
                                Vista previa
                            </span>

                            <div
                                class="w-full h-48 flex items-center justify-center border border-gray-200 rounded-xl bg-gray-50 overflow-hidden"
                            >
                                <!-- Hay logo -->
                                <img
                                    v-if="logoPreview"
                                    :src="logoPreview"
                                    alt="Logo de la tienda"
                                    class="max-w-full max-h-full object-contain p-4"
                                />

                                <!-- No hay logo -->
                                <div
                                    v-else
                                    class="flex flex-col items-center justify-center gap-2 text-gray-400"
                                >
                                    <i class="pi pi-image text-4xl"></i>

                                    <span class="text-sm"> Sin logo </span>
                                </div>
                            </div>
                        </div>

                        <!-- SELECCIONAR ARCHIVO -->
                        <div class="flex flex-col gap-3">
                            <span class="text-sm font-medium text-gray-700">
                                Seleccionar logo
                            </span>

                            <FileUpload
                                mode="basic"
                                name="logo"
                                accept="image/*"
                                :maxFileSize="2000000"
                                @select="seleccionarLogo"
                                chooseLabel="Seleccionar archivo"
                                chooseIcon="pi pi-image"
                            />

                            <small class="text-gray-500">
                                Formatos permitidos: JPG, PNG. Máximo 2 MB.
                            </small>
                        </div>
                    </div>

                    <!-- BOTÓN -->
                    <div class="flex justify-end mt-4">
                        <Button
                            :label="
                                esEdicion
                                    ? 'Actualizar información'
                                    : 'Guardar información'
                            "
                            icon="pi pi-save"
                            severity="success"
                            :loading="form.processing"
                            @click="guardar"
                        />
                    </div>
                </div>
            </template>
        </Card>
    </v-container>
</template>
