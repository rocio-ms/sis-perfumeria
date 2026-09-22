<script setup>
import { computed } from "vue";

const props = defineProps({
    visible: {
        type: Boolean,
        default: false,
    },

    usuario: {
        type: Object,
        default: null,
    },
});

const emit = defineEmits(["update:visible"]);

const nombre = computed(() => {
    return props.usuario?.name ?? "Sin nombre";
});

const iniciales = computed(() => {
    if (!props.usuario?.name) {
        return "U";
    }

    return props.usuario.name
        .split(" ")
        .filter(Boolean)
        .slice(0, 2)
        .map((nombre) => nombre.charAt(0).toUpperCase())
        .join("");
});

const estadoSeverity = computed(() => {
    return props.usuario?.activo ? "success" : "danger";
});

const estadoTexto = computed(() => {
    return props.usuario?.activo ? "Activo" : "Inactivo";
});

const cerrar = () => {
    emit("update:visible", false);
};

const fechaCreacion = computed(() => {
    if (!props.usuario?.created_at) {
        return "No disponible";
    }

    return new Date(props.usuario.created_at).toLocaleString("es-PE", {
        dateStyle: "long",
        timeStyle: "short",
    });
});

const fechaActualizacion = computed(() => {
    if (!props.usuario?.updated_at || !props.usuario?.created_at) {
        return "No disponible";
    }

    const creado = new Date(props.usuario.created_at).getTime();
    const actualizado = new Date(props.usuario.updated_at).getTime();

    if (actualizado <= creado) {
        return "Sin actualizaciones";
    }

    return new Date(props.usuario.updated_at).toLocaleString("es-PE", {
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
        header="Información del usuario"
        :style="{ width: '600px' }"
    >
        <div v-if="usuario" class="flex flex-col gap-5">
            <div
                class="flex items-center gap-4 p-4 rounded-xl bg-indigo-50 border border-indigo-100"
            >
                <div
                    class="w-16 h-16 rounded-full bg-indigo-600 text-white flex items-center justify-center text-xl font-bold shrink-0"
                >
                    {{ iniciales }}
                </div>

                <div class="min-w-0 flex-1">
                    <div class="flex items-center justify-between gap-2">
                        <h2 class="text-xl font-bold text-gray-800 truncate">
                            {{ nombre }}
                        </h2>

                        <Tag
                            :value="estadoTexto"
                            :severity="estadoSeverity"
                            class="shrink-0"
                        />
                    </div>

                    <p class="text-sm text-gray-500 mt-1">
                        Usuario: {{ usuario.username }}
                    </p>
                </div>
            </div>

            <div>
                <div class="flex items-center gap-2 mb-2">
                    <i class="pi pi-user text-indigo-600"></i>

                    <h3 class="font-semibold text-gray-800">
                        Información personal
                    </h3>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <!-- NOMBRE -->
                    <div class="border border-gray-200 rounded-xl p-3">
                        <span class="block text-xs text-gray-500 mb-1">
                            Nombre completo
                        </span>

                        <span class="font-medium text-gray-800">
                            {{ usuario.name || "—" }}
                        </span>
                    </div>

                    <!-- DNI -->
                    <div class="border border-gray-200 rounded-xl p-3">
                        <span class="block text-xs text-gray-500 mb-1">
                            DNI
                        </span>

                        <span class="font-medium text-gray-800">
                            {{ usuario.dni || "—" }}
                        </span>
                    </div>
                </div>
            </div>

            <div>
                <div class="flex items-center gap-2 mb-3">
                    <i class="pi pi-lock text-indigo-600"></i>

                    <h3 class="font-semibold text-gray-800">
                        Datos del usuario
                    </h3>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <!-- USERNAME -->
                    <div class="border border-gray-200 rounded-xl p-3">
                        <span class="block text-xs text-gray-500 mb-1">
                            Nombre de usuario
                        </span>

                        <span class="font-medium text-gray-800">
                            {{ usuario.username || "—" }}
                        </span>
                    </div>

                    <!-- ROL -->
                    <div class="border border-gray-200 rounded-xl p-3">
                        <span class="block text-xs text-gray-500 mb-1">
                            Rol
                        </span>

                        <div class="flex items-center gap-2">

                            <span class="font-medium text-gray-800">
                                {{
                                    usuario.roles?.length
                                        ? usuario.roles[0].name
                                        : "Sin rol asignado"
                                }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <i class="pi pi-calendar text-indigo-600"></i>

                <h3 class="font-semibold text-gray-800">
                    Información de registro
                </h3>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <!-- FECHA DE CREACIÓN -->
                <div class="border border-gray-200 rounded-xl p-3">
                    <span class="block text-xs text-gray-500 mb-1">
                        Fecha de creación
                    </span>

                    <div class="flex items-center gap-2">
                        <i class="pi pi-calendar-plus text-indigo-500"></i>

                        <span class="font-medium text-gray-800">
                            {{ fechaCreacion }}
                        </span>
                    </div>
                </div>

                <!-- ÚLTIMA ACTUALIZACIÓN -->
                <div class="border border-gray-200 rounded-xl p-3">
                    <span class="block text-xs text-gray-500 mb-1">
                        Última actualización
                    </span>

                    <div class="flex items-center gap-2">
                        <i class="pi pi-history text-indigo-500"></i>

                        <span class="font-medium text-gray-800">
                            {{ fechaActualizacion }}
                        </span>
                    </div>
                </div>
            </div>
            
            <!-- Botón -->

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
