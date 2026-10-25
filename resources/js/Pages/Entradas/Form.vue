<script setup>
import { computed, ref, watch } from "vue";
import { router, useForm } from "@inertiajs/vue3";

import Swal from "sweetalert2";

import ProveedorForm from "../Proveedores/Form.vue";
import ProductoForm from "../Productos/Form.vue";

const mostrarProveedorForm = ref(false);
const mostrarProductoForm = ref(false);

const props = defineProps({
    entrada: {
        type: Object,
        default: null,
    },

    proveedores: {
        type: Array,
        default: () => [],
    },

    productos: {
        type: Array,
        default: () => [],
    },

    codigoIngreso: {
        type: String,
        default: "",
    },
    categorias: {
        type: Array,
        default: () => [],
    },
    marcas: {
        type: Array,
        default: () => [],
    },
});

const visible = defineModel("visible");

const emit = defineEmits(["update:visible", "saved", "cancel"]);

/*
|--------------------------------------------------------------------------
| FORMULARIO
|--------------------------------------------------------------------------
*/

const form = useForm({
    codigo_ingreso: "",
    tiene_documento: false,
    tipo_comprobante: null,
    numero_comprobante: "",
    fecha_ingreso: new Date(),
    proveedor_id: null,
    observacion: "",
    detalles: [],
});

/*
|--------------------------------------------------------------------------
| ESTADOS
|--------------------------------------------------------------------------
*/

const tieneProveedor = ref(false);

const productoBusqueda = ref(null);

const productosFiltrados = ref([]);

const buscandoProducto = ref(false);

/*
|--------------------------------------------------------------------------
| TIPOS DE COMPROBANTE
|--------------------------------------------------------------------------
*/

const tiposComprobante = [
    {
        label: "Factura",
        value: "factura",
    },
    {
        label: "Boleta",
        value: "boleta",
    },
    {
        label: "Guía de remisión",
        value: "guia_remision",
    },
    {
        label: "Nota de compra",
        value: "nota_compra",
    },
    {
        label: "Orden de compra",
        value: "orden_compra",
    },
];

/*
|--------------------------------------------------------------------------
| FECHA MÍNIMA PARA VENCIMIENTO
|--------------------------------------------------------------------------
*/

const fechaMinima = computed(() => {
    const hoy = new Date();

    return new Date(hoy.getFullYear(), hoy.getMonth(), 1);
});

/*
|--------------------------------------------------------------------------
| COMPUTED
|--------------------------------------------------------------------------
*/

const esEdicion = computed(() => {
    return props.entrada !== null;
});

const titulo = computed(() => {
    return esEdicion.value ? "Editar entrada" : "Nueva entrada";
});

const subtitulo = computed(() => {
    return esEdicion.value
        ? "Actualiza la información de la entrada"
        : "Registra los productos ingresados al inventario";
});

const fechaLocal = (fecha) => {
    if (!fecha) {
        return null;
    }

    if (typeof fecha === "string" && /^\d{4}-\d{2}-\d{2}$/.test(fecha)) {
        const [anio, mes, dia] = fecha.split("-").map(Number);

        return new Date(anio, mes - 1, dia);
    }

    return new Date(fecha);
};

const fechaParaServidor = (fecha) => {
    if (!fecha) {
        return null;
    }

    const valor = fechaLocal(fecha);

    return [
        valor.getFullYear(),
        String(valor.getMonth() + 1).padStart(2, "0"),
        String(valor.getDate()).padStart(2, "0"),
    ].join("-");
};

/*
|--------------------------------------------------------------------------
| PROVEEDOR GUARDADO
|--------------------------------------------------------------------------
*/

const proveedorGuardado = (proveedor) => {
    mostrarProveedorForm.value = false;

    if (proveedor) {
        form.proveedor_id = proveedor.id;
        tieneProveedor.value = true;
    }
};

/*
|--------------------------------------------------------------------------
| PRODUCTO GUARDADO
|--------------------------------------------------------------------------
*/

const productoGuardado = async (producto) => {
    mostrarProductoForm.value = false;

    if (producto && typeof producto === "object") {
        const productoNuevo = producto.producto ?? producto;

        productoBusqueda.value = productoNuevo;

        agregarProducto(productoNuevo);
    }

    await Swal.fire({
        title: "¡Guardado!",
        text: "El producto fue registrado correctamente.",
        icon: "success",
        confirmButtonText: "Aceptar",
    });
};

/*
|--------------------------------------------------------------------------
| WATCH - PROVEEDOR
|--------------------------------------------------------------------------
*/

watch(tieneProveedor, (valor) => {
    if (!valor) {
        form.proveedor_id = null;
        form.clearErrors("proveedor_id");
    }
});

/*
|--------------------------------------------------------------------------
| WATCH - DOCUMENTO
|--------------------------------------------------------------------------
*/

watch(
    () => form.tiene_documento,
    (valor) => {
        if (!valor) {
            form.tipo_comprobante = null;
            form.numero_comprobante = "";

            form.clearErrors("tipo_comprobante", "numero_comprobante");
        }
    },
);

/*
|--------------------------------------------------------------------------
| INICIALIZAR FORMULARIO
|--------------------------------------------------------------------------
*/

watch(
    [() => props.entrada, () => props.codigoIngreso],
    ([entrada]) => {
        if (entrada) {
            form.codigo_ingreso = entrada.codigo_ingreso ?? "";

            form.tiene_documento =
                !!entrada.tipo_comprobante || !!entrada.numero_comprobante;

            form.tipo_comprobante = entrada.tipo_comprobante ?? null;

            form.numero_comprobante = entrada.numero_comprobante ?? "";

            form.fecha_ingreso = entrada.fecha_ingreso
                ? new Date(entrada.fecha_ingreso)
                : new Date();

            form.proveedor_id = entrada.proveedor_id ?? null;

            tieneProveedor.value = !!entrada.proveedor_id;

            form.observacion = entrada.observacion ?? "";

            form.detalles = (entrada.detalles ?? []).map((detalle) => {
                const precioCompra = Number(detalle.precio_compra) || 0;

                const precioVenta =
                    Number(detalle.precio_venta) ||
                    Math.round(precioCompra * 1.3 * 100) / 100;

                const fechaVencimiento =
                    detalle.lotes?.[0]?.fecha_vencimiento ??
                    detalle.fecha_vencimiento ??
                    null;

                return {
                    producto_id: detalle.producto_id,

                    producto: detalle.producto,

                    cantidad: Number(detalle.cantidad) || 1,

                    precio_compra: precioCompra,

                    precio_venta: precioVenta,

                    tiene_descuento: Boolean(detalle.tiene_descuento),

                    descuento: Number(detalle.descuento) || 0,

                    subtotal: Number(detalle.subtotal) || 0,

                    tiene_vencimiento: !!fechaVencimiento,

                    fecha_vencimiento: fechaVencimiento
                        ? fechaLocal(fechaVencimiento)
                        : null,
                };
            });
        } else {
            form.reset();

            form.codigo_ingreso = props.codigoIngreso;

            form.tiene_documento = false;

            form.tipo_comprobante = null;

            form.numero_comprobante = "";

            form.fecha_ingreso = new Date();

            form.proveedor_id = null;

            form.observacion = "";

            form.detalles = [];

            tieneProveedor.value = false;

            productoBusqueda.value = null;

            productosFiltrados.value = [];
        }

        form.clearErrors();
    },
    {
        immediate: true,
    },
);

/*
|--------------------------------------------------------------------------
| BUSCAR PRODUCTO
|--------------------------------------------------------------------------
*/

let temporizadorBusqueda = null;

const buscarProducto = (event) => {
    const query = String(event.query ?? "").trim();

    clearTimeout(temporizadorBusqueda);

    if (!query) {
        productosFiltrados.value = [];

        return;
    }

    temporizadorBusqueda = setTimeout(() => {
        buscandoProducto.value = true;

        router.get(
            route("entradas.index"),
            {
                buscar_producto: query,
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only: ["productos"],

                onSuccess: (page) => {
                    productosFiltrados.value = page.props.productos ?? [];
                },
                onFinish: () => {
                    buscandoProducto.value = false;
                },
            },
        );
    }, 150);
};

/*
|--------------------------------------------------------------------------
| AGREGAR PRODUCTO
|--------------------------------------------------------------------------
*/

const agregarProducto = (producto = null) => {
    const productoSeleccionado = producto ?? productoBusqueda.value;

    if (!productoSeleccionado || !productoSeleccionado.id) {
        return;
    }

    const existe = form.detalles.find(
        (detalle) => detalle.producto_id === productoSeleccionado.id,
    );

    if (existe) {
        existe.cantidad = Number(existe.cantidad || 0) + 1;

        calcularSubtotal(existe);

        productoBusqueda.value = null;

        return;
    }

    form.detalles.push({
        producto_id: productoSeleccionado.id,

        producto: productoSeleccionado,

        cantidad: 1,

        precio_compra: null,

        precio_venta: null,

        tiene_descuento: false,

        descuento: null,

        subtotal: 0,

        tiene_vencimiento: false,

        fecha_vencimiento: null,
    });

    productoBusqueda.value = null;
};

/*
|--------------------------------------------------------------------------
| PRECIO DE VENTA
|--------------------------------------------------------------------------
*/

const calcularPrecioVenta = (detalle) => {
    const precioCompra = Number(detalle.precio_compra) || 0;

    if (precioCompra <= 0) {
        detalle.precio_venta = null;

        calcularSubtotal(detalle);

        return;
    }

    detalle.precio_venta =
        Math.round((precioCompra * 1.3 + Number.EPSILON) * 100) / 100;

    calcularSubtotal(detalle);
};

/*
|--------------------------------------------------------------------------
| SUBTOTAL
|--------------------------------------------------------------------------
*/

const calcularSubtotal = (detalle) => {
    const cantidad = Number(detalle.cantidad) || 0;

    const costo = Number(detalle.precio_compra) || 0;

    detalle.subtotal = Math.round(cantidad * costo * 100) / 100;
};

/*
|--------------------------------------------------------------------------
| DESCUENTO
|--------------------------------------------------------------------------
*/

const cambiarDescuento = (detalle) => {
    if (!detalle.tiene_descuento) {
        detalle.descuento = 0;
    }
};

/*
|--------------------------------------------------------------------------
| VENCIMIENTO
|--------------------------------------------------------------------------
*/

const cambiarVencimiento = (detalle) => {
    if (!detalle.tiene_vencimiento) {
        detalle.fecha_vencimiento = null;
    }
};

/*
|--------------------------------------------------------------------------
| TOTAL COMPRA
|--------------------------------------------------------------------------
*/

const totalCompra = computed(() => {
    return form.detalles.reduce((total, detalle) => {
        return total + (Number(detalle.subtotal) || 0);
    }, 0);
});

/*
|--------------------------------------------------------------------------
| ELIMINAR DETALLE
|--------------------------------------------------------------------------
*/

const eliminarDetalle = (index) => {
    form.detalles.splice(index, 1);
};

/*
|--------------------------------------------------------------------------
| GUARDAR
|--------------------------------------------------------------------------
*/

const guardar = () => {
    if (form.detalles.length === 0) {
        Swal.fire({
            title: "Sin productos",
            text: "Debes agregar al menos un producto a la entrada.",
            icon: "warning",
            confirmButtonText: "Aceptar",
        });

        return;
    }

    /*
    |--------------------------------------------------------------------------
    | Validar documento
    |--------------------------------------------------------------------------
    */

    if (form.tiene_documento) {
        if (!form.tipo_comprobante) {
            Swal.fire({
                title: "Tipo de comprobante",
                text: "Selecciona el tipo de comprobante.",
                icon: "warning",
                confirmButtonText: "Aceptar",
            });

            return;
        }

        if (!form.numero_comprobante.trim()) {
            Swal.fire({
                title: "Número de comprobante",
                text: "Ingresa el número del comprobante.",
                icon: "warning",
                confirmButtonText: "Aceptar",
            });

            return;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Validar proveedor
    |--------------------------------------------------------------------------
    */

    if (tieneProveedor.value && !form.proveedor_id) {
        Swal.fire({
            title: "Proveedor",
            text: "Selecciona un proveedor.",
            icon: "warning",
            confirmButtonText: "Aceptar",
        });

        return;
    }

    /*
    |--------------------------------------------------------------------------
    | Preparar detalles
    |--------------------------------------------------------------------------
    */

    form.detalles.forEach((detalle) => {
        calcularPrecioVenta(detalle);

        if (!detalle.tiene_descuento) {
            detalle.descuento = 0;
        }

        if (!detalle.tiene_vencimiento) {
            detalle.fecha_vencimiento = null;
        }
    });

    form.fecha_ingreso = fechaLocal(form.fecha_ingreso);

    form.detalles.forEach((detalle) => {
        detalle.fecha_vencimiento = fechaParaServidor(
            detalle.fecha_vencimiento,
        );
    });

    const opciones = {
        preserveScroll: true,

        onSuccess: async () => {
            emit("saved");

            await Swal.fire({
                title: esEdicion.value ? "¡Actualizada!" : "¡Guardada!",

                text: esEdicion.value
                    ? "La entrada fue actualizada correctamente."
                    : "La entrada fue registrada correctamente.",

                icon: "success",

                confirmButtonText: "Aceptar",
            });
        },
    };

    if (esEdicion.value) {
        form.put(route("entradas.update", props.entrada.id), opciones);
    } else {
        form.post(route("entradas.store"), opciones);
    }
};

/*
|--------------------------------------------------------------------------
| CERRAR
|--------------------------------------------------------------------------
*/

const cerrar = () => {
    if (form.processing) {
        return;
    }

    visible.value = false;

    emit("cancel");
};
</script>

<template>
    <Dialog
        v-model:visible="visible"
        modal
        :style="{
            width: '95vw',
            maxWidth: '1200px',
        }"
        :closable="!form.processing"
    >
        <!-- ========================================================= -->
        <!-- HEADER -->
        <!-- ========================================================= -->

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

        <div class="flex flex-col gap-5">
            <!-- ========================================================= -->
            <!-- INFORMACIÓN DE LA ENTRADA -->
            <!-- ========================================================= -->

            <div>
                <h3 class="text-base font-semibold mb-4">
                    Información de la entrada
                </h3>

                <!--
                    PRIMERA FILA:
                    CÓDIGO DE INGRESO | CHECKBOX DOCUMENTO
                -->

                <div class="grid grid-cols-2 gap-x-5 gap-y-5">
                    <!-- ================================================= -->
                    <!-- CÓDIGO DE INGRESO -->
                    <!-- ================================================= -->

                    <div class="flex flex-col gap-1">
                        <FloatLabel variant="on">
                            <InputText
                                id="codigo_ingreso"
                                v-model="form.codigo_ingreso"
                                class="w-full"
                                disabled
                            />

                            <label for="codigo_ingreso">
                                Código de ingreso
                            </label>
                        </FloatLabel>

                        <small class="text-gray-500">
                            Generado automáticamente
                        </small>
                    </div>

                    <!-- ================================================= -->
                    <!-- CHECKBOX DOCUMENTO -->
                    <!-- ================================================= -->

                    <div class="flex items-center justify-end">
                        <div class="flex items-center gap-2">
                            <Checkbox
                                v-model="form.tiene_documento"
                                inputId="tiene_documento"
                                :binary="true"
                            />

                            <label for="tiene_documento" class="cursor-pointer">
                                La entrada tiene documento
                            </label>
                        </div>
                    </div>

                    <!-- ================================================= -->
                    <!-- DOCUMENTO -->
                    <!-- ================================================= -->

                    <div
                        v-if="form.tiene_documento"
                        class="col-span-2 border-b pb-4"
                    >
                        <div class="grid grid-cols-2 gap-5">
                            <!-- =============================== -->
                            <!-- TIPO COMPROBANTE -->
                            <!-- =============================== -->

                            <div class="flex flex-col gap-1">
                                <FloatLabel variant="on">
                                    <Select
                                        id="tipo_comprobante"
                                        v-model="form.tipo_comprobante"
                                        :options="tiposComprobante"
                                        optionLabel="label"
                                        optionValue="value"
                                        class="w-full"
                                        :invalid="
                                            !!form.errors.tipo_comprobante
                                        "
                                    />

                                    <label for="tipo_comprobante">
                                        Tipo de comprobante
                                        <span class="text-red-500"> * </span>
                                    </label>
                                </FloatLabel>

                                <small
                                    v-if="form.errors.tipo_comprobante"
                                    class="text-red-500"
                                >
                                    {{ form.errors.tipo_comprobante }}
                                </small>
                            </div>

                            <!-- =============================== -->
                            <!-- NÚMERO COMPROBANTE -->
                            <!-- =============================== -->

                            <div class="flex flex-col gap-1">
                                <FloatLabel variant="on">
                                    <InputText
                                        id="numero_comprobante"
                                        v-model="form.numero_comprobante"
                                        class="w-full"
                                        placeholder=""
                                        :invalid="
                                            !!form.errors.numero_comprobante
                                        "
                                    />

                                    <label for="numero_comprobante">
                                        Número de comprobante
                                        <span class="text-red-500"> * </span>
                                    </label>
                                </FloatLabel>

                                <small
                                    v-if="form.errors.numero_comprobante"
                                    class="text-red-500"
                                >
                                    {{ form.errors.numero_comprobante }}
                                </small>
                            </div>
                        </div>
                    </div>

                    <!-- ================================================= -->
                    <!-- PROVEEDOR -->
                    <!-- ================================================= -->

                    <div class="col-span-2 border-b pb-4">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <h3 class="font-semibold text-base m-0">
                                    Proveedor
                                </h3>

                                <small class="text-gray-500">
                                    Puedes registrar la entrada con o sin
                                    proveedor.
                                </small>
                            </div>

                            <div class="flex items-center gap-2">
                                <Checkbox
                                    v-model="tieneProveedor"
                                    inputId="tiene_proveedor"
                                    :binary="true"
                                />

                                <label
                                    for="tiene_proveedor"
                                    class="cursor-pointer"
                                >
                                    La entrada tiene proveedor
                                </label>
                            </div>
                        </div>

                        <div
                            v-if="tieneProveedor"
                            class="grid grid-cols-[1fr_auto] gap-3 items-center"
                        >
                            <!-- PROVEEDOR -->

                            <FloatLabel variant="on">
                                <Select
                                    id="proveedor_id"
                                    v-model="form.proveedor_id"
                                    :options="props.proveedores"
                                    optionLabel="nombre_comercial"
                                    optionValue="id"
                                    class="w-full"
                                    :invalid="!!form.errors.proveedor_id"
                                    emptyMessage="No hay proveedores disponibles"
                                />

                                <label for="proveedor_id"> Proveedor </label>
                            </FloatLabel>

                            <!-- NUEVO PROVEEDOR -->

                            <Button
                                type="button"
                                icon="pi pi-plus"
                                label="Nuevo proveedor"
                                severity="success"
                                @click="mostrarProveedorForm = true"
                            />
                        </div>

                        <small
                            v-if="form.errors.proveedor_id"
                            class="text-red-500 block mt-1"
                        >
                            {{ form.errors.proveedor_id }}
                        </small>
                    </div>

                    <!-- ================================================= -->
                    <!-- FECHA INGRESO -->
                    <!-- ================================================= -->

                    <div class="col-span-2 flex flex-col gap-1">
                        <FloatLabel variant="on" class="w-full">
                            <DatePicker
                                id="fecha_ingreso"
                                v-model="form.fecha_ingreso"
                                class="w-full"
                                showIcon
                                dateFormat="dd/mm/yy"
                            />

                            <label for="fecha_ingreso">
                                Fecha de ingreso
                                <span class="text-red-500"> * </span>
                            </label>
                        </FloatLabel>
                    </div>

                    <!-- ================================================= -->
                    <!-- OBSERVACIÓN -->
                    <!-- ================================================= -->

                    <div class="flex flex-col gap-1 col-span-2">
                        <FloatLabel variant="on">
                            <Textarea
                                id="observacion"
                                v-model="form.observacion"
                                class="w-full"
                                rows="3"
                                autoResize
                            />

                            <label for="observacion"> Observación </label>
                        </FloatLabel>
                    </div>
                </div>
            </div>

            <Divider />

            <!-- ========================================================= -->
            <!-- PRODUCTOS -->
            <!-- ========================================================= -->

            <div>
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="text-base font-semibold mb-1">
                            Productos ingresados
                        </h3>

                        <p class="text-sm text-gray-500 mt-1">
                            Busca un producto por nombre, código o código de
                            barras
                        </p>
                    </div>

                    <span
                        v-if="form.detalles.length > 0"
                        class="text-sm text-gray-500"
                    >
                        {{ form.detalles.length }}

                        {{
                            form.detalles.length === 1
                                ? "producto"
                                : "productos"
                        }}
                    </span>
                </div>

                <!-- BUSCADOR -->

                <div class="flex items-end gap-3">
                    <FloatLabel variant="on" class="w-full">
                        <AutoComplete
                            id="producto"
                            v-model="productoBusqueda"
                            :suggestions="productosFiltrados"
                            optionLabel="nombre"
                            class="w-full"
                            inputClass="w-full"
                            fluid
                            dropdown
                            @complete="buscarProducto"
                            @option-select="
                                (event) => agregarProducto(event.value)
                            "
                        >
                            <template #option="{ option }">
                                <div class="flex items-center gap-3 py-1">
                                    <div
                                        class="flex items-center justify-center w-9 h-9 rounded-lg bg-blue-50 text-blue-600 shrink-0"
                                    >
                                        <i class="pi pi-box"></i>
                                    </div>

                                    <div class="flex flex-col min-w-0">
                                        <span class="font-medium truncate">
                                            {{ option.nombre }}
                                        </span>

                                        <small class="text-gray-500">
                                            Código:
                                            {{ option.codigo }}

                                            <span v-if="option.codigo_barras">
                                                ·
                                                {{ option.codigo_barras }}
                                            </span>
                                        </small>
                                    </div>
                                </div>
                            </template>
                        </AutoComplete>

                        <label for="producto"> Buscar producto </label>
                    </FloatLabel>

                    <Button
                        icon="pi pi-plus"
                        severity="success"
                        type="button"
                        label="Nuevo producto"
                        class="whitespace-nowrap flex-shrink-0"
                        @click="mostrarProductoForm = true"
                    />
                </div>
            </div>

            <!-- ========================================================= -->
            <!-- TABLA -->
            <!-- ========================================================= -->

            <div
                v-if="form.detalles.length > 0"
                class="border border-gray-200 rounded-lg overflow-x-auto"
            >
                <table class="w-full min-w-[950px]">
                    <thead class="bg-gray-50">
                        <tr>
                            <th
                                class="text-left px-4 py-3 text-sm font-semibold"
                            >
                                Producto
                            </th>

                            <th
                                class="text-center px-4 py-3 text-sm font-semibold"
                                style="width: 130px"
                            >
                                Cantidad
                            </th>

                            <th
                                class="text-right px-4 py-3 text-sm font-semibold"
                                style="width: 180px"
                            >
                                Precio de compra
                            </th>

                            <th
                                class="text-right px-4 py-3 text-sm font-semibold"
                                style="width: 180px"
                            >
                                Precio de venta
                            </th>

                            <th
                                class="text-center px-4 py-3 text-sm font-semibold"
                                style="width: 130px"
                            >
                                Descuento
                            </th>

                            <th
                                class="text-right px-4 py-3 text-sm font-semibold"
                                style="width: 160px"
                            >
                                Subtotal
                            </th>

                            <th
                                class="text-center px-4 py-3 text-sm font-semibold"
                                style="width: 180px"
                            >
                                Vencimiento
                            </th>

                            <th
                                class="text-center px-4 py-3"
                                style="width: 70px"
                            ></th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr
                            v-for="(detalle, index) in form.detalles"
                            :key="detalle.producto_id"
                            class="border-t border-gray-100"
                        >
                            <!-- PRODUCTO -->

                            <td class="px-4 py-3">
                                <div class="font-medium">
                                    {{ detalle.producto?.nombre }}
                                </div>

                                <small class="text-gray-500">
                                    Código:
                                    {{ detalle.producto?.codigo }}
                                </small>
                            </td>

                            <!-- CANTIDAD -->

                            <td class="px-4 py-3">
                                <InputNumber
                                    v-model="detalle.cantidad"
                                    class="w-full"
                                    :min="1"
                                    :showButtons="true"
                                    @update:modelValue="
                                        calcularSubtotal(detalle)
                                    "
                                />
                            </td>

                            <!-- PRECIO COMPRA -->

                            <td class="px-4 py-3">
                                <InputNumber
                                    v-model="detalle.precio_compra"
                                    class="w-full"
                                    mode="currency"
                                    currency="PEN"
                                    locale="es-PE"
                                    :min="0"
                                    :minFractionDigits="2"
                                    :maxFractionDigits="2"
                                    @update:modelValue="
                                        calcularPrecioVenta(detalle)
                                    "
                                />
                            </td>

                            <!-- PRECIO VENTA -->

                            <td class="px-4 py-3">
                                <InputNumber
                                    v-model="detalle.precio_venta"
                                    class="w-full"
                                    mode="currency"
                                    currency="PEN"
                                    locale="es-PE"
                                    :min="0"
                                    :minFractionDigits="2"
                                    :maxFractionDigits="2"
                                    disabled
                                    inputClass="bg-gray-50"
                                />
                            </td>

                            <!-- DESCUENTO -->

                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2 w-[180px]">
                                    <Checkbox
                                        v-model="detalle.tiene_descuento"
                                        :inputId="`descuento_${index}`"
                                        :binary="true"
                                        @update:modelValue="
                                            cambiarDescuento(detalle)
                                        "
                                    />
                                    <label
                                        v-if="!detalle.tiene_descuento"
                                        :for="`descuento_${index}`"
                                        class="text-sm text-surface-600 cursor-pointer select-none whitespace-nowrap"
                                    >
                                        Aplicar descuento
                                    </label>
                                    <InputNumber
                                        v-else
                                        v-model="detalle.descuento"
                                        class="w-full"
                                        mode="currency"
                                        currency="PEN"
                                        locale="es-PE"
                                        :min="0"
                                        :max="detalle.precio_venta ?? 0"
                                        :minFractionDigits="2"
                                        :maxFractionDigits="2"
                                    />
                                </div>
                            </td>

                            <!-- SUBTOTAL -->

                            <td class="px-4 py-3 text-right font-medium">
                                {{
                                    Number(detalle.subtotal).toLocaleString(
                                        "es-PE",
                                        {
                                            style: "currency",
                                            currency: "PEN",
                                        },
                                    )
                                }}
                            </td>

                            <!-- VENCIMIENTO -->

                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2 w-[180px]">
                                    <Checkbox
                                        v-model="detalle.tiene_vencimiento"
                                        :inputId="`vencimiento_${index}`"
                                        :binary="true"
                                        @update:modelValue="
                                            cambiarVencimiento(detalle)
                                        "
                                    />
                                    <label
                                        v-if="!detalle.tiene_vencimiento"
                                        :for="`vencimiento_${index}`"
                                        class="text-sm text-surface-600 cursor-pointer select-none whitespace-nowrap"
                                    >
                                        Tiene vencimiento
                                    </label>
                                    <DatePicker
                                        v-else
                                        v-model="detalle.fecha_vencimiento"
                                        view="month"
                                        dateFormat="mm/yy"
                                        showIcon
                                        class="w-full"
                                        :minDate="fechaMinima"
                                        placeholder="Mes/año de vencimiento"
                                    />
                                </div>
                            </td>

                            <!-- ELIMINAR -->

                            <td class="px-4 py-3 text-center">
                                <Button
                                    icon="pi pi-trash"
                                    severity="danger"
                                    text
                                    rounded
                                    type="button"
                                    @click="eliminarDetalle(index)"
                                />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- ========================================================= -->
            <!-- SIN PRODUCTOS -->
            <!-- ========================================================= -->

            <div
                v-else
                class="border border-dashed border-gray-300 rounded-lg py-8 text-center"
            >
                <i class="pi pi-shopping-cart text-3xl text-gray-400 mb-3"></i>

                <p class="text-gray-500 m-0">No hay productos agregados.</p>

                <small class="text-gray-400">
                    Busca un producto para agregarlo a la entrada.
                </small>
            </div>

            <!-- ========================================================= -->
            <!-- TOTAL -->
            <!-- ========================================================= -->

            <div class="flex justify-end">
                <div
                    class="w-72 border border-gray-200 rounded-lg p-4 bg-gray-50"
                >
                    <div class="flex justify-between items-center">
                        <span class="font-semibold text-gray-600">
                            Total compra
                        </span>

                        <span class="text-xl font-bold">
                            {{
                                totalCompra.toLocaleString("es-PE", {
                                    style: "currency",
                                    currency: "PEN",
                                })
                            }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- ========================================================= -->
        <!-- FOOTER -->
        <!-- ========================================================= -->

        <template #footer>
            <Button
                label="Cancelar"
                severity="secondary"
                variant="outlined"
                type="button"
                :disabled="form.processing"
                @click="cerrar"
            />

            <Button
                :label="esEdicion ? 'Actualizar' : 'Guardar'"
                icon="pi pi-save"
                :loading="form.processing"
                type="button"
                @click="guardar"
            />
        </template>

        <!-- ========================================================= -->
        <!-- FORMULARIO PROVEEDOR -->
        <!-- ========================================================= -->

        <ProveedorForm
            v-model:visible="mostrarProveedorForm"
            @saved="proveedorGuardado"
        />

        <!-- ========================================================= -->
        <!-- FORMULARIO PRODUCTO -->
        <!-- ========================================================= -->

        <ProductoForm
            v-model:visible="mostrarProductoForm"
            :categorias="props.categorias"
            :marcas="props.marcas"
            @saved="productoGuardado"
        />
    </Dialog>
</template>
