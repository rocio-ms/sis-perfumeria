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
    ruc: "",
    razon_social: "",
    nombre_comercial: "",
    direccion: "",
    departamento: "",
    provincia: "",
    distrito: "",
    telefono: "",
    email: "",
    logo: null,
});

const esEdicion = computed(() => {
    return props.tienda !== null;
});

watch(
    () => props.tienda,
    (tienda) => {
        if (tienda) {
            form.ruc = tienda.ruc ?? "";
            form.razon_social = tienda.razon_social ?? "";
            form.nombre_comercial = tienda.nombre_comercial ?? "";
            form.direccion = tienda.direccion ?? "";
            form.departamento = tienda.departamento ?? "";
            form.provincia = tienda.provincia ?? "";
            form.distrito = tienda.distrito ?? "";
            form.telefono = tienda.telefono ?? "";
            form.email = tienda.email ?? "";
            form.logo = null;
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
                    <!-- DATOS FISCALES -->
                    <div>
                        <h2 class="text-lg font-semibold text-gray-800">
                            Datos fiscales
                        </h2>

                        <p class="text-sm text-gray-500 mt-1">
                            Información que aparecerá en tus boletas y facturas.
                        </p>
                    </div>

                    <Divider />

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <!-- RUC -->
                        <div class="flex flex-col gap-1">
                            <FloatLabel variant="on">
                                <InputText
                                    id="ruc"
                                    v-model="form.ruc"
                                    class="w-full"
                                    maxlength="11"
                                    inputmode="numeric"
                                    :invalid="!!form.errors.ruc"
                                    @input="
                                        form.ruc = form.ruc
                                            .replace(/\D/g, '')
                                            .slice(0, 11)
                                    "
                                />

                                <label for="ruc">
                                    RUC <span class="text-red-500">*</span>
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
                            <small v-if="form.errors.ruc" class="text-red-500">
                                {{ form.errors.ruc }}
                            </small>
                        </div>

                        <!-- Razón social -->
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
                                    <span class="text-red-500">*</span>
                                </label>
                            </FloatLabel>
                            <small
                                v-if="form.errors.razon_social"
                                class="text-red-500"
                            >
                                {{ form.errors.razon_social }}
                            </small>
                        </div>

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

                    <div class="flex flex-col gap-3">
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
