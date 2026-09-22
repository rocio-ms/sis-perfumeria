<script setup>
import { computed } from "vue";

const props = defineProps({
    visible: {
        type: Boolean,
        default: false,
    },
    proveedor: {
        type: Object,
        default: null,
    },
});

const emit = defineEmits(["update:visible"]);

const nombre = computed(() => {
    return props.proveedor?.razon_social ?? "Sin razón social";
});

const iniciales = computed(() => {
    if (!props.proveedor?.razon_social) {
        return "P";
    }

    return props.proveedor.razon_social
        .split(" ")
        .filter(Boolean)
        .slice(0, 2)
        .map((nombre) => nombre.charAt(0).toUpperCase())
        .join("");
});

const estadoSeverity = computed(() => {
    return props.proveedor?.activo ? "success" : "danger";
});

const estadoTexto = computed(() => {
    return props.proveedor?.activo ? "Activo" : "Inactivo";
});

const tipoDocumentoTexto = computed(() => {
    if (props.proveedor?.tipo_documento === "ruc") {
        return "RUC";
    }

    if (props.proveedor?.tipo_documento === "dni") {
        return "DNI";
    }

    return props.proveedor?.tipo_documento ?? "No especificado";
});

const cerrar = () => {
    emit("update:visible", false);
};

const fechaCreacion = computed(() => {
    if (!props.proveedor?.created_at) {
        return "No disponible";
    }

    return new Date(props.proveedor.created_at).toLocaleString("es-PE", {
        dateStyle: "long",
        timeStyle: "short",
    });
});

const fechaActualizacion = computed(() => {
    if (!props.proveedor?.updated_at || !props.proveedor?.created_at) {
        return "No disponible";
    }

    const creado = new Date(props.proveedor.created_at).getTime();
    const actualizado = new Date(props.proveedor.updated_at).getTime();

    if (actualizado <= creado) {
        return "Sin actualizaciones";
    }

    return new Date(props.proveedor.updated_at).toLocaleString("es-PE", {
        dateStyle: "long",
        timeStyle: "short",
    });
});
</script>

<template>
    <Dialog
        :visible="visible"
        @update:visible="emit('update:visible', $event)"
        modal
        header="Información del proveedor"
        :style="{ width: '650px' }"
        :breakpoints="{ '700px': '90vw', '500px': '95vw' }"
    >
        <div v-if="proveedor" class="flex flex-col gap-5">
            <!-- INFORMACIÓN FISCAL -->
            <div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <!-- RAZÓN SOCIAL -->
                    <div
                        class="border border-gray-200 rounded-xl p-3 sm:col-span-2"
                    >
                        <span class="block text-xs text-gray-500 mb-1">
                            Razón social
                        </span>

                        <span class="font-medium text-gray-800 break-words">
                            {{ proveedor.razon_social || "—" }}
                        </span>
                    </div>

                    <!-- NOMBRE COMERCIAL -->
                    <div class="border border-gray-200 rounded-xl p-3">
                        <span class="block text-xs text-gray-500 mb-1">
                            Nombre comercial
                        </span>

                        <span class="font-medium text-gray-800 break-words">
                            {{ proveedor.nombre_comercial || "—" }}
                        </span>
                    </div>

                    <!-- TIPO DOCUMENTO -->
                    <div class="border border-gray-200 rounded-xl p-3">
                        <span class="block text-xs text-gray-500 mb-1">
                            Tipo de documento
                        </span>

                        <div class="flex items-center gap-2">
                            <Tag :value="tipoDocumentoTexto" severity="info" />
                        </div>
                    </div>

                    <!-- NÚMERO DOCUMENTO -->
                    <div
                        class="border border-gray-200 rounded-xl p-3 sm:col-span-2"
                    >
                        <span class="block text-xs text-gray-500 mb-1">
                            Número de documento
                        </span>

                        <div class="flex items-center gap-2">
                            <span
                                class="font-semibold text-gray-800 tracking-wide"
                            >
                                {{ proveedor.n_documento || "—" }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- INFORMACIÓN DE CONTACTO -->
            <div>
                <div class="flex items-center gap-2 mb-3">
                    <i class="pi pi-address-book text-indigo-600"></i>

                    <h3 class="font-semibold text-gray-800">
                        Información de contacto
                    </h3>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <!-- CONTACTO -->
                    <div class="border border-gray-200 rounded-xl p-3">
                        <span class="block text-xs text-gray-500 mb-1">
                            Nombre de contacto
                        </span>

                        <div class="flex items-center gap-2">
                            <i class="pi pi-user text-indigo-500"></i>

                            <span class="font-medium text-gray-800 break-words">
                                {{ proveedor.nombre_contacto || "Sin información" }}
                            </span>
                        </div>
                    </div>

                    <!-- TELÉFONO -->
                    <div class="border border-gray-200 rounded-xl p-3">
                        <span class="block text-xs text-gray-500 mb-1">
                            Teléfono
                        </span>

                        <div class="flex items-center gap-2">
                            <i class="pi pi-phone text-indigo-500"></i>

                            <span class="font-medium text-gray-800">
                                {{ proveedor.telefono || "Sin información" }}
                            </span>
                        </div>
                    </div>

                    <!-- CORREO -->
                    <div class="border border-gray-200 rounded-xl p-3">
                        <span class="block text-xs text-gray-500 mb-1">
                            Correo electrónico
                        </span>

                        <div class="flex items-center gap-2 min-w-0">
                            <i class="pi pi-envelope text-indigo-500"></i>

                            <span
                                class="font-medium text-gray-800 truncate"
                                :title="proveedor.email"
                            >
                                {{ proveedor.email || "Sin información" }}
                            </span>
                        </div>
                    </div>

                    <!-- DIRECCIÓN -->
                    <div class="border border-gray-200 rounded-xl p-3">
                        <span class="block text-xs text-gray-500 mb-1">
                            Dirección
                        </span>

                        <div class="flex items-start gap-2">
                            <i
                                class="pi pi-map-marker text-indigo-500 mt-0.5"
                            ></i>

                            <span class="font-medium text-gray-800 break-words">
                                {{ proveedor.direccion || "Sin información" }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- INFORMACIÓN DE REGISTRO -->
            <div>
                <div class="flex items-center gap-2 mb-3">
                    <i class="pi pi-calendar text-indigo-600"></i>

                    <h3 class="font-semibold text-gray-800">
                        Información de registro
                    </h3>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <!-- FECHA CREACIÓN -->
                    <div class="border border-gray-200 rounded-xl p-3">
                        <span class="block text-xs text-gray-500 mb-1">
                            Fecha de creación
                        </span>

                        <div class="flex items-start gap-2">
                            <i
                                class="pi pi-calendar-plus text-indigo-500 mt-0.5"
                            ></i>

                            <span class="font-medium text-gray-800 text-sm">
                                {{ fechaCreacion }}
                            </span>
                        </div>
                    </div>

                    <!-- FECHA ACTUALIZACIÓN -->
                    <div class="border border-gray-200 rounded-xl p-3">
                        <span class="block text-xs text-gray-500 mb-1">
                            Última actualización
                        </span>

                        <div class="flex items-start gap-2">
                            <i class="pi pi-history text-indigo-500 mt-0.5"></i>

                            <span class="font-medium text-gray-800 text-sm">
                                {{ fechaActualizacion }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- FOOTER -->
            <div class="flex justify-end pt-3 border-t border-gray-100">
                <Button
                    label="Cerrar"
                    icon="pi pi-times"
                    severity="secondary"
                    outlined
                    @click="cerrar"
                />
            </div>
        </div>
    </Dialog>
</template>
