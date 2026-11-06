<script setup>
import { computed, ref, watch } from "vue";
import { router, useForm } from "@inertiajs/vue3";
import axios from "axios";

import Swal from "sweetalert2";

import ImportacionExcel from "@/Components/ImportacionExcel.vue";
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
    subcategorias: {
        type: Array,
        default: () => [],
    },
    ubicaciones: {
        type: Array,
        default: () => [],
    },
    empresas: {
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
const codigoEscaner = ref("");
const escaneandoProducto = ref(false);
const codigoBarrasProductoPendiente = ref("");
const importandoExcel = ref(false);

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

const porcentajesGanancia = [0, 10, 20, 30, 40, 50, 60, 70, 80, 90, 100].map(
    (porcentaje) => ({
        label: `${porcentaje}%`,
        value: porcentaje,
    }),
);

const porcentajePorDefecto = (producto) => {
    const categoria =
        producto?.categoria?.nombre ??
        props.categorias.find(
            (item) => Number(item.id) === Number(producto?.categoria_id),
        )?.nombre ??
        "";
    const categoriaNormalizada = String(categoria)
        .normalize("NFD")
        .replace(/[\u0300-\u036f]/g, "")
        .toLowerCase();

    return categoriaNormalizada.includes("portabilidad personal") ||
        categoriaNormalizada.includes("joya")
        ? 50
        : 30;
};

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
    } else if (codigoBarrasProductoPendiente.value) {
        try {
            const { data } = await axios.get(
                route("entradas.buscarPorCodigoBarras"),
                {
                    params: {
                        codigo_barras: codigoBarrasProductoPendiente.value,
                    },
                },
            );

            if (!data.producto) {
                throw new Error(
                    "El producto fue registrado, pero no se pudo recuperar para añadirlo a la entrada.",
                );
            }

            agregarProducto(data.producto);
            codigoBarrasProductoPendiente.value = "";

            await Swal.fire({
                title: "¡Producto agregado!",
                text: "El producto fue registrado y añadido a la entrada.",
                icon: "success",
                confirmButtonText: "Aceptar",
            });
        } catch (error) {
            codigoBarrasProductoPendiente.value = "";

            await Swal.fire({
                title: "Producto registrado",
                text:
                    error.response?.data?.message ||
                    error.message ||
                    "No se pudo añadir el producto a la entrada. Búsquelo por nombre para continuar.",
                icon: "warning",
                confirmButtonText: "Aceptar",
            });
        }

        return;
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

const limpiarFormulario = () => {
    form.reset();
    form.codigo_ingreso = props.codigoIngreso;
    form.tiene_documento = false;
    form.tipo_comprobante = null;
    form.numero_comprobante = "";
    form.fecha_ingreso = new Date();
    form.proveedor_id = null;
    form.observacion = "";
    form.detalles = [];
    form.clearErrors();

    tieneProveedor.value = false;
    productoBusqueda.value = null;
    productosFiltrados.value = [];
    codigoEscaner.value = "";
    codigoBarrasProductoPendiente.value = "";
};

watch(
    [() => props.entrada, () => props.codigoIngreso, visible],
    ([entrada, codigoIngreso, estaVisible]) => {
        if (!estaVisible) {
            if (!entrada) {
                limpiarFormulario();
            }
            return;
        }

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
                const porcentaje = porcentajePorDefecto(detalle.producto);

                const precioVenta =
                    detalle.precio_venta === null ||
                    detalle.precio_venta === undefined
                        ? Math.round(precioCompra * (1 + porcentaje / 100))
                        : Number(detalle.precio_venta);

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

                    porcentaje_ganancia: porcentaje,

                    tiene_descuento: Boolean(detalle.tiene_descuento),

                    descuento: Number(detalle.descuento) || 0,

                    subtotal: Number(detalle.subtotal) || 0,

                    tiene_vencimiento: !!fechaVencimiento,

                    fecha_vencimiento: fechaVencimiento
                        ? fechaLocal(fechaVencimiento)
                        : null,

                    codigo_barras_lote:
                        detalle.lotes?.[0]?.codigo_barras ?? "",
                };
            });
        } else {
            limpiarFormulario();
            form.codigo_ingreso = codigoIngreso;
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

const escanearProducto = async () => {
    const codigo = String(codigoEscaner.value ?? "").trim();

    if (!codigo || escaneandoProducto.value) {
        return;
    }

    escaneandoProducto.value = true;

    try {
        const { data } = await axios.get(
            route("entradas.buscarPorCodigoBarras"),
            {
                params: {
                    codigo_barras: codigo,
                },
            },
        );

        codigoEscaner.value = "";

        if (data.producto) {
            agregarProducto(data.producto);
            return;
        }

        const resultado = await Swal.fire({
            title: "Producto no encontrado",
            text: "Búsquelo por nombre o regístrelo como nuevo producto. Después podrá ingresar el código del lote en la entrada.",
            icon: "warning",
            showCancelButton: true,
            confirmButtonText: "Añadir nuevo producto",
            cancelButtonText: "Buscar por nombre",
            reverseButtons: true,
        });

        if (resultado.isConfirmed) {
            codigoBarrasProductoPendiente.value = codigo;
            mostrarProductoForm.value = true;
        } else if (resultado.dismiss === Swal.DismissReason.cancel) {
            document.getElementById("producto")?.focus();
        }
    } catch (error) {
        await Swal.fire({
            title: "No se pudo buscar el producto",
            text:
                error.response?.data?.message ||
                error.message ||
                "Ocurrió un error al consultar el código escaneado.",
            icon: "error",
            confirmButtonText: "Aceptar",
        });
    } finally {
        escaneandoProducto.value = false;
    }
};

const cancelarRegistroProducto = () => {
    codigoBarrasProductoPendiente.value = "";
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

        porcentaje_ganancia: porcentajePorDefecto(productoSeleccionado),

        tiene_descuento: false,

        descuento: null,

        subtotal: 0,

        tiene_vencimiento: false,

        fecha_vencimiento: null,

        codigo_barras_lote: "",

    });

    productoBusqueda.value = null;
};

const valorNumericoExcel = (valor) => {
    if (typeof valor === "number") {
        return Number.isFinite(valor) ? valor : null;
    }

    const texto = String(valor ?? "").trim();
    if (!texto) {
        return null;
    }

    const normalizado = /^\d+,\d+$/.test(texto)
        ? texto.replace(",", ".")
        : texto;
    const numero = Number(normalizado);

    return Number.isFinite(numero) ? numero : null;
};

const fechaExcel = (valor) => {
    if (valor instanceof Date && !Number.isNaN(valor.getTime())) {
        return new Date(
            valor.getFullYear(),
            valor.getMonth(),
            valor.getDate(),
        );
    }

    if (typeof valor === "number" && valor > 0) {
        const fecha = new Date(Date.UTC(1899, 11, 30) + valor * 86400000);
        return new Date(fecha.getUTCFullYear(), fecha.getUTCMonth(), fecha.getUTCDate());
    }

    const texto = String(valor ?? "").trim();
    if (!texto) {
        return null;
    }

    const partes = texto.match(/^(\d{1,2})[/-](\d{1,2})[/-](\d{4})$/);
    if (partes) {
        const fecha = new Date(Number(partes[3]), Number(partes[2]) - 1, Number(partes[1]));
        return Number.isNaN(fecha.getTime()) ? null : fecha;
    }

    const fecha = new Date(texto);
    return Number.isNaN(fecha.getTime()) ? null : fecha;
};

const importarDetallesExcel = async (filas) => {
    importandoExcel.value = true;

    try {
        const errores = [];
        const codigos = [];
        const filasValidas = [];

        filas.forEach((fila) => {
            const codigo = String(fila.codigo_producto ?? "").trim();
            const cantidad = valorNumericoExcel(fila.cantidad);
            const precioCompra = valorNumericoExcel(fila.precio_compra);
            const precioVenta = valorNumericoExcel(fila.precio_venta);
            const descuento = valorNumericoExcel(fila.descuento) ?? 0;
            const tieneDescuento = ["si", "sí", "true", "1", "yes"].includes(
                String(fila.tiene_descuento ?? "").trim().toLocaleLowerCase(),
            );
            const fechaVencimiento = fechaExcel(fila.fecha_vencimiento);

            if (!codigo || !Number.isInteger(cantidad) || cantidad < 1) {
                errores.push(`Fila ${fila.numero_fila}: código y cantidad entera mayor a cero son obligatorios.`);
                return;
            }

            if (
                precioCompra === null ||
                precioCompra < 0 ||
                precioVenta === null ||
                precioVenta < 0
            ) {
                errores.push(`Fila ${fila.numero_fila}: precio de compra y precio de venta deben ser números iguales o mayores a cero.`);
                return;
            }

            if (
                descuento < 0 ||
                (tieneDescuento && descuento > precioVenta)
            ) {
                errores.push(`Fila ${fila.numero_fila}: el descuento debe estar entre cero y el precio de venta.`);
                return;
            }

            if (fila.fecha_vencimiento && !fechaVencimiento) {
                errores.push(`Fila ${fila.numero_fila}: la fecha de vencimiento no es válida.`);
                return;
            }

            if (
                fechaVencimiento &&
                fechaVencimiento <= new Date(new Date().setHours(0, 0, 0, 0))
            ) {
                errores.push(`Fila ${fila.numero_fila}: la fecha de vencimiento debe ser posterior a hoy.`);
                return;
            }

            codigos.push(codigo);
            filasValidas.push({
                ...fila,
                codigo,
                cantidad,
                precio_compra: precioCompra,
                precio_venta: precioVenta,
                tiene_descuento: tieneDescuento,
                descuento: tieneDescuento ? descuento : 0,
                fecha_vencimiento: fechaVencimiento,
            });
        });

        if (errores.length) {
            await Swal.fire({
                title: "Revisa las filas del Excel",
                text: [
                    ...errores.slice(0, 12),
                    ...(errores.length > 12
                        ? [`Y ${errores.length - 12} errores más.`]
                        : []),
                ].join("\n"),
                icon: "warning",
                confirmButtonText: "Aceptar",
            });
            return;
        }

        const { data } = await axios.post(
            route("productos.resolver-importacion"),
            { codigos: [...new Set(codigos)] },
        );
        const productos = data.productos ?? [];
        const productoPorCodigo = new Map();
        const productoPorCodigoNumerico = new Map();

        productos.forEach((producto) => {
            if (producto.codigo) {
                const codigo = String(producto.codigo).trim();
                productoPorCodigo.set(codigo, producto);
                if (/^\d+$/.test(codigo)) {
                    productoPorCodigoNumerico.set(
                        codigo.replace(/^0+(?=\d)/, ""),
                        producto,
                    );
                }
            }
            if (producto.codigo_barras) {
                productoPorCodigo.set(String(producto.codigo_barras).trim(), producto);
            }
        });

        const noEncontrados = filasValidas
            .filter(
                (fila) =>
                    !productoPorCodigo.has(fila.codigo) &&
                    !productoPorCodigoNumerico.has(
                        fila.codigo.replace(/^0+(?=\d)/, ""),
                    ),
            )
            .map((fila) => `Fila ${fila.numero_fila}: no se encontró el producto "${fila.codigo}".`);

        if (noEncontrados.length) {
            await Swal.fire({
                title: "Hay productos que no existen",
                text: [
                    ...noEncontrados.slice(0, 12),
                    ...(noEncontrados.length > 12
                        ? [`Y ${noEncontrados.length - 12} productos más.`]
                        : []),
                ].join("\n"),
                icon: "warning",
                confirmButtonText: "Aceptar",
            });
            return;
        }

        filasValidas.forEach((fila) => {
            const producto =
                productoPorCodigo.get(fila.codigo) ??
                productoPorCodigoNumerico.get(
                    fila.codigo.replace(/^0+(?=\d)/, ""),
                );
            const subtotal = Math.round(fila.cantidad * fila.precio_compra * 100) / 100;

            form.detalles.push({
                producto_id: producto.id,
                producto,
                cantidad: fila.cantidad,
                precio_compra: fila.precio_compra,
                precio_venta: fila.precio_venta,
                porcentaje_ganancia: porcentajePorDefecto(producto),
                tiene_descuento: fila.tiene_descuento,
                descuento: fila.descuento,
                subtotal,
                tiene_vencimiento: Boolean(fila.fecha_vencimiento),
                fecha_vencimiento: fila.fecha_vencimiento,
                codigo_barras_lote: String(
                    fila.codigo_barras_lote ?? "",
                ).trim(),
            });
        });

        await Swal.fire({
            title: "Excel importado",
            text: `Se agregaron ${filasValidas.length} filas al formulario. Revisa los datos antes de guardar.`,
            icon: "success",
            confirmButtonText: "Aceptar",
        });
    } catch (error) {
        await Swal.fire({
            title: "No se pudo importar el Excel",
            text:
                error.response?.data?.message ||
                error.message ||
                "Ocurrió un error al buscar los productos.",
            icon: "error",
            confirmButtonText: "Aceptar",
        });
    } finally {
        importandoExcel.value = false;
    }
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

    const porcentaje = Number(
        detalle.porcentaje_ganancia ??
            porcentajePorDefecto(detalle.producto),
    );
    detalle.precio_venta = Math.round(
        precioCompra * (1 + porcentaje / 100),
    );

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
        if (detalle.precio_venta === null || detalle.precio_venta === undefined) {
            calcularPrecioVenta(detalle);
        } else {
            calcularSubtotal(detalle);
        }

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

                <ImportacionExcel
                    v-if="!esEdicion"
                    tipo="entrada"
                    class="mb-4"
                    :disabled="importandoExcel"
                    @imported="importarDetallesExcel"
                />

                <!-- BUSCADOR -->

                <div class="mb-3">
                    <div class="flex items-end gap-3">
                        <FloatLabel variant="on" class="w-full">
                            <InputText
                                id="codigo_producto_escaneado"
                                v-model="codigoEscaner"
                                class="w-full"
                                autocomplete="off"
                                placeholder="Escanee el código y presione Enter"
                                :disabled="escaneandoProducto"
                                @keydown.enter.prevent="escanearProducto"
                            />
                            <label for="codigo_producto_escaneado">
                                Escanear código de barras o QR
                            </label>
                        </FloatLabel>

                        <Button
                            type="button"
                            icon="pi pi-barcode"
                            label="Buscar código"
                            class="whitespace-nowrap flex-shrink-0"
                            :loading="escaneandoProducto"
                            :disabled="!codigoEscaner.trim()"
                            @click="escanearProducto"
                        />
                    </div>
                    <small class="mt-1 block text-gray-500">
                        Si no se encuentra, podrá buscarlo por nombre o
                        registrarlo como nuevo producto.
                    </small>
                </div>

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
                <div class="min-w-[1150px]">
                    <div
                        v-for="(detalle, index) in form.detalles"
                        :key="detalle.producto_id"
                        class="border-b border-gray-100 p-4 last:border-b-0"
                    >
                        <div
                            class="grid grid-cols-[minmax(0,1fr)_300px] items-center gap-6"
                        >
                            <div class="min-w-0">
                                <div class="font-medium text-gray-800">
                                    {{ detalle.producto?.nombre }}
                                </div>
                                <small class="text-gray-500">
                                    Código: {{ detalle.producto?.codigo }}
                                </small>
                            </div>

                            <div class="flex flex-col gap-1">
                                <label
                                    :for="`codigo_barras_lote_${index}`"
                                    class="text-sm font-medium text-gray-600"
                                >
                                    Código de barras del lote
                                </label>
                                <InputText
                                    :id="`codigo_barras_lote_${index}`"
                                    v-model="detalle.codigo_barras_lote"
                                    class="w-full"
                                    maxlength="50"
                                    placeholder="Opcional"
                                />
                            </div>
                        </div>

                        <div
                            class="mt-4 grid grid-cols-[110px_minmax(340px,1.7fr)_minmax(200px,1fr)_150px_minmax(220px,1.3fr)_44px] items-stretch gap-3 rounded-xl bg-gray-50 p-3"
                        >
                            <div
                                class="flex min-w-0 flex-col justify-center gap-2 rounded-lg border border-gray-200 bg-white p-3"
                            >
                                <label
                                    :for="`cantidad_${index}`"
                                    class="text-xs font-semibold uppercase tracking-wide text-gray-500"
                                >
                                    Cantidad
                                </label>
                                <InputNumber
                                    v-model="detalle.cantidad"
                                    :inputId="`cantidad_${index}`"
                                    class="w-full"
                                    inputClass="w-full"
                                    :min="1"
                                    :showButtons="true"
                                    @update:modelValue="
                                        calcularSubtotal(detalle)
                                    "
                                />
                            </div>

                            <div
                                class="flex min-w-0 flex-col justify-center gap-2 rounded-lg border border-gray-200 bg-white p-3"
                            >
                                <span
                                    class="text-xs font-semibold uppercase tracking-wide text-gray-500"
                                >
                                    Precios
                                </span>
                                <div class="grid grid-cols-2 gap-3">
                                    <div class="flex min-w-0 flex-col gap-1">
                                        <label
                                            :for="`precio_compra_${index}`"
                                            class="text-xs text-gray-500"
                                        >
                                            Compra
                                        </label>
                                        <InputNumber
                                            v-model="detalle.precio_compra"
                                            :inputId="`precio_compra_${index}`"
                                            class="w-full"
                                            inputClass="w-full"
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
                                    </div>
                                    <div class="flex min-w-0 flex-col gap-1">
                                        <label
                                            :for="`precio_venta_${index}`"
                                            class="text-xs text-gray-500"
                                        >
                                            Venta
                                        </label>
                                        <InputNumber
                                            v-model="detalle.precio_venta"
                                            :inputId="`precio_venta_${index}`"
                                            class="w-full"
                                            mode="currency"
                                            currency="PEN"
                                            locale="es-PE"
                                            :min="0"
                                            :minFractionDigits="2"
                                            :maxFractionDigits="2"
                                            inputClass="w-full"
                                        />
                                    </div>
                                </div>
                                <div class="mt-2 flex flex-col gap-1">
                                    <label
                                        :for="`porcentaje_ganancia_${index}`"
                                        class="text-xs text-gray-500"
                                    >
                                        Margen sobre compra
                                    </label>
                                    <Select
                                        v-model="detalle.porcentaje_ganancia"
                                        :inputId="`porcentaje_ganancia_${index}`"
                                        :options="porcentajesGanancia"
                                        optionLabel="label"
                                        optionValue="value"
                                        class="w-full"
                                        @update:modelValue="
                                            calcularPrecioVenta(detalle)
                                        "
                                    />
                                </div>
                            </div>

                            <div
                                class="flex min-w-0 flex-col justify-center gap-2 rounded-lg border border-gray-200 bg-white p-3"
                            >
                                <span
                                    class="text-xs font-semibold uppercase tracking-wide text-gray-500"
                                >
                                    Descuento
                                </span>
                                <div
                                    class="flex min-h-9 items-center gap-2"
                                >
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
                                        class="text-sm text-gray-600 cursor-pointer select-none"
                                    >
                                        Aplicar
                                    </label>
                                    <InputNumber
                                        v-else
                                        v-model="detalle.descuento"
                                        class="w-full"
                                        inputClass="w-full"
                                        mode="currency"
                                        currency="PEN"
                                        locale="es-PE"
                                        :min="0"
                                        :max="detalle.precio_venta ?? 0"
                                        :minFractionDigits="2"
                                        :maxFractionDigits="2"
                                    />
                                </div>
                            </div>

                            <div
                                class="flex flex-col justify-center gap-2 rounded-lg border border-blue-100 bg-blue-50 px-3 py-2"
                            >
                                <span
                                    class="text-xs font-semibold uppercase tracking-wide text-blue-700"
                                >
                                    Subtotal
                                </span>
                                <span
                                    class="text-right text-base font-semibold text-blue-900"
                                >
                                    {{
                                        Number(
                                            detalle.subtotal,
                                        ).toLocaleString("es-PE", {
                                            style: "currency",
                                            currency: "PEN",
                                        })
                                    }}
                                </span>
                            </div>

                            <div
                                class="flex flex-col justify-center gap-2 rounded-lg border border-gray-200 bg-white p-3"
                            >
                                <span
                                    class="text-xs font-semibold uppercase tracking-wide text-gray-500"
                                >
                                    Vencimiento
                                </span>
                                <div class="flex flex-col gap-2">
                                    <div class="flex items-center gap-2">
                                        <Checkbox
                                            v-model="detalle.tiene_vencimiento"
                                            :inputId="`vencimiento_${index}`"
                                            :binary="true"
                                            @update:modelValue="
                                                cambiarVencimiento(detalle)
                                            "
                                        />
                                        <label
                                            :for="`vencimiento_${index}`"
                                            class="text-sm text-gray-600 cursor-pointer select-none"
                                        >
                                            Tiene vencimiento
                                        </label>
                                    </div>
                                    <DatePicker
                                        v-if="detalle.tiene_vencimiento"
                                        v-model="detalle.fecha_vencimiento"
                                        view="month"
                                        dateFormat="mm/yy"
                                        showIcon
                                        class="w-full"
                                        :minDate="fechaMinima"
                                        placeholder="Mes/año"
                                    />
                                </div>
                            </div>

                            <div class="flex items-center justify-center">
                                <Button
                                    icon="pi pi-trash"
                                    severity="danger"
                                    text
                                    rounded
                                    type="button"
                                    aria-label="Eliminar producto"
                                    @click="eliminarDetalle(index)"
                                />
                            </div>
                        </div>
                    </div>
                </div>
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
            :subcategorias="props.subcategorias"
            :ubicaciones="props.ubicaciones"
            :empresas="props.empresas"
            :codigo-barras-inicial="codigoBarrasProductoPendiente"
            @saved="productoGuardado"
            @cancel="cancelarRegistroProducto"
        />
    </Dialog>
</template>
