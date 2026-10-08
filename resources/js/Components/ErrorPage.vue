<script setup>
import Button from "primevue/button";
import { computed } from "vue";
import { usePage, router } from "@inertiajs/vue3";

const props = defineProps({
    code: {
        type: [String, Number],
        default: 500,
    },

    title: {
        type: String,
        default: "Ha ocurrido un error",
    },

    message: {
        type: String,
        default: "Ocurrió un problema inesperado",
    },

    icon: {
        type: String,
        default: "pi pi-exclamation-triangle",
    },
});

const page = usePage();

const usuarioAutenticado = computed(() => {
    return !!page.props.auth?.user;
});

const textBoton = computed(() => {
    return usuarioAutenticado.value
        ? "Ir al dashboard"
        : "Ir a la página de inicio";
});

const iconoBoton = computed(() => {
    return usuarioAutenticado.value ? "pi pi-home" : "pi pi-sign-in";
});

const rutaInicio = computed(() => {
    return usuarioAutenticado.value ? route("dashboard") : route("login");
});

const volverInicio = () => {
    router.visit(rutaInicio.value);
};

const volverAtras = () => {
    window.history.back();
};
</script>

<template>
    <div class="min-h-screen flex items-center justify-center bg-slate-50 px-6">
        <div class="text-center max-w-lg">
            <div
                class="w-24 h-24 mx-auto mb-6 rounded-full bg-indigo-100 flex items-center justify-center"
            >
                <i :class="icon" class="text-4xl text-indigo-600"></i>
            </div>

            <h1 class="text-8xl font-bold text-indigo-600">
                {{ code }}
            </h1>

            <h2 class="text-2xl font-semibold text-slate-800 mt-4">
                {{ title }}
            </h2>

            <p class="text-slate-500 mt-3 leading-relaxed">
                {{ message }}
            </p>

            <div class="flex justify-center gap-3 mt-8">
                <Button
                    label="Volver atrás"
                    icon="pi pi-arrow-left"
                    severity="secondary"
                    outlined
                    @click="volverAtras"
                />

                <Button
                    :label="textoBoton"
                    :icon="iconoBoton"
                    @click="volverInicio"
                />
            </div>
        </div>
    </div>
</template>
