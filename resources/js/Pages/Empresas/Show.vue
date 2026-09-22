<script setup>
import { computed } from "vue";

const props = defineProps({
    visible: {
        type: Boolean,
        default: false,
    },

    empresa: {
        type: Object,
        default: null,
    },
});

const emit = defineEmits(["update:visible"]);

const nombreEmpresa = computed(() => {
    return (
        props.empresa?.nombre_comercial ||
        props.empresa?.razon_social ||
        "Sin nombre"
    );
});

const iniciales = computed(() => {
    const nombre =
        props.empresa?.nombre_comercial || props.empresa?.razon_social || "";

    if (!nombre) {
        return "E";
    }

    return nombre
        .split(" ")
        .filter(Boolean)
        .slice(0, 2)
        .map((palabra) => palabra.charAt(0).toUpperCase())
        .join("");
});

const estadoSeverity = computed(() => {
    return props.empresa?.activo ? "success" : "danger";
});

const estadoTexto = computed(() => {
    return props.empresa?.activo ? "Activa" : "Inactiva";
});

const nombreTienda = computed(() => {
    return (
        props.empresa?.tienda?.nombre_comercial ||
        props.empresa?.tienda?.nombre ||
        "Sin tienda asignada"
    );
});

const serieBoleta = computed(() => {
    const series =
        props.empresa?.series_comprobantes || props.empresa?.series || [];

    if (!Array.isArray(series)) {
        return "—";
    }

    const serie = series.find((item) => {
        const nombre =
            item?.tipo_comprobante?.nombre?.toString().toLowerCase() || "";

        return nombre.includes("boleta") || nombre.includes("boleto");
    });

    return serie?.serie || "—";
});

const serieFactura = computed(() => {
    const series =
        props.empresa?.series_comprobantes || props.empresa?.series || [];

    if (!Array.isArray(series)) {
        return "—";
    }

    const serie = series.find((item) => {
        const nombre =
            item?.tipo_comprobante?.nombre?.toString().toLowerCase() || "";

        return nombre.includes("factura");
    });

    return serie?.serie || "—";
});

const correlativoBoleta = computed(() => {
    const series =
        props.empresa?.series_comprobantes || props.empresa?.series || [];

    if (!Array.isArray(series)) {
        return "000000";
    }

    const serie = series.find((item) => {
        const nombre =
            item?.tipo_comprobante?.nombre?.toString().toLowerCase() || "";

        return nombre.includes("boleta") || nombre.includes("boleto");
    });

    const numero = Number(serie?.correlativo_actual ?? 0);

    return Number.isFinite(numero) ? String(numero).padStart(6, "0") : "000000";
});

const correlativoFactura = computed(() => {
    const series =
        props.empresa?.series_comprobantes || props.empresa?.series || [];

    if (!Array.isArray(series)) {
        return "000000";
    }

    const serie = series.find((item) => {
        const nombre =
            item?.tipo_comprobante?.nombre?.toString().toLowerCase() || "";

        return nombre.includes("factura");
    });

    const numero = Number(serie?.correlativo_actual ?? 0);

    return Number.isFinite(numero) ? String(numero).padStart(6, "0") : "000000";
});

const cerrar = () => {
    emit("update:visible", false);
};

const fechaCreacion = computed(() => {
    if (!props.empresa?.created_at) {
        return "No disponible";
    }

    return new Date(props.empresa.created_at).toLocaleString("es-PE", {
        dateStyle: "long",
        timeStyle: "short",
    });
});

const fechaActualizacion = computed(() => {
    if (!props.empresa?.updated_at || !props.empresa?.created_at) {
        return "No disponible";
    }

    const creado = new Date(props.empresa.created_at).getTime();
    const actualizado = new Date(props.empresa.updated_at).getTime();

    if (actualizado <= creado) {
        return "Sin actualizaciones";
    }

    return new Date(props.empresa.updated_at).toLocaleString("es-PE", {
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
        header="Información de la empresa"
        :style="{ width: '650px' }"
    >
        <div v-if="empresa" class="flex flex-col gap-5">
            <!-- ===================================================== -->
            <!-- DATOS DE LA EMPRESA -->
            <!-- ===================================================== -->

            <div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <!-- RAZÓN SOCIAL -->
                    <div class="border border-gray-200 rounded-xl p-3">
                        <span class="block text-xs text-gray-500 mb-1">
                            Razón social
                        </span>

                        <span class="font-medium text-gray-800">
                            {{ empresa.razon_social || "—" }}
                        </span>
                    </div>

                    <!-- NOMBRE COMERCIAL -->
                    <div class="border border-gray-200 rounded-xl p-3">
                        <span class="block text-xs text-gray-500 mb-1">
                            Nombre comercial
                        </span>

                        <span class="font-medium text-gray-800">
                            {{ empresa.nombre_comercial || "—" }}
                        </span>
                    </div>

                    <!-- RUC -->
                    <div class="border border-gray-200 rounded-xl p-3">
                        <span class="block text-xs text-gray-500 mb-1">
                            RUC
                        </span>

                        <div class="flex items-center gap-2">

                            <span class="font-medium text-gray-800">
                                {{ empresa.ruc || "—" }}
                            </span>
                        </div>
                    </div>

                    <!-- TIENDA -->
                    <div class="border border-gray-200 rounded-xl p-3">
                        <span class="block text-xs text-gray-500 mb-1">
                            Tienda asociada
                        </span>

                        <div class="flex items-center gap-2">

                            <span class="font-medium text-gray-800">
                                {{ nombreTienda }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ===================================================== -->
            <!-- DATOS DE CONTACTO -->
            <!-- ===================================================== -->

            <div>
                <div class="flex items-center gap-2 mb-3">
                    <i class="pi pi-address-book text-indigo-600"></i>

                    <h3 class="font-semibold text-gray-800">
                        Información de contacto
                    </h3>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <!-- DIRECCIÓN -->
                    <div
                        class="border border-gray-200 rounded-xl p-3 sm:col-span-2"
                    >
                        <span class="block text-xs text-gray-500 mb-1">
                            Dirección
                        </span>

                        <div class="flex items-center gap-2">

                            <span class="font-medium text-gray-800">
                                {{ empresa.direccion || "—" }}
                            </span>
                        </div>
                    </div>

                    <!-- TELÉFONO -->
                    <div class="border border-gray-200 rounded-xl p-3">
                        <span class="block text-xs text-gray-500 mb-1">
                            Teléfono
                        </span>

                        <div class="flex items-center gap-2">

                            <span class="font-medium text-gray-800">
                                {{ empresa.telefono || "Sin información" }}
                            </span>
                        </div>
                    </div>

                    <!-- CORREO -->
                    <div class="border border-gray-200 rounded-xl p-3">
                        <span class="block text-xs text-gray-500 mb-1">
                            Correo electrónico
                        </span>

                        <div class="flex items-center gap-2">

                            <span class="font-medium text-gray-800 break-all">
                                {{ empresa.email || "Sin información" }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ===================================================== -->
            <!-- SERIES Y NUMERACIÓN -->
            <!-- ===================================================== -->

            <div>
                <div class="flex items-center gap-2 mb-3">
                    <i class="pi pi-file text-indigo-600"></i>

                    <h3 class="font-semibold text-gray-800">
                        Series y numeración
                    </h3>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <!-- BOLETA -->
                    <div class="border border-gray-200 rounded-xl p-3">
                        <span class="block text-xs text-gray-500 mb-1">
                            Serie de Boleta
                        </span>

                        <div class="flex items-center justify-between gap-3">
                            <div class="flex items-center gap-2">

                                <span class="font-semibold text-gray-800">
                                    {{ serieBoleta }}
                                </span>
                            </div>

                            <Tag :value="correlativoBoleta" severity="info" />
                        </div>

                        <span class="block text-xs text-gray-400 mt-2">
                            Próximo correlativo
                        </span>
                    </div>

                    <!-- FACTURA -->
                    <div class="border border-gray-200 rounded-xl p-3">
                        <span class="block text-xs text-gray-500 mb-1">
                            Serie de Factura
                        </span>

                        <div class="flex items-center justify-between gap-3">
                            <div class="flex items-center gap-2">

                                <span class="font-semibold text-gray-800">
                                    {{ serieFactura }}
                                </span>
                            </div>

                            <Tag :value="correlativoFactura" severity="info" />
                        </div>

                        <span class="block text-xs text-gray-400 mt-2">
                            Próximo correlativo
                        </span>
                    </div>
                </div>
            </div>

            <!-- ===================================================== -->
            <!-- ESTADO -->
            <!-- ===================================================== -->

            <div>
                <div class="flex items-center gap-2 mb-3">
                    <i class="pi pi-power-off text-indigo-600"></i>

                    <h3 class="font-semibold text-gray-800">Estado</h3>
                </div>

                <div class="border border-gray-200 rounded-xl p-3">
                    <div class="flex items-center justify-between">
                        <div>
                            <span class="block text-xs text-gray-500 mb-1">
                                Estado de la empresa
                            </span>

                            <span class="font-medium text-gray-800">
                                {{
                                    empresa.activo
                                        ? "La empresa está activa"
                                        : "La empresa está inactiva"
                                }}
                            </span>
                        </div>

                        <Tag :value="estadoTexto" :severity="estadoSeverity" />
                    </div>
                </div>
            </div>

            <!-- ===================================================== -->
            <!-- INFORMACIÓN DE REGISTRO -->
            <!-- ===================================================== -->

            <div>
                <div class="flex items-center gap-2 mb-3">
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
            </div>

            <!-- ===================================================== -->
            <!-- BOTÓN -->
            <!-- ===================================================== -->

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
