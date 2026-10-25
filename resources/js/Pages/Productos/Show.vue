<script setup>
import { computed } from "vue";

const props = defineProps({
    producto: {
        type: Object,
        default: null,
    },
});

const visible = defineModel("visible");
const producto = computed(() => props.producto);

const precioActual = computed(() => producto.value?.precio_actual ?? null);
const precioVenta = computed(() => Number(precioActual.value?.precio_venta ?? 0));
const descuento = computed(() => Number(precioActual.value?.descuento ?? 0));
const precioFinal = computed(() =>
    Math.max(precioVenta.value - descuento.value, 0),
);

const unidadMedida = computed(() => {
    const unidades = {
        und: "Unidad",
        caja: "Caja",
        pqt: "Paquete",
        blister: "Blíster",
        frasco: "Frasco",
        botella: "Botella",
        ml: "Mililitros",
        l: "Litros",
        gr: "Gramos",
        kg: "Kilogramos",
        kit: "Kit",
        par: "Par",
    };

    return unidades[producto.value?.unidad_medida] ?? producto.value?.unidad_medida ?? "—";
});

const contenidoProducto = computed(() => {
    const unidad = producto.value?.unidad_medida;
    const unidadesContenido = {
        ml: "ml",
        l: "L",
        gr: "gr",
        frasco: "ml",
        botella: "ml",
    };
    const sufijo = unidadesContenido[unidad];

    if (!sufijo || producto.value?.volumen_ml == null) {
        return "—";
    }

    return `${producto.value.volumen_ml} ${sufijo}`;
});

const cerrar = () => {
    visible.value = false;
};

const formatearMoneda = (valor) =>
    Number(valor || 0).toLocaleString("es-PE", {
        style: "currency",
        currency: "PEN",
    });
</script>

<template>
    <Dialog
        v-model:visible="visible"
        modal
        :style="{ width: '720px' }"
        :breakpoints="{ '768px': '90vw', '575px': '95vw' }"
    >
        <template #header>
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center">
                    <i class="pi pi-box text-lg"></i>
                </div>
                <div>
                    <h2 class="text-xl font-semibold m-0 text-gray-800">Detalle de producto</h2>
                    <p class="text-sm text-gray-500 mt-0.5 mb-0">Información del producto e inventario</p>
                </div>
            </div>
        </template>

        <div v-if="producto" class="space-y-5">
            <div class="rounded-xl border border-gray-200 bg-gray-50/50 p-5 flex flex-col sm:flex-row gap-5">
                <img
                    :src="producto.imagen_url ? `/storage/${producto.imagen_url}` : '/images/producto_default.svg'"
                    alt="Imagen del producto"
                    class="w-32 h-32 rounded-xl object-contain border border-gray-200 bg-white p-2 shrink-0"
                />
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2 mb-2">
                        <span class="text-xs font-medium text-gray-600 bg-gray-100 rounded-md px-2 py-1">Código: {{ producto.codigo || "—" }}</span>
                        <Tag :value="producto.activo ? 'Activo' : 'Inactivo'" :severity="producto.activo ? 'success' : 'danger'" />
                    </div>
                    <h3 class="text-2xl font-bold text-gray-800">{{ producto.nombre }}</h3>
                    <p class="text-sm text-gray-500 mt-1">{{ producto.descripcion || "Sin descripción" }}</p>
                </div>
            </div>

            <div>
                <h4 class="font-semibold text-gray-800 mb-3 text-sm flex items-center gap-2"><i class="pi pi-info-circle text-blue-600"></i>Información general</h4>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div class="border border-gray-100 rounded-lg p-3 bg-gray-50/50"><span class="text-xs text-gray-500 block mb-1">Código de barras</span><span class="font-medium text-sm text-gray-800">{{ producto.codigo_barras || "Sin código" }}</span></div>
                    <div class="border border-gray-100 rounded-lg p-3 bg-gray-50/50"><span class="text-xs text-gray-500 block mb-1">Categoría</span><span class="font-medium text-sm text-gray-800">{{ producto.categoria?.nombre || "—" }}</span></div>
                    <div class="border border-gray-100 rounded-lg p-3 bg-gray-50/50"><span class="text-xs text-gray-500 block mb-1">Subcategoría</span><span class="font-medium text-sm text-gray-800">{{ producto.subcategoria?.nombre || "Sin subcategoría" }}</span></div>
                    <div class="border border-gray-100 rounded-lg p-3 bg-gray-50/50"><span class="text-xs text-gray-500 block mb-1">Ubicación</span><span class="font-medium text-sm text-gray-800">{{ producto.ubicacion || "Sin ubicación" }}</span></div>
                    <div class="border border-gray-100 rounded-lg p-3 bg-gray-50/50"><span class="text-xs text-gray-500 block mb-1">Marca</span><span class="font-medium text-sm text-gray-800">{{ producto.marca?.nombre || "Sin marca" }}</span></div>
                    <div class="border border-gray-100 rounded-lg p-3 bg-gray-50/50"><span class="text-xs text-gray-500 block mb-1">Unidad de medida</span><span class="font-medium text-sm text-gray-800">{{ unidadMedida }}</span></div>
                </div>
            </div>

            <!-- ===================================================== -->
            <!-- PROVEEDOR -->
            <!-- ===================================================== -->

            <div>
                <h4 class="font-semibold text-gray-800 mb-3 text-sm flex items-center gap-2"><i class="pi pi-box text-blue-600"></i>Inventario</h4>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    <div class="border border-gray-200 rounded-lg p-3"><span class="text-xs text-gray-500 block mb-1">Stock actual</span><span class="text-xl font-bold text-gray-800">{{ producto.stock_actual ?? 0 }}</span></div>
                    <div class="border border-gray-200 rounded-lg p-3"><span class="text-xs text-gray-500 block mb-1">Stock mínimo</span><span class="text-xl font-bold text-gray-800">{{ producto.stock_minimo ?? 0 }}</span></div>
                    <div class="border border-gray-200 rounded-lg p-3"><span class="text-xs text-gray-500 block mb-1">Contenido</span><span class="text-xl font-bold text-gray-800">{{ contenidoProducto }}</span></div>
                    <div class="border border-gray-200 rounded-lg p-3"><span class="text-xs text-gray-500 block mb-1">Precio venta</span><span class="text-lg font-bold text-gray-800">{{ formatearMoneda(precioVenta) }}</span></div>
                </div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <span class="text-xs text-gray-500 block">Precio final</span>
                    <span class="text-2xl font-bold text-indigo-600">{{ formatearMoneda(precioFinal) }}</span>
                </div>
                <Tag v-if="precioActual?.tiene_descuento" :value="`Descuento: ${formatearMoneda(descuento)}`" severity="warn" />
                <span v-else class="text-sm text-gray-500">Sin descuento</span>
            </div>

            <div class="flex justify-end">
                <Button label="Cerrar" icon="pi pi-times" severity="secondary" variant="outlined" @click="cerrar" />
            </div>
        </div>
    </Dialog>
</template>
