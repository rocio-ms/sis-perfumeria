<script setup>
import { computed } from "vue";

const props = defineProps({
    cliente: { type: Object, required: true },
});

const nombre = computed(() =>
    props.cliente.nombre_completo?.trim() ||
    props.cliente.razon_social_nombre?.trim() ||
    "Cliente",
);
const iniciales = computed(() => nombre.value
    .split(/\s+/)
    .slice(0, 2)
    .map((palabra) => palabra[0])
    .join("")
    .toLocaleUpperCase("es-PE"),
);
</script>

<template>
    <div class="grid gap-4">
        <div class="flex flex-wrap items-center gap-3 rounded-xl border border-rose-100 bg-rose-50 p-4">
            <Avatar
                :label="iniciales"
                shape="circle"
                size="large"
                class="!bg-rose-100 !text-rose-900"
                aria-hidden="true"
            />
            <div class="min-w-0 flex-1">
                <h2 class="break-words text-lg font-semibold leading-snug text-slate-900">
                    {{ nombre }}
                </h2>
                <p class="mt-1 flex items-center gap-2 text-sm text-rose-900">
                    <i class="pi pi-id-card" aria-hidden="true"></i>
                    {{ cliente.tipo_documento }} {{ cliente.numero_documento }}
                </p>
            </div>
            <Tag :value="cliente.activo ? 'Activo' : 'Inactivo'" :severity="cliente.activo ? 'success' : 'secondary'" />
        </div>

        <dl class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div class="rounded-xl border border-slate-200 p-3">
                <dt class="flex items-center gap-2 text-sm font-medium text-slate-600">
                    <i class="pi pi-phone text-rose-800" aria-hidden="true"></i>
                    Celular
                </dt>
                <dd class="mt-1 break-words text-sm text-slate-900">{{ cliente.telefono?.trim() || "Sin celular" }}</dd>
            </div>
            <div class="rounded-xl border border-slate-200 p-3">
                <dt class="flex items-center gap-2 text-sm font-medium text-slate-600">
                    <i class="pi pi-envelope text-rose-800" aria-hidden="true"></i>
                    Correo electrónico
                </dt>
                <dd class="mt-1 break-words text-sm text-slate-900">{{ cliente.email?.trim() || "Sin correo" }}</dd>
            </div>
            <div class="rounded-xl border border-slate-200 p-3 sm:col-span-2">
                <dt class="flex items-center gap-2 text-sm font-medium text-slate-600">
                    <i class="pi pi-map-marker text-rose-800" aria-hidden="true"></i>
                    Dirección
                </dt>
                <dd class="mt-1 break-words text-sm text-slate-900">{{ cliente.direccion?.trim() || "Sin dirección" }}</dd>
            </div>
        </dl>
    </div>
</template>
