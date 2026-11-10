<script setup>
import {
    ref,
    computed,
    nextTick,
    watch,
    onBeforeUnmount,
    onMounted,
} from "vue";
import { usePage, router } from "@inertiajs/vue3";
import AppLayout from "@/Layouts/AppLayout.vue";
import Card from "@/Components/Card.vue";
import PagoDialog from "./PagoDialog.vue";
import ComprobanteTicket from "./ComprobanteTicket.vue";
import Swal from "sweetalert2";

defineOptions({
    layout: AppLayout,
});

const page = usePage();

const props = defineProps({
    sesionCaja: {
        type: Object,
        default: null,
    },
    productos: {
        type: Array,
        default: () => [],
    },
    categorias: {
        type: Array,
        default: () => [],
    },
    cajaAbierta: {
        type: Boolean,
        default: false,
    },
    comprobantesEmitidos: {
        type: Array,
        default: () => [],
    },
    tienda: {
        type: Object,
        default: () => ({}),
    },
});

/*
|--------------------------------------------------------------------------
| BÚSQUEDA
|--------------------------------------------------------------------------
*/

const busqueda = ref("");
const categoriaSeleccionada = ref(null);
const categoriaScroller = ref(null);
const puedeDesplazarCategoriasIzquierda = ref(false);
const puedeDesplazarCategoriasDerecha = ref(false);
const inputBusqueda = ref(null);
const productosBuscados = ref(props.productos);
const buscandoProductos = ref(false);
const errorBusqueda = ref("");

let temporizadorBusqueda = null;
let controladorBusqueda = null;
let busquedaPendiente = 0;
let observadorCategorias = null;

/*
|--------------------------------------------------------------------------
| CARRITO
|--------------------------------------------------------------------------
*/

const carrito = ref([]);
const mostrarPago = ref(false);
const procesandoVenta = ref(false);
const mostrarComprobantesEmitidos = ref(false);
const comprobantesEmitidos = ref([]);
const comprobanteSeleccionadoParaImprimir = ref(null);

/*
|--------------------------------------------------------------------------
| PRODUCTOS DISPONIBLES
|--------------------------------------------------------------------------
*/

const productosDisponibles = computed(() => {
    return productosBuscados.value.filter(
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
    if (categoriaSeleccionada.value === null) {
        return productosDisponibles.value;
    }

    return productosDisponibles.value.filter(
        (producto) =>
            Number(producto.categoria_id) ===
            Number(categoriaSeleccionada.value),
    );
});

const actualizarDesplazamientoCategorias = () => {
    const scroller = categoriaScroller.value;

    if (!scroller) return;

    puedeDesplazarCategoriasIzquierda.value = scroller.scrollLeft > 1;
    puedeDesplazarCategoriasDerecha.value =
        scroller.scrollLeft + scroller.clientWidth < scroller.scrollWidth - 1;
};

const desplazarCategorias = (direccion) => {
    const scroller = categoriaScroller.value;

    if (!scroller) return;

    scroller.scrollBy({
        left: direccion * Math.max(scroller.clientWidth * 0.7, 160),
        behavior: "smooth",
    });
};

const solicitarProductos = async (texto = "", categoriaId = null) => {
    controladorBusqueda?.abort();

    const controlador = new AbortController();
    controladorBusqueda = controlador;
    const solicitud = ++busquedaPendiente;
    buscandoProductos.value = true;
    errorBusqueda.value = "";

    try {
        const response = await fetch(
            route("ventas.productos.buscar", {
                search: texto,
                categoria_id: categoriaId,
            }),
            {
                headers: { Accept: "application/json" },
                signal: controlador.signal,
            },
        );

        const data = await response.json();

        if (!response.ok) {
            throw new Error(data.message ?? "No se pudieron buscar productos.");
        }

        if (solicitud !== busquedaPendiente) return [];

        productosBuscados.value = Array.isArray(data.productos)
            ? data.productos
            : [];

        return productosBuscados.value;
    } catch (error) {
        if (error.name !== "AbortError" && solicitud === busquedaPendiente) {
            errorBusqueda.value =
                "No se pudo consultar el catálogo. Intenta nuevamente.";
            productosBuscados.value = [];
        }

        return [];
    } finally {
        if (solicitud === busquedaPendiente) {
            buscandoProductos.value = false;
        }
    }
};

watch(busqueda, (valor) => {
    clearTimeout(temporizadorBusqueda);

    const termino = valor.trim();
    const solicitud = ++busquedaPendiente;

    controladorBusqueda?.abort();

    temporizadorBusqueda = setTimeout(
        () => {
            if (solicitud === busquedaPendiente) {
                solicitarProductos(
                    termino,
                    categoriaSeleccionada.value,
                );
            }
        },
        termino ? 180 : 0,
    );
});

watch(categoriaSeleccionada, (categoriaId) => {
    clearTimeout(temporizadorBusqueda);
    solicitarProductos(busqueda.value.trim(), categoriaId);
});

onBeforeUnmount(() => {
    clearTimeout(temporizadorBusqueda);
    controladorBusqueda?.abort();
    window.removeEventListener("resize", actualizarDesplazamientoCategorias);
    observadorCategorias?.disconnect();
});

/*
|--------------------------------------------------------------------------
| BUSCAR Y AGREGAR PRODUCTO
|--------------------------------------------------------------------------
*/

const buscarYAgregarProducto = async () => {
    const texto = busqueda.value.trim();
    const textoNormalizado = texto.toLowerCase();

    if (!texto) {
        return;
    }

    clearTimeout(temporizadorBusqueda);

    const resultados = await solicitarProductos(texto);
    const productoExacto = resultados.find((producto) => {
        const nombre = String(producto.nombre ?? "").toLowerCase();
        const codigo = String(producto.codigo ?? "").toLowerCase();
        const codigoBarras = String(producto.codigo_barras ?? "").toLowerCase();

        return (
            codigo === textoNormalizado ||
            codigoBarras === textoNormalizado ||
            nombre === textoNormalizado
        );
    });

    if (!productoExacto) {
        if (resultados.length > 0 || errorBusqueda.value) return;

        Swal.fire({
            icon: "warning",
            title: "Producto no encontrado",
            text: `No se encontró ningún producto relacionado con "${busqueda.value}".`,
            confirmButtonText: "Aceptar",
        });

        return;
    }

    agregarAlCarrito(productoExacto);

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
        inputBusqueda.value?.$el?.focus({ preventScroll: true });
    });
};

const recargarCatalogo = async () => {
    clearTimeout(temporizadorBusqueda);
    controladorBusqueda?.abort();
    busquedaPendiente++;
    busqueda.value = "";

    await nextTick();

    clearTimeout(temporizadorBusqueda);
    await solicitarProductos("", categoriaSeleccionada.value);

    inputBusqueda.value?.$el?.focus();
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

const cerrarDialogoComprobantes = () => {
    mostrarComprobantesEmitidos.value = false;
    comprobantesEmitidos.value = [];
};

const imprimirComprobantesEmitidos = async (comprobanteId = null) => {
    comprobanteSeleccionadoParaImprimir.value = comprobanteId;
    await nextTick();

    const limpiarModoImpresion = () => {
        document.body.classList.remove("printing-emitted-receipts");
        document.body.classList.remove("printing-single-receipt");
        comprobanteSeleccionadoParaImprimir.value = null;
    };

    document.body.classList.add("printing-emitted-receipts");
    if (comprobanteId !== null) {
        document.body.classList.add("printing-single-receipt");
    }
    window.addEventListener("afterprint", limpiarModoImpresion, {
        once: true,
    });
    window.print();
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

            cliente: datosPago.cliente,

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

            onSuccess: async (page) => {
                mostrarPago.value = false;

                carrito.value = [];

                comprobantesEmitidos.value = Array.isArray(
                    page.props.comprobantesEmitidos,
                )
                    ? page.props.comprobantesEmitidos
                    : [];

                mostrarComprobantesEmitidos.value =
                    comprobantesEmitidos.value.length > 0;

                await recargarCatalogo();
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

onMounted(() => {
    nextTick(() => {
        actualizarDesplazamientoCategorias();

        if (typeof ResizeObserver === "undefined" || !categoriaScroller.value) {
            return;
        }

        observadorCategorias = new ResizeObserver(
            actualizarDesplazamientoCategorias,
        );
        observadorCategorias.observe(categoriaScroller.value);
        observadorCategorias.observe(categoriaScroller.value.firstElementChild);
    });
    window.addEventListener("resize", actualizarDesplazamientoCategorias);

    if (!props.cajaAbierta) {
        Swal.fire({
            icon: "warning",
            title: "Caja no abierta",
            text: "Debes abrir una caja antes de realizar una venta.",
            confirmButtonText: "Ir a abrir caja",
            allowOutsideClick: false,
            allowEscapeKey: false,
        }).then((result) => {
            if (result.isConfirmed) {
                router.visit(route("sesiones-caja.index"));
            }
        });
    }
});
</script>

<template>
    <v-container fluid class="ventas-page pa-2">
        <Card>
            <!-- HEADER -->
            <template #title>
                <div
                    class="flex flex-col md:flex-row justify-between items-start md:items-center gap-2 mb-2"
                >
                    <div class="min-w-0 w-full">
                        <div class="category-filter">
                            <Button
                                icon="pi pi-chevron-left"
                                rounded
                                outlined
                                size="small"
                                class="category-scroll-button"
                                aria-label="Desplazar categorías a la izquierda"
                                :disabled="!puedeDesplazarCategoriasIzquierda"
                                @click="desplazarCategorias(-1)"
                            />

                            <div
                                ref="categoriaScroller"
                                class="category-scroller"
                                @scroll="actualizarDesplazamientoCategorias"
                            >
                                <div
                                    class="category-list"
                                    role="group"
                                    aria-label="Filtrar productos por categoría"
                                >
                                    <Button
                                        label="Todas"
                                        size="small"
                                        :outlined="
                                            categoriaSeleccionada !== null
                                        "
                                        :class="
                                            categoriaSeleccionada === null
                                                ? '!bg-[#7F1D3F] !border-[#7F1D3F] !text-white'
                                                : '!border-[#E8C8D2] !text-gray-700'
                                        "
                                        :aria-pressed="
                                            categoriaSeleccionada === null
                                        "
                                        @click="categoriaSeleccionada = null"
                                    />
                                    <Button
                                        v-for="categoria in categorias"
                                        :key="categoria.id"
                                        :label="categoria.nombre"
                                        :badge="String(categoria.stock_total ?? 0)"
                                        :aria-label="`${categoria.nombre}, stock disponible: ${categoria.stock_total ?? 0}`"
                                        size="small"
                                        :outlined="
                                            Number(categoriaSeleccionada) !==
                                            Number(categoria.id)
                                        "
                                        :class="
                                            Number(categoriaSeleccionada) ===
                                            Number(categoria.id)
                                                ? '!bg-[#7F1D3F] !border-[#7F1D3F] !text-white'
                                                : '!border-[#E8C8D2] !text-gray-700'
                                        "
                                        :aria-pressed="
                                            Number(categoriaSeleccionada) ===
                                            Number(categoria.id)
                                        "
                                        @click="
                                            categoriaSeleccionada = categoria.id
                                        "
                                    />
                                </div>
                            </div>

                            <Button
                                icon="pi pi-chevron-right"
                                rounded
                                outlined
                                size="small"
                                class="category-scroll-button"
                                aria-label="Desplazar categorías a la derecha"
                                :disabled="!puedeDesplazarCategoriasDerecha"
                                @click="desplazarCategorias(1)"
                            />
                        </div>
                        <h1 class="text-2xl font-bold text-gray-800 my-2">
                            Registrar venta
                        </h1>

                        <p class="text-sm text-gray-500 mt-1">
                            Selecciona un producto o escanea su código de barras
                            para comenzar la venta.
                        </p>
                    </div>

                </div>
            </template>

            <!-- CONTENIDO -->
            <template #content>
                <div
                    class="grid grid-cols-1 gap-3"
                    :class="
                        carrito.length > 0
                            ? 'lg:grid-cols-[minmax(0,3fr)_minmax(420px,2fr)]'
                            : 'lg:grid-cols-1'
                    "
                >
                    <!-- CATÁLOGO -->
                    <div
                        class="border border-[#F5DCE4] rounded-2xl bg-white overflow-clip"
                    >
                        <!-- HEADER CATÁLOGO -->
                        <div class="px-4 pt-3">
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
                                                Busca productos para agregarlos
                                                a la venta
                                            </p>
                                        </div>
                                    </div>
                                </div>

                            </div>

                        </div>

                        <!-- BUSCADOR -->
                        <div
                            class="venta-buscador border-b border-[#F9E8ED] bg-white px-4 py-3"
                            role="search"
                            aria-label="Buscar productos para la venta"
                        >
                            <div class="flex gap-2">
                                <IconField class="w-full">
                                    <InputIcon
                                        :class="
                                            buscandoProductos
                                                ? 'pi pi-spin pi-spinner'
                                                : 'pi pi-barcode'
                                        "
                                    />

                                    <InputText
                                        ref="inputBusqueda"
                                        v-model="busqueda"
                                        class="w-full"
                                        aria-label="Escanea o busca un producto"
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
                                class="flex items-center gap-2 mt-2 text-xs text-gray-500"
                            >
                                <i class="pi pi-info-circle text-[#7F1D3F]"></i>

                                <span>
                                    Escanea un código para agregarlo o busca por
                                    nombre y selecciona un resultado. Presiona
                                    <strong class="text-[#7F1D3F]">
                                        Enter
                                    </strong>
                                    para agregar una coincidencia exacta.
                                </span>
                            </div>
                        </div>

                        <!-- CATÁLOGO DE PRODUCTOS -->
                        <div class="p-3">
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
                                                producto.imagen_url ||
                                                '/images/producto_default.svg'
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
                                            class="block max-w-full whitespace-normal break-words [overflow-wrap:anywhere] font-semibold text-sm leading-snug text-gray-800"
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
                                v-else-if="errorBusqueda"
                                class="flex flex-col items-center justify-center py-16 text-center text-gray-400"
                            >
                                <p class="font-medium text-gray-600 m-0">
                                    {{ errorBusqueda }}
                                </p>
                                <Button
                                    label="Reintentar"
                                    icon="pi pi-refresh"
                                    severity="secondary"
                                    outlined
                                    class="mt-3"
                                    @click="recargarCatalogo"
                                />
                            </div>

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
                                    {{ `Sin coincidencias para "${busqueda}"` }}
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
                    <div v-if="carrito.length > 0" class="venta-carrito">
                        <div
                            class="venta-carrito-panel border border-[#F5DCE4] rounded-2xl bg-white flex flex-col"
                        >
                            <!-- HEADER -->
                            <div
                                class="px-4 py-3 border-b border-[#F9E8ED] flex items-center justify-between shrink-0"
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
                            <div class="venta-carrito-productos p-3 pb-0">
                                <transition-group
                                    name="cart-row"
                                    tag="div"
                                    class="relative flex flex-col gap-3 pr-1"
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
                                                            item.producto?.imagen_url ||
                                                            '/images/producto_default.svg'
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
                                                        class="block max-w-full whitespace-normal break-words [overflow-wrap:anywhere] font-semibold text-sm leading-snug text-gray-800"
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
                                class="venta-cobro border-t border-[#F5DCE4] bg-white shrink-0"
                            >
                                <!-- SUBTOTAL -->
                                <div
                                    class="venta-subtotal justify-between text-sm text-gray-500 mb-2"
                                >
                                    <span>Subtotal</span>
                                    <span>
                                        {{ formatearMoneda(totalVenta) }}
                                    </span>
                                </div>

                                <!-- TOTAL -->
                                <div
                                    class="venta-total"
                                >
                                    <div
                                        class="venta-total-linea"
                                    >
                                        <span
                                            class="font-semibold text-gray-700"
                                        >
                                            Total a pagar
                                        </span>

                                        <span
                                            class="venta-total-monto font-bold text-[#7F1D3F]"
                                            aria-live="polite"
                                        >
                                            {{ formatearMoneda(totalVenta) }}
                                        </span>
                                    </div>
                                </div>

                                <Button
                                    label="Continuar con el pago"
                                    icon="pi pi-credit-card"
                                    size="large"
                                    class="venta-cobro-boton !bg-[#7F1D3F] !border-[#7F1D3F] hover:!bg-[#64152F] hover:!border-[#64152F] max-sm:!px-3 max-sm:!text-sm"
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

        <Dialog
            v-model:visible="mostrarComprobantesEmitidos"
            modal
            :header="`Comprobantes emitidos (${comprobantesEmitidos.length})`"
            :style="{ width: '900px', maxHeight: '90vh' }"
            :breakpoints="{ '1199px': '80vw', '575px': '95vw' }"
            @hide="comprobantesEmitidos = []"
        >
            <p class="mb-4 text-sm text-gray-600">
                Se emitieron {{ comprobantesEmitidos.length }}
                comprobante{{ comprobantesEmitidos.length === 1 ? '' : 's' }}.
            </p>

            <div class="emitted-receipts-print-area">
                <div
                    v-for="comprobante in comprobantesEmitidos"
                    :key="comprobante.id"
                    class="emitted-receipt-item"
                    :class="{
                        'emitted-receipt-item--selected':
                            comprobante.id ===
                            comprobanteSeleccionadoParaImprimir,
                    }"
                >
                    <div class="emitted-receipt-actions no-print">
                        <Button
                            label="Imprimir comprobante"
                            icon="pi pi-print"
                            size="small"
                            @click="imprimirComprobantesEmitidos(comprobante.id)"
                        />
                    </div>
                    <ComprobanteTicket
                        :venta="comprobante"
                        :tienda="props.tienda"
                    />
                </div>
            </div>

            <template #footer>
                <Button
                    label="Cerrar"
                    severity="secondary"
                    outlined
                    @click="cerrarDialogoComprobantes"
                />
                <Button
                    label="Imprimir todos"
                    icon="pi pi-print"
                    @click="imprimirComprobantesEmitidos"
                />
            </template>
        </Dialog>
    </v-container>
</template>

<style scoped>
.ventas-page {
    --venta-sticky-top: 73px;
}

.venta-buscador {
    position: sticky;
    top: var(--venta-sticky-top);
    z-index: 5;
}

.venta-carrito {
    margin-bottom: calc(5.5rem + env(safe-area-inset-bottom, 0px));
}

.venta-carrito-productos {
    min-height: 0;
    max-height: 360px;
    overflow-y: auto;
}

.venta-cobro {
    position: fixed;
    inset-inline-start: var(--v-layout-left, 0px);
    inset-inline-end: var(--v-layout-right, 0px);
    bottom: 0;
    z-index: 6;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 0.75rem 1rem calc(0.75rem + env(safe-area-inset-bottom, 0px));
}

.venta-subtotal {
    display: none;
}

.venta-total-linea {
    display: flex;
    flex-direction: column;
}

.venta-total-monto {
    font-size: 1.125rem;
}

.venta-cobro-boton {
    flex-shrink: 0;
    white-space: nowrap;
}

@media (min-width: 1024px) and (min-height: 420px) {
    .venta-carrito {
        position: sticky;
        top: var(--venta-sticky-top);
        align-self: start;
        margin-bottom: 0;
    }

    .venta-carrito-panel {
        max-height: calc(100dvh - var(--venta-sticky-top) - 0.5rem);
    }

    .venta-cobro {
        position: static;
        display: block;
        margin-top: 0.5rem;
        padding: 0.75rem;
    }

    .venta-subtotal {
        display: flex;
    }

    .venta-total {
        border: 1px solid #f5dce4;
        border-radius: 0.75rem;
        background: #fdf2f5;
        padding: 1rem;
    }

    .venta-total-linea {
        flex-direction: row;
        align-items: center;
        justify-content: space-between;
    }

    .venta-total-monto {
        font-size: 1.5rem;
    }

    .venta-cobro-boton {
        width: 100%;
        margin-top: 1rem;
    }
}

.category-filter {
    display: flex;
    align-items: center;
    gap: 8px;
    width: 100%;
    max-width: none;
    min-width: 0;
}

.category-scroll-button {
    flex: 0 0 auto;
}

.category-scroller {
    flex: 1 1 0;
    min-width: 0;
    overflow-x: auto;
    overflow-y: hidden;
    overscroll-behavior-x: contain;
    scrollbar-width: none;
    scroll-behavior: smooth;
}

.category-scroller::-webkit-scrollbar {
    display: none;
}

.category-list {
    display: flex;
    width: max-content;
    min-width: 100%;
    align-items: center;
    gap: 8px;
    padding: 2px 1px 8px;
}

.category-list :deep(.p-button) {
    flex: 0 0 auto;
    white-space: nowrap;
}

.ventas-page :deep(.p-card-body) {
    padding: 10px;
}

.ventas-page :deep(.p-card-title) {
    margin-bottom: 0;
}

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

.emitted-receipts-print-area {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    align-items: center;
    max-height: none !important;
    overflow: visible !important;
}

.emitted-receipt-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
}

@media print {
    :global(body.printing-emitted-receipts #app) {
        display: none !important;
    }

    :global(body.printing-emitted-receipts .emitted-receipts-print-area),
    :global(body.printing-emitted-receipts .emitted-receipts-print-area *) {
        visibility: visible !important;
    }

    :global(body.printing-emitted-receipts .emitted-receipts-print-area) {
        position: absolute !important;
        top: 0 !important;
        left: 0 !important;
        display: block;
        width: 80mm;
        max-height: none;
        overflow: visible;
    }

    :global(body.printing-emitted-receipts .emitted-receipt-actions) {
        display: none !important;
    }

    :global(body.printing-single-receipt .emitted-receipt-item--selected) {
        display: flex !important;
    }

    :global(body.printing-single-receipt .emitted-receipt-item:not(.emitted-receipt-item--selected)) {
        display: none !important;
    }

    :global(body.printing-emitted-receipts .p-dialog-mask) {
        position: static !important;
        display: block !important;
        overflow: visible !important;
        background: transparent !important;
    }

    :global(body.printing-emitted-receipts .p-dialog) {
        position: static !important;
        width: 80mm !important;
        max-height: none !important;
        margin: 0 !important;
        box-shadow: none !important;
        transform: none !important;
    }

    :global(body.printing-emitted-receipts .p-dialog-header),
    :global(body.printing-emitted-receipts .p-dialog-footer),
    :global(body.printing-emitted-receipts .no-print) {
        display: none !important;
    }

    :global(body.printing-emitted-receipts .p-dialog-content) {
        display: block !important;
        width: 80mm !important;
        margin: 0 !important;
        padding: 0 !important;
        max-height: none !important;
        overflow: visible !important;
    }

    :global(body.printing-emitted-receipts .emitted-receipt-item > .ticket-page) {
        margin: 0 !important;
        break-inside: avoid;
        page-break-inside: avoid;
    }

    :global(html),
    :global(body.printing-emitted-receipts) {
        margin: 0 !important;
        padding: 0 !important;
        width: 80mm !important;
    }

    @page {
        size: 80mm 200mm;
        margin: 0;
    }
}
</style>
