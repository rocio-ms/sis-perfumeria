<script setup>
import { ref, computed, nextTick } from "vue";
import { router } from "@inertiajs/vue3";
import AppLayout from "@/Layouts/AppLayout.vue";
import Card from "@/Components/Card.vue";
import PagoDialog from "./PagoDialog.vue";
import Swal from "sweetalert2";

defineOptions({
    layout: AppLayout,
});

const props = defineProps({
    sesionCaja: {
        type: Object,
        default: null,
    },
    productos: {
        type: Array,
        default: () => [],
    },
});

/*
|--------------------------------------------------------------------------
| BREADCRUMB
|--------------------------------------------------------------------------
*/

const home = ref({
    icon: "pi pi-home",
    command: () => {
        router.visit("/dashboard");
    },
});

const items = ref([
    {
        label: "Ventas",
    },
]);

/*
|--------------------------------------------------------------------------
| BÚSQUEDA
|--------------------------------------------------------------------------
*/

const busqueda = ref("");
const inputBusqueda = ref(null);

/*
|--------------------------------------------------------------------------
| CARRITO
|--------------------------------------------------------------------------
*/

const carrito = ref([]);
const mostrarPago = ref(false);
const procesandoVenta = ref(false);

/*
|--------------------------------------------------------------------------
| PRODUCTOS DISPONIBLES
|--------------------------------------------------------------------------
*/

const productosDisponibles = computed(() => {
    return props.productos.filter(
        (producto) => Number(producto.stock_actual ?? 0) > 0,
    );
});

/*
|--------------------------------------------------------------------------
| PRODUCTOS FILTRADOS
|--------------------------------------------------------------------------
|
| Mientras escribes en el buscador se filtran las cards.
| Pero NO se agrega automáticamente.
|
| Para agregar mediante búsqueda/escáner se utiliza ENTER.
|--------------------------------------------------------------------------
*/

const productosFiltrados = computed(() => {
    const texto = busqueda.value.trim().toLowerCase();

    if (!texto) {
        return productosDisponibles.value;
    }

    return productosDisponibles.value.filter((producto) => {
        const nombre = String(producto.nombre ?? "").toLowerCase();
        const codigo = String(producto.codigo ?? "").toLowerCase();
        const codigoBarras = String(producto.codigo_barras ?? "").toLowerCase();

        return (
            nombre.includes(texto) ||
            codigo.includes(texto) ||
            codigoBarras.includes(texto)
        );
    });
});

/*
|--------------------------------------------------------------------------
| BUSCAR Y AGREGAR PRODUCTO
|--------------------------------------------------------------------------
*/

const buscarYAgregarProducto = () => {
    const texto = busqueda.value.trim().toLowerCase();

    if (!texto) {
        return;
    }

    const productoExacto = productosDisponibles.value.find((producto) => {
        const nombre = String(producto.nombre ?? "").toLowerCase();
        const codigo = String(producto.codigo ?? "").toLowerCase();
        const codigoBarras = String(producto.codigo_barras ?? "").toLowerCase();

        return codigo === texto || codigoBarras === texto || nombre === texto;
    });

    const productoEncontrado =
        productoExacto ||
        productosDisponibles.value.find((producto) => {
            const nombre = String(producto.nombre ?? "").toLowerCase();
            const codigo = String(producto.codigo ?? "").toLowerCase();
            const codigoBarras = String(
                producto.codigo_barras ?? "",
            ).toLowerCase();

            return (
                nombre.includes(texto) ||
                codigo.includes(texto) ||
                codigoBarras.includes(texto)
            );
        });

    if (!productoEncontrado) {
        Swal.fire({
            icon: "warning",
            title: "Producto no encontrado",
            text: `No se encontró ningún producto relacionado con "${busqueda.value}".`,
            confirmButtonText: "Aceptar",
        });

        return;
    }

    agregarAlCarrito(productoEncontrado);

    limpiarBusqueda();
};

/*
|--------------------------------------------------------------------------
| LIMPIAR BÚSQUEDA
|--------------------------------------------------------------------------
*/

const limpiarBusqueda = () => {
    busqueda.value = "";

    nextTick(() => {
        inputBusqueda.value?.$el?.focus();
    });
};

/*
|--------------------------------------------------------------------------
| AGREGAR AL CARRITO
|--------------------------------------------------------------------------
*/

const agregarAlCarrito = (producto) => {
    const stock = Number(producto.stock_actual ?? 0);

    if (stock <= 0) {
        Swal.fire({
            icon: "warning",
            title: "Sin stock",
            text: `El producto "${producto.nombre}" no tiene stock disponible.`,
            confirmButtonText: "Aceptar",
        });

        return;
    }

    const existente = carrito.value.find(
        (item) => item.producto_id === producto.id,
    );

    if (existente) {
        if (existente.cantidad >= stock) {
            Swal.fire({
                icon: "warning",
                title: "Stock insuficiente",
                text: `Solo hay ${stock} unidades disponibles de "${producto.nombre}".`,
                confirmButtonText: "Aceptar",
            });

            return;
        }

        existente.cantidad++;

        calcularSubtotal(existente);

        return;
    }

    const precio = Number(producto.precio_venta ?? producto.precio ?? 0);

    const loteDisponible = producto.lotes?.find(
        (lote) => Number(lote.cantidad_actual ?? 0) > 0,
    );

    if (!loteDisponible) {
        Swal.fire({
            icon: "warning",
            title: "Sin stock disponible",
            text: `El producto "${producto.nombre}" no tiene ningún lote con stock disponible.`,
            confirmButtonText: "Aceptar",
        });

        return;
    }

    window.dispatchEvent(new CustomEvent("sidebar-collapse"));

    carrito.value.push({
        producto_id: producto.id,

        producto: producto,

        lote_id: loteDisponible.id,

        cantidad: 1,

        precio_unitario: precio,

        tiene_descuento: Boolean(producto.tiene_descuento),
        descuento_disponible: Number(producto.descuento ?? 0),

        aplicar_descuento: false,
        descuento: 0,

        subtotal: precio,
    });
};

/*
|--------------------------------------------------------------------------
| INCREMENTAR / DECREMENTAR CANTIDAD
|--------------------------------------------------------------------------
*/

const incrementarCantidad = (index) => {
    const item = carrito.value[index];

    const stock = Number(item.producto?.stock_actual ?? 0);

    if (item.cantidad >= stock) {
        Swal.fire({
            icon: "warning",
            title: "Stock máximo alcanzado",
            text: `Solo hay ${stock} unidades disponibles.`,
            confirmButtonText: "Aceptar",
        });

        return;
    }

    item.cantidad++;

    calcularSubtotal(item);
};

const decrementarCantidad = (index) => {
    const item = carrito.value[index];

    if (item.cantidad > 1) {
        item.cantidad--;

        calcularSubtotal(item);

        return;
    }

    eliminarDelCarrito(index);
};

/*
|--------------------------------------------------------------------------
| CALCULAR SUBTOTAL
|--------------------------------------------------------------------------
*/

const calcularSubtotal = (item) => {
    const cantidad = Number(item.cantidad || 0);
    const precio = Number(item.precio_unitario || 0);

    const descuento = item.aplicar_descuento ? Number(item.descuento || 0) : 0;

    const precioFinal = Math.max(precio - descuento, 0);

    item.subtotal = cantidad * precioFinal;
};

//Aplicar o quitar descuento
const alternarDescuento = (item) => {
    if (!item.tiene_descuento) {
        return;
    }

    item.aplicar_descuento = !item.aplicar_descuento;

    if (item.aplicar_descuento) {
        item.descuento = Number(item.descuento_disponible ?? 0);
    } else {
        item.descuento = 0;
    }

    calcularSubtotal(item);
};

/*
|--------------------------------------------------------------------------
| ELIMINAR / VACIAR CARRITO
|--------------------------------------------------------------------------
*/

const eliminarDelCarrito = (index) => {
    carrito.value.splice(index, 1);

    nextTick(() => {
        inputBusqueda.value?.$el?.focus();
    });
};

const vaciarCarrito = async () => {
    if (carrito.value.length === 0) {
        return;
    }

    const resultado = await Swal.fire({
        icon: "question",
        title: "¿Vaciar carrito?",
        text: "Se eliminarán todos los productos de la venta.",
        showCancelButton: true,
        confirmButtonText: "Sí, vaciar",
        cancelButtonText: "Cancelar",
        reverseButtons: true,
    });

    if (resultado.isConfirmed) {
        carrito.value = [];

        limpiarBusqueda();
    }
};

/*
|--------------------------------------------------------------------------
| TOTALES
|--------------------------------------------------------------------------
*/

const totalVenta = computed(() => {
    return carrito.value.reduce((total, item) => {
        return total + Number(item.subtotal || 0);
    }, 0);
});


const cantidadProductos = computed(() => {
    return carrito.value.reduce((total, item) => {
        return total + Number(item.cantidad || 0);
    }, 0);
});

const cantidadItemsCarrito = computed(() => {
    return carrito.value.length;
});

/*
|--------------------------------------------------------------------------
| FORMATEAR MONEDA
|--------------------------------------------------------------------------
*/

const formatearMoneda = (valor) => {
    return new Intl.NumberFormat("es-PE", {
        style: "currency",
        currency: "PEN",
    }).format(Number(valor || 0));
};

/*
|--------------------------------------------------------------------------
| PROCESAR VENTA
|--------------------------------------------------------------------------
|
| Recibe el payload que emite PagoDialog (cliente, comprobante,
| método de pago, monto recibido, etc.) y registra la venta.
|
| OJO: ajusta la ruta 'ventas.store' y el shape del payload al
| contrato real de tu backend si es distinto.
|--------------------------------------------------------------------------
*/

const tiposPago = {
    efectivo: 1,
    tarjeta: 2,
    yape_plin: 3,
    transferencia: 4,
};

const procesarVenta = (datosPago) => {
    if (!props.sesionCaja?.id) {
        Swal.fire({
            icon: "warning",
            title: "Caja no disponible",
            text: "No tienes una sesión de caja abierta.",
        });

        return;
    }

    procesandoVenta.value = true;

    const clienteId = datosPago.cliente?.id ?? null;

    router.post(
        route("ventas.store"),
        {
            cliente_id: clienteId,

            sesion_caja_id: props.sesionCaja.id,

            tipo_comprobante_id: datosPago.comprobante === "Factura" ? 2 : 1,

            fecha_emision: new Date()
                .toISOString()
                .slice(0, 19)
                .replace("T", " "),

            moneda: "soles",

            forma_pago: "contado",

            tipo_pago_id: tiposPago[datosPago.metodo_pago],

            detalles: carrito.value.map((item) => ({
                producto_id: item.producto_id,

                lote_id: item.lote_id,

                cantidad: item.cantidad,

                unidad_medida: item.unidad_medida ?? "NIU",

                precio_unitario: item.precio_unitario,

                descuento: item.aplicar_descuento
                    ? Number(item.descuento ?? 0)
                    : 0,

                descripcion: item.producto?.nombre ?? item.nombre ?? "",
            })),
        },
        {
            preserveScroll: true,

            onSuccess: () => {
                mostrarPago.value = false;

                carrito.value = [];

                limpiarBusqueda();

                Swal.fire({
                    icon: "success",
                    title: "¡Venta registrada!",
                    text: "La venta se registró correctamente.",
                    confirmButtonText: "Aceptar",
                });
            },

            onError: (errors) => {
                console.error("Errores al registrar venta:", errors);

                Swal.fire({
                    icon: "error",
                    title: "No se pudo registrar la venta",
                    text:
                        Object.values(errors)[0] ??
                        "Revisa los datos e inténtalo nuevamente.",
                    confirmButtonText: "Aceptar",
                });
            },

            onFinish: () => {
                procesandoVenta.value = false;
            },
        },
    );
};
</script>

<template>
    <v-container fluid>
        <Card>
            <!-- HEADER -->
            <template #title>
                <div class="flex mb-3">
                    <Breadcrumb :home="home" :model="items" />
                </div>

                <div
                    class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-4"
                >
                    <div>
                        <h1 class="text-2xl font-bold text-gray-800">
                            Registrar venta
                        </h1>

                        <p class="text-sm text-gray-500 mt-1">
                            Selecciona un producto o escanea su código de barras
                            para comenzar la venta.
                        </p>
                    </div>

                    <!-- INDICADOR DE PRODUCTOS -->
                    <div
                        class="flex items-center gap-2 px-3 py-2 rounded-lg bg-[#FDF2F5] text-[#7F1D3F] border border-[#F5DCE4]"
                    >
                        <i class="pi pi-box text-[#7F1D3F]"></i>

                        <span class="text-sm font-medium">
                            {{ productosDisponibles.length }} productos
                        </span>
                    </div>
                </div>
            </template>

            <!-- CONTENIDO -->
            <template #content>
                <div
                    class="grid grid-cols-1 gap-5"
                    :class="
                        carrito.length > 0
                            ? 'lg:grid-cols-[minmax(0,3fr)_minmax(420px,2fr)]'
                            : 'lg:grid-cols-1'
                    "
                >
                    <!-- CATÁLOGO -->
                    <div
                        class="border border-[#F5DCE4] rounded-2xl bg-white overflow-hidden"
                    >
                        <!-- HEADER CATÁLOGO -->
                        <div class="px-5 pt-5 pb-4 border-b border-[#F9E8ED]">
                            <div
                                class="flex flex-col md:flex-row md:items-center md:justify-between gap-4"
                            >
                                <div>
                                    <div class="flex items-center gap-2">
                                        <div
                                            class="w-9 h-9 rounded-lg bg-[#FDF2F5] flex items-center justify-center"
                                        >
                                            <i
                                                class="pi pi-shopping-bag text-[#7F1D3F]"
                                            ></i>
                                        </div>

                                        <div>
                                            <h2
                                                class="text-lg font-semibold text-gray-800"
                                            >
                                                Productos
                                            </h2>

                                            <p
                                                class="text-xs text-gray-500 mt-0.5"
                                            >
                                                Selecciona un producto para
                                                agregarlo a la venta
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <Tag
                                    :value="`${productosDisponibles.length} disponibles`"
                                    severity="info"
                                    class="!bg-[#FDF2F5] !text-[#7F1D3F] !border !border-[#F5DCE4]"
                                />
                            </div>

                            <!-- BUSCADOR -->
                            <div class="flex gap-2 mt-5">
                                <IconField class="w-full">
                                    <InputIcon class="pi pi-barcode" />

                                    <InputText
                                        ref="inputBusqueda"
                                        v-model="busqueda"
                                        class="w-full"
                                        placeholder="Escanea el código o busca un producto..."
                                        autofocus
                                        @keydown.enter.prevent="
                                            buscarYAgregarProducto
                                        "
                                    />

                                    <InputIcon
                                        v-if="busqueda"
                                        class="pi pi-times cursor-pointer hover:text-[#7F1D3F]"
                                        @click="limpiarBusqueda"
                                    />
                                </IconField>

                                <Button
                                    icon="pi pi-search"
                                    aria-label="Buscar"
                                    class="!bg-[#7F1D3F] !border-[#7F1D3F] hover:!bg-[#64152F] hover:!border-[#64152F]"
                                    @click="buscarYAgregarProducto"
                                />
                            </div>

                            <div
                                class="flex items-center gap-2 mt-3 text-xs text-gray-500"
                            >
                                <i class="pi pi-info-circle text-[#7F1D3F]"></i>

                                <span>
                                    Escanea el código de barras o escribe el
                                    nombre y presiona
                                    <strong class="text-[#7F1D3F]">
                                        Enter
                                    </strong>
                                </span>
                            </div>
                        </div>

                        <!-- CATÁLOGO DE PRODUCTOS -->
                        <div class="p-5">
                            <div
                                v-if="productosFiltrados.length"
                                class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-4"
                            >
                                <div
                                    v-for="producto in productosFiltrados"
                                    :key="producto.id"
                                    class="group relative border border-gray-200 rounded-xl p-3 bg-white cursor-pointer transition-all duration-200 hover:border-[#C77A96] hover:shadow-md hover:-translate-y-0.5"
                                    @click="agregarAlCarrito(producto)"
                                >
                                    <div
                                        class="relative w-full h-32 bg-gray-50 rounded-lg flex items-center justify-center overflow-hidden mb-3"
                                    >
                                        <img
                                            :src="
                                                producto.imagen_url
                                                    ? `/storage/${producto.imagen_url}`
                                                    : '/images/producto_default.svg'
                                            "
                                            :alt="producto.nombre"
                                            class="w-full h-full object-contain transition-transform duration-200 group-hover:scale-105"
                                        />

                                        <div
                                            v-if="
                                                Number(producto.stock_actual) <=
                                                Number(
                                                    producto.stock_minimo ?? 0,
                                                )
                                            "
                                            class="absolute top-2 right-2"
                                        >
                                            <Tag
                                                value="Stock bajo"
                                                severity="warn"
                                                class="!text-[10px]"
                                            />
                                        </div>
                                    </div>

                                    <div class="min-w-0">
                                        <h3
                                            class="font-semibold text-sm text-gray-800 truncate"
                                            :title="producto.nombre"
                                        >
                                            {{ producto.nombre }}
                                        </h3>

                                        <p
                                            class="text-xs text-gray-400 mt-1 truncate"
                                        >
                                            <i class="pi pi-hashtag mr-1"></i>
                                            {{ producto.codigo }}
                                        </p>
                                    </div>

                                    <div
                                        class="flex items-center justify-between mt-3"
                                    >
                                        <span
                                            class="text-xs"
                                            :class="
                                                Number(producto.stock_actual) <=
                                                Number(
                                                    producto.stock_minimo ?? 0,
                                                )
                                                    ? 'text-orange-500'
                                                    : 'text-gray-500'
                                            "
                                        >
                                            <i class="pi pi-box mr-1"></i>
                                            Stock:
                                            {{ producto.stock_actual }}
                                        </span>
                                    </div>

                                    <div
                                        class="flex items-center justify-between gap-2 mt-2"
                                    >
                                        <span
                                            class="font-bold text-[#7F1D3F] text-sm"
                                        >
                                            {{
                                                formatearMoneda(
                                                    producto.precio_venta ??
                                                        producto.precio,
                                                )
                                            }}
                                        </span>

                                        <Button
                                            icon="pi pi-plus"
                                            rounded
                                            size="small"
                                            aria-label="Agregar producto"
                                            class="!bg-[#7F1D3F] !border-[#7F1D3F] hover:!bg-[#64152F] !w-8 !h-8"
                                            @click.stop="
                                                agregarAlCarrito(producto)
                                            "
                                        />
                                    </div>
                                </div>
                            </div>

                            <!-- SIN RESULTADOS -->
                            <div
                                v-else-if="busqueda"
                                class="flex flex-col items-center justify-center py-16 text-center text-gray-400"
                            >
                                <div
                                    class="w-16 h-16 rounded-full bg-[#FDF2F5] flex items-center justify-center mb-4"
                                >
                                    <i
                                        class="pi pi-search text-2xl text-[#B85C7D]"
                                    ></i>
                                </div>

                                <p class="font-medium text-gray-600 m-0">
                                    Sin coincidencias para "{{ busqueda }}"
                                </p>

                                <small class="mt-1">
                                    Prueba con otro nombre o código.
                                </small>
                            </div>

                            <!-- SIN PRODUCTOS -->
                            <div
                                v-else
                                class="flex flex-col items-center justify-center py-16 text-center text-gray-400"
                            >
                                <div
                                    class="w-16 h-16 rounded-full bg-[#FDF2F5] flex items-center justify-center mb-4"
                                >
                                    <i
                                        class="pi pi-inbox text-2xl text-[#B85C7D]"
                                    ></i>
                                </div>

                                <p class="font-medium text-gray-600 m-0">
                                    No hay productos disponibles
                                </p>

                                <small class="mt-1">
                                    No existen productos con stock.
                                </small>
                            </div>
                        </div>
                    </div>

                    <!-- DETALLE DE VENTA -->
                    <div v-if="carrito.length > 0">
                        <div
                            class="border border-[#F5DCE4] rounded-2xl bg-white h-full overflow-hidden lg:sticky lg:top-4 flex flex-col"
                        >
                            <!-- HEADER -->
                            <div
                                class="px-5 py-4 border-b border-[#F9E8ED] flex items-center justify-between shrink-0"
                            >
                                <div class="flex items-center gap-3">
                                    <div
                                        class="w-10 h-10 rounded-xl bg-[#FDF2F5] flex items-center justify-center"
                                    >
                                        <i
                                            class="pi pi-shopping-cart text-[#7F1D3F]"
                                        ></i>
                                    </div>

                                    <div>
                                        <h2
                                            class="text-lg font-semibold text-gray-800"
                                        >
                                            Detalle de venta
                                        </h2>

                                        <p class="text-xs text-gray-500 mt-0.5">
                                            {{ cantidadItemsCarrito }}
                                            producto(s) ·
                                            {{ cantidadProductos }}
                                            unidad(es)
                                        </p>
                                    </div>
                                </div>

                                <Button
                                    icon="pi pi-trash"
                                    rounded
                                    text
                                    severity="danger"
                                    aria-label="Vaciar carrito"
                                    v-tooltip.top="'Vaciar venta'"
                                    @click="vaciarCarrito"
                                />
                            </div>

                            <!-- PRODUCTOS DEL CARRITO -->
                            <div class="p-5 pb-0">
                                <transition-group
                                    name="cart-row"
                                    tag="div"
                                    class="flex flex-col gap-3 max-h-[360px] overflow-y-auto pr-1"
                                >
                                    <div
                                        v-for="(item, index) in carrito"
                                        :key="item.producto_id"
                                        class="border border-[#F5DCE4] rounded-xl p-3 bg-[#FDF8FA] hover:bg-[#FDF2F5] transition"
                                    >
                                        <div class="flex justify-between gap-3">
                                            <div
                                                class="flex items-center gap-3 min-w-0"
                                            >
                                                <div
                                                    class="w-12 h-12 shrink-0 rounded-lg bg-white border border-[#F5DCE4] flex items-center justify-center overflow-hidden"
                                                >
                                                    <img
                                                        :src="
                                                            item.producto
                                                                ?.imagen_url
                                                                ? `/storage/${item.producto.imagen_url}`
                                                                : '/images/producto_default.svg'
                                                        "
                                                        :alt="
                                                            item.producto
                                                                ?.nombre
                                                        "
                                                        class="w-full h-full object-contain"
                                                    />
                                                </div>

                                                <div class="min-w-0">
                                                    <h3
                                                        class="font-semibold text-sm text-gray-800 truncate"
                                                        :title="
                                                            item.producto
                                                                ?.nombre
                                                        "
                                                    >
                                                        {{
                                                            item.producto
                                                                ?.nombre
                                                        }}
                                                    </h3>

                                                    <small
                                                        class="text-gray-500"
                                                    >
                                                        {{
                                                            formatearMoneda(
                                                                item.precio_unitario,
                                                            )
                                                        }}
                                                        c/u
                                                    </small>
                                                    <small
                                                        v-if="
                                                            item.tiene_descuento
                                                        "
                                                        class="text-green-600 font-medium mt-1"
                                                    >
                                                        <i
                                                            class="pi pi-tag mr-1"
                                                        ></i>

                                                        Descuento disponible:
                                                        {{
                                                            formatearMoneda(
                                                                item.descuento_disponible,
                                                            )
                                                        }}
                                                    </small>
                                                    <div
                                                        v-if="
                                                            item.tiene_descuento
                                                        "
                                                        class="mt-2"
                                                    >
                                                        <Button
                                                            :label="
                                                                item.aplicar_descuento
                                                                    ? 'Descuento aplicado'
                                                                    : 'Aplicar descuento'
                                                            "
                                                            :icon="
                                                                item.aplicar_descuento
                                                                    ? 'pi pi-check'
                                                                    : 'pi pi-tag'
                                                            "
                                                            :severity="
                                                                item.aplicar_descuento
                                                                    ? 'success'
                                                                    : 'secondary'
                                                            "
                                                            size="small"
                                                            outlined
                                                            class="w-full"
                                                            @click="
                                                                alternarDescuento(
                                                                    item,
                                                                )
                                                            "
                                                        />
                                                    </div>
                                                </div>
                                            </div>

                                            <Button
                                                icon="pi pi-times"
                                                rounded
                                                text
                                                severity="secondary"
                                                aria-label="Eliminar"
                                                class="!text-gray-400 hover:!text-red-500"
                                                @click="
                                                    eliminarDelCarrito(index)
                                                "
                                            />
                                        </div>

                                        <div
                                            class="flex justify-between items-center mt-3"
                                        >
                                            <div
                                                class="flex items-center border border-[#F5DCE4] rounded-lg bg-white overflow-hidden"
                                            >
                                                <Button
                                                    icon="pi pi-minus"
                                                    text
                                                    size="small"
                                                    class="!text-[#7F1D3F] hover:!bg-[#FDF2F5] !rounded-none"
                                                    @click="
                                                        decrementarCantidad(
                                                            index,
                                                        )
                                                    "
                                                />

                                                <span
                                                    class="font-semibold text-sm text-gray-700 min-w-[35px] text-center"
                                                >
                                                    {{ item.cantidad }}
                                                </span>

                                                <Button
                                                    icon="pi pi-plus"
                                                    text
                                                    size="small"
                                                    class="!text-[#7F1D3F] hover:!bg-[#FDF2F5] !rounded-none"
                                                    @click="
                                                        incrementarCantidad(
                                                            index,
                                                        )
                                                    "
                                                />
                                            </div>

                                            <span
                                                class="font-bold text-[#7F1D3F]"
                                            >
                                                {{
                                                    formatearMoneda(
                                                        item.subtotal,
                                                    )
                                                }}
                                            </span>
                                        </div>
                                    </div>
                                </transition-group>
                            </div>

                            <!-- RESUMEN -->
                            <div
                                class="p-5 pt-4 mt-2 border-t border-[#F5DCE4] shrink-0"
                            >
                                <!-- SUBTOTAL -->
                                <div
                                    class="flex justify-between text-sm text-gray-500 mb-2"
                                >
                                    <span>Subtotal</span>
                                    <span>
                                        {{ formatearMoneda(totalVenta) }}
                                    </span>
                                </div>


                                <!-- TOTAL -->
                                <div
                                    class="rounded-xl bg-[#FDF2F5] border border-[#F5DCE4] px-4 py-4"
                                >
                                    <div
                                        class="flex justify-between items-center"
                                    >
                                        <span
                                            class="font-semibold text-gray-700"
                                        >
                                            Total a pagar
                                        </span>

                                        <span
                                            class="text-2xl font-bold text-[#7F1D3F]"
                                        >
                                            {{ formatearMoneda(totalVenta) }}
                                        </span>
                                    </div>
                                </div>

                                <Button
                                    label="Continuar con el pago"
                                    icon="pi pi-credit-card"
                                    size="large"
                                    class="w-full mt-4 !bg-[#7F1D3F] !border-[#7F1D3F] hover:!bg-[#64152F] hover:!border-[#64152F]"
                                    @click="mostrarPago = true"
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </Card>

        <PagoDialog
            v-model:visible="mostrarPago"
            :total="totalVenta"
            :carrito="carrito"
            :procesando="procesandoVenta"
            @confirmar="procesarVenta"
        />
    </v-container>
</template>

<style scoped>
.cart-row-enter-active,
.cart-row-leave-active {
    transition: all 0.2s ease;
}
.cart-row-enter-from,
.cart-row-leave-to {
    opacity: 0;
    transform: translateX(8px);
}
.cart-row-leave-active {
    position: absolute;
}
</style>
