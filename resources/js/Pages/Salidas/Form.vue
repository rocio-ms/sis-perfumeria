<script setup>
import { computed, ref, watch } from "vue";
import { router, useForm } from "@inertiajs/vue3";
import Swal from "sweetalert2";

const props = defineProps({
    salida: {
        type: Object,
        default: null,
    },

    productos: {
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
    tipo_salida: null,
    fecha_salida: new Date(),
    observacion: "",
    total: 0,
    detalles: [],
});

/*
|--------------------------------------------------------------------------
| TIPOS DE SALIDA
|--------------------------------------------------------------------------
*/

const tiposSalida = [
    {
        label: "Consumo interno",
        value: "consumo",
    },
    {
        label: "Merma / Daño",
        value: "merma",
    },
    {
        label: "Devolución",
        value: "devolucion",
    },
];

/*
|--------------------------------------------------------------------------
| PRODUCTOS
|--------------------------------------------------------------------------
*/

const productosFiltrados = ref([]);
const buscandoProducto = ref(false);

let temporizadorBusqueda = null;

/*
|--------------------------------------------------------------------------
| PRODUCTO
|--------------------------------------------------------------------------
|
| busquedaProducto:
|   Es lo que escribe el usuario o lo que escribe el lector.
|
| productoSeleccionado:
|   Es el producto que finalmente se encontró/seleccionó.
|
|--------------------------------------------------------------------------
*/

const busquedaProducto = ref("");

const productoSeleccionado = ref(null);

const loteSeleccionado = ref(null);

const cantidad = ref(1);

/*
|--------------------------------------------------------------------------
| BUSCAR PRODUCTOS
|--------------------------------------------------------------------------
|
| Funciona para:
| - Nombre
| - Código
| - Código de barras
|--------------------------------------------------------------------------
*/

const buscarProductos = (event) => {
    const query = String(event.query ?? "").trim();

    clearTimeout(temporizadorBusqueda);

    if (!query) {
        productosFiltrados.value = [];
        return;
    }

    temporizadorBusqueda = setTimeout(() => {
        buscandoProducto.value = true;

        router.get(
            route("salidas.index"),
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
| SELECCIONAR PRODUCTO
|--------------------------------------------------------------------------
|
| Esta función NO agrega el producto a form.detalles.
|
| Solo:
| 1. Selecciona el producto.
| 2. Busca automáticamente un lote con stock.
| 3. Coloca cantidad = 1.
|--------------------------------------------------------------------------
*/

const seleccionarProducto = (producto) => {
    if (!producto) {
        productoSeleccionado.value = null;
        loteSeleccionado.value = null;
        cantidad.value = 1;

        return;
    }

    productoSeleccionado.value = producto;

    /*
     * Mantener el producto seleccionado
     * visible dentro del AutoComplete.
     */
    busquedaProducto.value = producto;

    /*
     * Buscar automáticamente el primer lote
     * que tenga stock.
     */
    const lote = Array.isArray(producto.lotes)
        ? producto.lotes.find((lote) => Number(lote.cantidad_actual ?? 0) > 0)
        : null;

    loteSeleccionado.value = lote ?? null;

    cantidad.value = 1;

    if (lote) {
        agregarDetalle();
    }
};

/*
|--------------------------------------------------------------------------
| SELECCIÓN DESDE AUTOCOMPLETE
|--------------------------------------------------------------------------
*/

const seleccionarDesdeAutocomplete = (event) => {
    const producto = event?.value ?? null;

    seleccionarProducto(producto);
};

/*
|--------------------------------------------------------------------------
| ESCANEAR DESDE EL MISMO AUTOCOMPLETE
|--------------------------------------------------------------------------
|
| El lector de código de barras actúa como teclado.
|
| Ejemplo:
|
| 775123456789 + Enter
|
| Entonces buscamos ese valor exactamente
| en codigo_barras.
|--------------------------------------------------------------------------
*/

const escanearCodigoBarras = (event) => {
    const codigo = String(event?.target?.value ?? "").trim();

    if (!codigo) {
        return;
    }

    buscandoProducto.value = true;

    clearTimeout(temporizadorBusqueda);

    router.get(
        route("salidas.index"),
        {
            buscar_producto: codigo,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ["productos"],

            onSuccess: (page) => {
                const productos = page.props.productos ?? [];

                productosFiltrados.value = productos;

                /*
                |--------------------------------------------------------------------------
                | Buscar coincidencia exacta por código de barras
                |--------------------------------------------------------------------------
                */

                const producto = productos.find((producto) => {
                    const codigoProducto = String(
                        producto.codigo_barras ?? producto.codigoBarras ?? "",
                    ).trim();

                    return codigoProducto === codigo;
                });

                if (producto) {
                    seleccionarProducto(producto);
                    return;
                }

                /*
                |--------------------------------------------------------------------------
                | Si no existe
                |--------------------------------------------------------------------------
                */

                const esCodigoNumerico = /^\d+$/.test(codigo);

                if (esCodigoNumerico) {
                    Swal.fire({
                        title: "Producto no encontrado",
                        text: `No existe un producto con el código de barras ${codigo}.`,
                        icon: "warning",
                        confirmButtonText: "Aceptar",
                    });

                    busquedaProducto.value = "";
                    productosFiltrados.value = [];
                }
            },

            onFinish: () => {
                buscandoProducto.value = false;
            },
        },
    );
};

/*
|--------------------------------------------------------------------------
| LOTES DISPONIBLES
|--------------------------------------------------------------------------
*/

const lotesDisponibles = computed(() => {
    if (!productoSeleccionado.value) {
        return [];
    }

    if (!Array.isArray(productoSeleccionado.value.lotes)) {
        return [];
    }

    return productoSeleccionado.value.lotes.filter(
        (lote) => Number(lote.cantidad_actual ?? 0) > 0,
    );
});

/*
|--------------------------------------------------------------------------
| COSTO UNITARIO
|--------------------------------------------------------------------------
*/

const costoUnitario = computed(() => {
    if (!loteSeleccionado.value) {
        return 0;
    }

    return Number(
        loteSeleccionado.value.costo_unitario ??
            loteSeleccionado.value.detalle_entrada?.precio_compra ??
            0,
    );
});

/*
|--------------------------------------------------------------------------
| PRECIO DE VENTA
|--------------------------------------------------------------------------
*/

const precioUnitario = computed(() => {
    if (!loteSeleccionado.value) {
        return 0;
    }

    return Number(
        loteSeleccionado.value.precio_venta ??
            loteSeleccionado.value.detalle_entrada?.precio_venta ??
            0,
    );
});

/*
|--------------------------------------------------------------------------
| STOCK DISPONIBLE
|--------------------------------------------------------------------------
*/

const stockDisponible = computed(() => {
    if (!loteSeleccionado.value) {
        return 0;
    }

    return Number(loteSeleccionado.value.cantidad_actual ?? 0);
});

/*
|--------------------------------------------------------------------------
| SUBTOTAL
|--------------------------------------------------------------------------
*/

const subtotal = computed(() => {
    return Number(cantidad.value || 0) * Number(costoUnitario.value || 0);
});

/*
|--------------------------------------------------------------------------
| AGREGAR DETALLE
|--------------------------------------------------------------------------
|
| AQUÍ recién el producto pasa a form.detalles.
|--------------------------------------------------------------------------
*/

const agregarDetalle = () => {
    /*
     * Producto
     */
    if (!productoSeleccionado.value) return;
    /*
     * Lote
     */
    if (!loteSeleccionado.value) {
        Swal.fire({
            title: "Sin lote disponible",
            text: "El producto no tiene lotes con stock disponible.",
            icon: "warning",
            confirmButtonText: "Aceptar",
        });

        return;
    }

    /*
     * Cantidad
     */
    if (cantidad.value < 1) {
        Swal.fire({
            title: "Cantidad inválida",
            text: "La cantidad debe ser mayor a cero.",
            icon: "warning",
            confirmButtonText: "Aceptar",
        });
        return;
    }

    if (cantidad.value > stockDisponible.value) {
        Swal.fire({
            title: "Stock insuficiente",
            text: `Solo hay ${stockDisponible.value} unidades disponibles.`,
            icon: "warning",
            confirmButtonText: "Aceptar",
        });
        return;
    }
    /*
     * Buscar si ya existe el mismo producto
     * en el mismo lote.
     */
    const detalleExistente = form.detalles.find(
        (detalle) =>
            detalle.producto_id === productoSeleccionado.value.id &&
            detalle.lote_id === loteSeleccionado.value.id,
    );

    /*
     * Si ya existe, sumar cantidad.
     */
    if (detalleExistente) {
        const nuevaCantidad =
            Number(detalleExistente.cantidad) + Number(cantidad.value);

        if (nuevaCantidad > stockDisponible.value) {
            Swal.fire({
                title: "Stock insuficiente",
                text: `No puedes superar las ${stockDisponible.value} unidades disponibles de este lote.`,
                icon: "warning",
                confirmButtonText: "Aceptar",
            });

            return;
        }

        detalleExistente.cantidad = nuevaCantidad;

        detalleExistente.subtotal =
            nuevaCantidad * Number(detalleExistente.costo_unitario);
    } else {
        /*
         * Nuevo detalle.
         */
        form.detalles.push({
            producto_id: productoSeleccionado.value.id,

            lote_id: loteSeleccionado.value.id,

            cantidad: Number(cantidad.value),

            costo_unitario: Number(costoUnitario.value),

            precio_unitario: Number(precioUnitario.value),

            subtotal: Number(subtotal.value),

            producto: productoSeleccionado.value,

            lote: loteSeleccionado.value,
        });
    }

    /*
     * Recalcular total.
     */
    calcularTotal();

    /*
     * Limpiar producto temporal.
     *
     * Esto permite buscar/escanear otro.
     */
    productoSeleccionado.value = null;

    loteSeleccionado.value = null;

    cantidad.value = 1;

    busquedaProducto.value = "";
};

/*
|--------------------------------------------------------------------------
| ELIMINAR DETALLE
|--------------------------------------------------------------------------
*/

const eliminarDetalle = (index) => {
    form.detalles.splice(index, 1);

    calcularTotal();
};

//Actualizar cantidad
const actualizarCantidad = (detalle) => {
    const cantidadNueva = Number(detalle.cantidad || 0);

    const stock = Number(detalle.lote?.cantidad_actual ?? 0);

    if (cantidadNueva < 1) {
        detalle.cantidad = 1;
    } else if (cantidadNueva > stock) {
        detalle.cantidad = stock;

        Swal.fire({
            title: "Stock insuficiente",
            text: `Solo hay ${stock} unidades disponibles de este lote.`,
            icon: "warning",
            confirmButtonText: "Aceptar",
        });
    } else {
        detalle.cantidad = cantidadNueva;
    }

    detalle.subtotal =
        Number(detalle.cantidad) * Number(detalle.costo_unitario);

    calcularTotal();
};

/*
|--------------------------------------------------------------------------
| CALCULAR TOTAL
|--------------------------------------------------------------------------
*/

const calcularTotal = () => {
    form.total = form.detalles.reduce((total, detalle) => {
        return total + Number(detalle.subtotal || 0);
    }, 0);
};

/*
|--------------------------------------------------------------------------
| WATCH PRODUCTO
|--------------------------------------------------------------------------
*/

watch(
    () => productoSeleccionado.value,
    () => {
        cantidad.value = 1;
    },
);

/*
|--------------------------------------------------------------------------
| WATCH LOTE
|--------------------------------------------------------------------------
*/

watch(
    () => loteSeleccionado.value,
    () => {
        cantidad.value = 1;
    },
);

/*
|--------------------------------------------------------------------------
| MODO CONSULTA
|--------------------------------------------------------------------------
*/

const esConsulta = computed(() => {
    return props.salida !== null;
});

const esVenta = computed(() => {
    return props.salida?.tipo_salida === "venta";
});

/*
|--------------------------------------------------------------------------
| TÍTULO
|--------------------------------------------------------------------------
*/

const titulo = computed(() => {
    if (esConsulta.value) {
        return esVenta.value ? "Detalle de venta" : "Detalle de salida";
    }

    return "Registrar salida";
});

/*
|--------------------------------------------------------------------------
| SUBTÍTULO
|--------------------------------------------------------------------------
*/

const subtitulo = computed(() => {
    if (esConsulta.value) {
        return "Consulta los productos asociados a esta salida.";
    }

    return "Registra una salida de inventario diferente a una venta.";
});

/*
|--------------------------------------------------------------------------
| CARGAR / RESETEAR
|--------------------------------------------------------------------------
*/

watch(
    () => props.salida,

    (salida) => {
        if (salida) {
            form.tipo_salida = salida.tipo_salida ?? null;

            form.fecha_salida = salida.fecha_salida
                ? new Date(salida.fecha_salida)
                : new Date();

            form.observacion = salida.observacion ?? "";

            form.total = Number(salida.total ?? 0);

            form.detalles = Array.isArray(salida.detalles)
                ? salida.detalles.map((detalle) => ({
                      producto_id: detalle.producto_id,

                      lote_id: detalle.lote_id,

                      cantidad: Number(detalle.cantidad ?? 0),

                      costo_unitario: Number(detalle.costo_unitario ?? 0),

                      precio_unitario: Number(detalle.precio_unitario ?? 0),

                      subtotal: Number(detalle.subtotal ?? 0),

                      producto: detalle.producto ?? null,

                      lote: detalle.lote ?? null,
                  }))
                : [];
        } else {
            form.reset();

            form.tipo_salida = null;

            form.fecha_salida = new Date();

            form.observacion = "";

            form.total = 0;

            form.detalles = [];
        }

        productoSeleccionado.value = null;

        loteSeleccionado.value = null;

        cantidad.value = 1;

        busquedaProducto.value = "";

        productosFiltrados.value = [];

        form.clearErrors();
    },

    {
        immediate: true,
    },
);

/*
|--------------------------------------------------------------------------
| GUARDAR
|--------------------------------------------------------------------------
*/

const guardar = () => {
    if (!form.tipo_salida) {
        Swal.fire({
            title: "Tipo de salida requerido",
            text: "Selecciona el tipo de salida.",
            icon: "warning",
            confirmButtonText: "Aceptar",
        });

        return;
    }

    if (form.detalles.length === 0) {
        Swal.fire({
            title: "Sin productos",
            text: "Debes agregar al menos un producto a la salida.",
            icon: "warning",
            confirmButtonText: "Aceptar",
        });

        return;
    }

    /*
     * Recalcular subtotales.
     */
    form.detalles.forEach((detalle) => {
        detalle.subtotal =
            Number(detalle.cantidad || 0) * Number(detalle.costo_unitario || 0);
    });

    calcularTotal();

    form.post(route("salidas.store"), {
        preserveScroll: true,

        onSuccess: async () => {
            emit("saved", "guardado");

            await Swal.fire({
                title: "¡Salida registrada!",
                text: "La salida de inventario fue registrada correctamente.",
                icon: "success",
                confirmButtonText: "Aceptar",
            });

            form.reset();

            form.tipo_salida = null;

            form.fecha_salida = new Date();

            form.observacion = "";

            form.total = 0;

            form.detalles = [];

            productoSeleccionado.value = null;

            loteSeleccionado.value = null;

            cantidad.value = 1;

            busquedaProducto.value = "";

            productosFiltrados.value = [];
        },
    });
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

/*
|--------------------------------------------------------------------------
| FORMATO MONEDA
|--------------------------------------------------------------------------
*/

const formatoMoneda = (valor) => {
    return Number(valor || 0).toLocaleString("es-PE", {
        style: "currency",
        currency: "PEN",
    });
};

/*
|--------------------------------------------------------------------------
| NOMBRE TIPO SALIDA
|--------------------------------------------------------------------------
*/

const nombreTipoSalida = (tipo) => {
    switch (tipo) {
        case "venta":
            return "Venta";

        case "consumo":
            return "Consumo interno";

        case "merma":
            return "Merma / Daño";

        case "devolucion":
            return "Devolución";

        default:
            return tipo ?? "Sin tipo";
    }
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
            <!-- INFORMACIÓN DE LA SALIDA -->
            <!-- ========================================================= -->

            <div>
                <h3 class="text-base font-semibold mb-4">
                    Información de la salida
                </h3>

                <div class="grid grid-cols-2 gap-x-5 gap-y-5">
                    <!-- ================================================= -->
                    <!-- TIPO DE SALIDA -->
                    <!-- ================================================= -->

                    <div class="flex flex-col gap-1">
                        <FloatLabel variant="on">
                            <Select
                                id="tipo_salida"
                                v-model="form.tipo_salida"
                                :options="tiposSalida"
                                optionLabel="label"
                                optionValue="value"
                                class="w-full"
                                :disabled="esConsulta"
                                :invalid="!!form.errors.tipo_salida"
                            />

                            <label for="tipo_salida">
                                Tipo de salida

                                <span v-if="!esConsulta" class="text-red-500">
                                    *
                                </span>
                            </label>
                        </FloatLabel>

                        <small
                            v-if="form.errors.tipo_salida"
                            class="text-red-500"
                        >
                            {{ form.errors.tipo_salida }}
                        </small>
                    </div>

                    <!-- ================================================= -->
                    <!-- FECHA DE SALIDA -->
                    <!-- ================================================= -->

                    <div class="flex flex-col gap-1">
                        <FloatLabel variant="on">
                            <InputText
                                id="fecha_salida"
                                :modelValue="
                                    form.fecha_salida
                                        ? new Date(
                                              form.fecha_salida,
                                          ).toLocaleDateString('es-PE')
                                        : ''
                                "
                                class="w-full"
                                disabled
                            />

                            <label for="fecha_salida"> Fecha de salida </label>
                        </FloatLabel>
                    </div>

                    <!-- ================================================= -->
                    <!-- INFORMACIÓN DEL TIPO -->
                    <!-- ================================================= -->

                    <div v-if="esConsulta" class="col-span-2">
                        <div class="flex items-center gap-3">
                            <span class="text-sm text-gray-500">
                                Tipo registrado:
                            </span>

                            <span class="font-medium">
                                {{ nombreTipoSalida(form.tipo_salida) }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <Divider />

            <!-- ========================================================= -->
            <!-- REGISTRAR PRODUCTOS -->
            <!-- ========================================================= -->

            <div v-if="!esConsulta">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="text-base font-semibold mb-1">
                            Productos a retirar
                        </h3>

                        <p class="text-sm text-gray-500 mt-1">
                            Busca el producto, selecciona su lote y especifica
                            la cantidad.
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

                <!-- ================================================= -->
                <!-- AUTOCOMPLETE PRODUCTO -->
                <!-- ================================================= -->

                <FloatLabel variant="on">
                    <AutoComplete
                        id="producto"
                        v-model="busquedaProducto"
                        :suggestions="productosFiltrados"
                        optionLabel="nombre"
                        class="w-full"
                        dropdown
                        forceSelection
                        :disabled="esConsulta"
                        autofocus
                        @complete="buscarProductos"
                        @item-select="seleccionarDesdeAutocomplete"
                        @keydown.enter.prevent="escanearCodigoBarras"
                    >
                        <template #option="{ option }">
                            <div class="flex flex-col py-1">
                                <span class="font-medium">
                                    {{ option.nombre }}
                                </span>

                                <small class="text-gray-500">
                                    Código:
                                    {{ option.codigo || "—" }}
                                    | Barras:
                                    {{ option.codigo_barras || "—" }}
                                </small>

                                <small class="text-gray-500">
                                    Stock:
                                    {{ option.stock_actual ?? 0 }}
                                </small>
                            </div>
                        </template>
                    </AutoComplete>

                    <label for="producto"> Buscar o escanear producto </label>
                </FloatLabel>

                <small class="text-gray-500">
                    Puedes buscar por nombre, código o escanear el código de
                    barras.
                </small>
                <!-- ================================================= -->
                <!-- LOTE Y CANTIDAD -->
                <!-- APARECEN DEBAJO DEL PRODUCTO -->
                <!-- ================================================= -->

                <div
                    v-if="productoSeleccionado"
                    class="grid grid-cols-2 gap-5 mt-5"
                >
                    <!-- ================================================= -->
                    <!-- LOTE -->
                    <!-- ================================================= -->

                    <div class="flex flex-col gap-1">
                        <FloatLabel variant="on">
                            <Select
                                id="lote"
                                v-model="loteSeleccionado"
                                :options="lotesDisponibles"
                                optionLabel="numero_lote"
                                class="w-full"
                                :disabled="
                                    !productoSeleccionado ||
                                    esConsulta ||
                                    lotesDisponibles.length === 0
                                "
                                :showClear="true"
                                emptyMessage="No hay lotes disponibles"
                            >
                                <template #option="slotProps">
                                    <div
                                        class="flex items-center justify-between w-full gap-4"
                                    >
                                        <div class="flex flex-col">
                                            <span class="font-medium">
                                                {{
                                                    slotProps.option
                                                        .numero_lote ??
                                                    slotProps.option
                                                        .codigo_lote ??
                                                    `Lote #${slotProps.option.id}`
                                                }}
                                            </span>

                                            <span
                                                class="text-xs text-gray-500 mt-1"
                                            >
                                                Vencimiento:

                                                {{
                                                    slotProps.option
                                                        .fecha_vencimiento ??
                                                    "Sin vencimiento"
                                                }}
                                            </span>
                                        </div>

                                        <span
                                            class="text-xs font-medium text-gray-600 whitespace-nowrap"
                                        >
                                            Stock:
                                            {{
                                                slotProps.option
                                                    .cantidad_actual ?? 0
                                            }}
                                        </span>
                                    </div>
                                </template>
                            </Select>

                            <label for="lote"> Lote </label>
                        </FloatLabel>

                        <small
                            v-if="
                                productoSeleccionado &&
                                lotesDisponibles.length === 0
                            "
                            class="text-red-500"
                        >
                            Este producto no tiene lotes con stock disponible.
                        </small>
                    </div>

                    <!-- ================================================= -->
                    <!-- CANTIDAD -->
                    <!-- ================================================= -->

                    <div class="flex flex-col gap-1">
                        <FloatLabel variant="on">
                            <InputNumber
                                id="cantidad"
                                v-model="cantidad"
                                class="w-full"
                                :min="1"
                                :max="
                                    loteSeleccionado
                                        ? Number(
                                              loteSeleccionado.cantidad_actual,
                                          )
                                        : undefined
                                "
                                :disabled="!loteSeleccionado || esConsulta"
                                :showButtons="true"
                                :useGrouping="false"
                            />

                            <label for="cantidad"> Cantidad </label>
                        </FloatLabel>

                        <small v-if="loteSeleccionado" class="text-gray-500">
                            Stock disponible:
                            <strong>
                                {{ stockDisponible }}
                            </strong>
                            unidades
                        </small>
                    </div>
                </div>

                <!-- ================================================= -->
                <!-- INFORMACIÓN DEL LOTE -->
                <!-- ================================================= -->

                <div
                    v-if="loteSeleccionado"
                    class="grid grid-cols-3 gap-5 mt-5 p-4 border border-gray-200 rounded-lg bg-gray-50"
                >
                    <!-- STOCK -->

                    <div>
                        <small class="text-gray-500 block mb-1">
                            Stock disponible
                        </small>

                        <span class="text-lg font-semibold">
                            {{ stockDisponible }}
                            unidades
                        </span>
                    </div>

                    <!-- COSTO -->

                    <div>
                        <small class="text-gray-500 block mb-1">
                            Costo unitario
                        </small>

                        <span class="text-lg font-semibold">
                            {{ formatoMoneda(costoUnitario) }}
                        </span>
                    </div>

                    <!-- SUBTOTAL -->

                    <div>
                        <small class="text-gray-500 block mb-1">
                            Subtotal
                        </small>

                        <span class="text-lg font-semibold">
                            {{ formatoMoneda(subtotal) }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- ========================================================= -->
            <!-- DETALLE DE PRODUCTOS -->
            <!-- ========================================================= -->

            <div>
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="text-base font-semibold mb-1">
                            Detalle de productos
                        </h3>

                        <p class="text-sm text-gray-500">
                            {{
                                esConsulta
                                    ? "Productos asociados a esta salida."
                                    : "Productos que serán descontados del inventario."
                            }}
                        </p>
                    </div>
                </div>

                <!-- ================================================= -->
                <!-- SIN PRODUCTOS -->
                <!-- ================================================= -->

                <div
                    v-if="form.detalles.length === 0"
                    class="border border-dashed border-gray-300 rounded-lg py-8 text-center"
                >
                    <i
                        class="pi pi-inbox text-3xl text-gray-400 mb-3 block"
                    ></i>

                    <p class="text-gray-500 m-0">No hay productos agregados.</p>

                    <small v-if="!esConsulta" class="text-gray-400">
                        Selecciona un producto para agregarlo a la salida.
                    </small>
                </div>

                <!-- ================================================= -->
                <!-- TABLA -->
                <!-- ================================================= -->

                <div
                    v-else
                    class="border border-gray-200 rounded-lg overflow-x-auto"
                >
                    <table class="w-full min-w-[900px]">
                        <thead class="bg-gray-50">
                            <tr>
                                <th
                                    class="text-left px-4 py-3 text-sm font-semibold"
                                >
                                    Producto
                                </th>

                                <th
                                    class="text-left px-4 py-3 text-sm font-semibold"
                                    style="width: 150px"
                                >
                                    Lote
                                </th>

                                <th
                                    class="text-right px-4 py-3 text-sm font-semibold"
                                    style="width: 170px"
                                >
                                    Costo unitario
                                </th>

                                <th
                                    class="text-right px-4 py-3 text-sm font-semibold"
                                    style="width: 160px"
                                >
                                    Subtotal
                                </th>

                                <th
                                    class="text-center px-4 py-3 text-sm font-semibold"
                                    style="width: 130px"
                                >
                                    Cantidad
                                </th>

                                <th
                                    v-if="!esConsulta"
                                    class="text-center px-4 py-3"
                                    style="width: 70px"
                                >
                                    Acción
                                </th>
                            </tr>
                        </thead>

                        <tbody>
                            <tr
                                v-for="(detalle, index) in form.detalles"
                                :key="`${detalle.producto_id}-${detalle.lote_id}-${index}`"
                                class="border-t border-gray-100"
                            >
                                <!-- PRODUCTO -->

                                <td class="px-4 py-3">
                                    <div class="font-medium">
                                        {{
                                            detalle.producto?.nombre ??
                                            productosDisponibles.find(
                                                (p) =>
                                                    Number(p.id) ===
                                                    Number(detalle.producto_id),
                                            )?.nombre ??
                                            "Producto"
                                        }}
                                    </div>

                                    <small class="text-gray-500">
                                        Código:
                                        {{
                                            detalle.producto?.codigo ??
                                            productosDisponibles.find(
                                                (p) =>
                                                    Number(p.id) ===
                                                    Number(detalle.producto_id),
                                            )?.codigo ??
                                            "---"
                                        }}
                                    </small>
                                </td>

                                <!-- LOTE -->

                                <td class="px-4 py-3">
                                    {{
                                        detalle.lote?.numero_lote ??
                                        detalle.lote?.codigo_lote ??
                                        detalle.lote_id ??
                                        "---"
                                    }}
                                </td>

                                <!-- COSTO -->

                                <td class="px-4 py-3 text-right">
                                    {{ formatoMoneda(detalle.costo_unitario) }}
                                </td>

                                <!-- SUBTOTAL -->

                                <td class="px-4 py-3 text-right font-semibold">
                                    {{ formatoMoneda(detalle.subtotal) }}
                                </td>

                                <!-- CANTIDAD -->

                                <td class="px-4 py-3 text-center">
                                    <InputNumber
                                        v-model="detalle.cantidad"
                                        :min="1"
                                        :max="
                                            Number(
                                                detalle.lote?.cantidad_actual ??
                                                    0,
                                            )
                                        "
                                        :disabled="esConsulta"
                                        :useGrouping="false"
                                        :showButtons="true"
                                        inputClass="w-20 text-center"
                                        @update:modelValue="
                                            actualizarCantidad(detalle)
                                        "
                                    />
                                </td>

                                <!-- ELIMINAR -->

                                <td
                                    v-if="!esConsulta"
                                    class="px-4 py-3 text-center"
                                >
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
            </div>

            <Divider />

            <!-- ========================================================= -->
            <!-- OBSERVACIÓN -->
            <!-- ========================================================= -->

            <div class="flex flex-col gap-1">
                <FloatLabel variant="on">
                    <Textarea
                        id="observacion"
                        v-model="form.observacion"
                        rows="3"
                        class="w-full"
                        :disabled="esConsulta"
                        :invalid="!!form.errors.observacion"
                        autoResize
                    />

                    <label for="observacion"> Observación </label>
                </FloatLabel>

                <small v-if="form.errors.observacion" class="text-red-500">
                    {{ form.errors.observacion }}
                </small>
            </div>

            <!-- ========================================================= -->
            <!-- TOTAL -->
            <!-- ========================================================= -->

            <div class="flex justify-end border-t pt-4">
                <div
                    class="w-72 border border-gray-200 rounded-lg p-4 bg-gray-50"
                >
                    <div class="flex justify-between items-center">
                        <span class="font-semibold text-gray-600">
                            Costo total
                        </span>

                        <span class="text-xl font-bold">
                            {{ formatoMoneda(form.total) }}
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
                label="Cerrar"
                severity="secondary"
                variant="outlined"
                type="button"
                :disabled="form.processing"
                @click="cerrar"
            />

            <Button
                v-if="!esConsulta"
                label="Registrar salida"
                icon="pi pi-save"
                :loading="form.processing"
                :disabled="!form.tipo_salida || form.detalles.length === 0"
                type="button"
                @click="guardar"
            />
        </template>
    </Dialog>
</template>
