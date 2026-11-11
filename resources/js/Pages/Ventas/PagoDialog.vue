<script setup>
import { ref, computed, onBeforeUnmount, watch } from "vue";

const props = defineProps({
    visible: {
        type: Boolean,
        default: false,
    },
    total: {
        type: Number,
        default: 0,
    },
    carrito: {
        type: Array,
        default: () => [],
    },
    procesando: {
        type: Boolean,
        default: false,
    },
    tiposPago: { type: Array, default: () => [] },
    tiposComprobante: { type: Object, default: () => ({}) },
});

const emit = defineEmits(["update:visible", "confirmar"]);

const dialogVisible = computed({
    get: () => props.visible,
    set: (value) => emit("update:visible", value),
});

/*
|--------------------------------------------------------------------------
| UTILIDADES
|--------------------------------------------------------------------------
| Redondeo a 2 decimales para evitar errores de coma flotante
| (ej: 36.900000000000006 vs 36.90 dejaba el botón deshabilitado).
*/
const redondear = (valor) =>
    Math.round((Number(valor || 0) + Number.EPSILON) * 100) / 100;

const totalRedondeado = computed(() => redondear(props.total));

// Convierte cualquier valor (null, número, string) a string sin espacios
const texto = (valor) => String(valor ?? "").trim();

/*
|--------------------------------------------------------------------------
| COMPROBANTE (Boleta / Factura)
|--------------------------------------------------------------------------
*/
const comprobante = ref("Boleta");

/*
|--------------------------------------------------------------------------
| CLIENTE
|--------------------------------------------------------------------------
*/
const esClienteVarios = ref(true);
const buscandoCliente = ref(false);
const mensajeCliente = ref("");
const clienteEncontrado = ref(false);
const clienteEncontradoEnAPI = ref(false);
let solicitudCliente = null;

// Último documento que se consultó (BD/API). Sirve para invalidar
// el id y los mensajes si el usuario modifica el número después.
const documentoConsultado = ref("");

const cliente = ref({
    id: null,
    tipo_documento: "DNI",
    numero_documento: "",
    nombre: "",
    razon_social_nombre: "",
    ap_paterno: "",
    ap_materno: "",
    nombres: "",
    direccion: "",
    contacto: "",
});

const largoDocumentoEsperado = computed(() => {
    return texto(cliente.value.tipo_documento).toUpperCase() === "RUC" ? 11 : 8;
});

const limpiarEstadoBusqueda = () => {
    solicitudCliente?.abort();
    solicitudCliente = null;
    buscandoCliente.value = false;
    clienteEncontrado.value = false;
    clienteEncontradoEnAPI.value = false;
    mensajeCliente.value = "";
    documentoConsultado.value = "";
};

onBeforeUnmount(limpiarEstadoBusqueda);

/**
 * Opción 1: cliente anónimo (checkbox marcado)
 */
const establecerClienteVarios = () => {
    esClienteVarios.value = true;

    cliente.value = {
        id: null,
        tipo_documento: "DNI",
        numero_documento: "00000000",
        nombre: "CLIENTES VARIOS",
        razon_social_nombre: "CLIENTES VARIOS",
        ap_paterno: "",
        ap_materno: "",
        nombres: "CLIENTES VARIOS",
        direccion: "",
        contacto: "",
    };

    limpiarEstadoBusqueda();
};

/**
 * Opciones 2 y 3: cliente identificado (API o manual)
 */
const activarClienteIdentificado = () => {
    esClienteVarios.value = false;

    cliente.value = {
        id: null,
        tipo_documento: comprobante.value === "Factura" ? "RUC" : "DNI",
        numero_documento: "",
        nombre: "",
        razon_social_nombre: "",
        ap_paterno: "",
        ap_materno: "",
        nombres: "",
        direccion: "",
        contacto: "",
    };

    limpiarEstadoBusqueda();
};

const alternarClienteVarios = (marcado) => {
    if (marcado) {
        establecerClienteVarios();
    } else {
        activarClienteIdentificado();
    }
};

/**
 * Cambio de tipo de documento (DNI <-> RUC) hecho por el usuario.
 * Se maneja aquí y no con un watch para que no se dispare
 * cuando el código cambia el tipo por dentro.
 */
const cambiarTipoDocumento = () => {
    cliente.value.id = null;
    cliente.value.numero_documento = "";
    cliente.value.nombre = "";
    cliente.value.razon_social_nombre = "";
    cliente.value.ap_paterno = "";
    cliente.value.ap_materno = "";
    cliente.value.nombres = "";
    cliente.value.direccion = "";
    cliente.value.contacto = "";

    limpiarEstadoBusqueda();
};

// Solo dígitos en el documento + invalidar la consulta anterior si cambia
watch(
    () => cliente.value.numero_documento,
    (valor) => {
        if (esClienteVarios.value) return;

        const soloDigitos = String(valor ?? "").replace(/\D/g, "");

        if (soloDigitos !== valor) {
            cliente.value.numero_documento = soloDigitos;
            return;
        }

        if (soloDigitos !== documentoConsultado.value) {
            cliente.value.id = null;
            cliente.value.contacto = "";
            if (clienteEncontrado.value || clienteEncontradoEnAPI.value) {
                cliente.value.nombre = "";
                cliente.value.razon_social_nombre = "";
                cliente.value.ap_paterno = "";
                cliente.value.ap_materno = "";
                cliente.value.nombres = "";
                cliente.value.direccion = "";
            }
            limpiarEstadoBusqueda();
        }
    },
);

/**
 * Opción 2: buscar en BD y luego en la API (RENIEC / SUNAT)
 * Si falla, el usuario puede escribir el nombre manualmente (opción 3).
 */
const buscarCliente = async () => {
    if (buscandoCliente.value || esClienteVarios.value) return;

    const documento = texto(cliente.value.numero_documento);

    // Validar longitud
    if (!/^\d+$/.test(documento) || documento.length !== largoDocumentoEsperado.value) {
        mensajeCliente.value = `El ${cliente.value.tipo_documento} debe tener ${largoDocumentoEsperado.value} dígitos.`;
        return;
    }

    const solicitud = new AbortController();
    solicitudCliente = solicitud;
    buscandoCliente.value = true;
    mensajeCliente.value = "";
    documentoConsultado.value = documento;

    const tipoDocumento = cliente.value.tipo_documento;

    try {
        // 1. PRIMERO BUSCAMOS EN NUESTRA BASE DE DATOS
        const urlBD =
            `${route("clientes.buscar-documento")}` +
            `?tipo_documento=${encodeURIComponent(tipoDocumento)}` +
            `&numero_documento=${encodeURIComponent(documento)}`;

        const responseBD = await fetch(urlBD, {
            method: "GET",
            signal: solicitud.signal,
            headers: {
                Accept: "application/json",
                "X-Requested-With": "XMLHttpRequest",
            },
        });

        if (!responseBD.ok) {
            throw new Error(`La búsqueda local devolvió HTTP ${responseBD.status}.`);
        }

        const dataBD = await responseBD.json();
        if (solicitud.signal.aborted) return;

        if (dataBD.success && dataBD.cliente) {
            const nombre = texto(dataBD.cliente.nombre);
            cliente.value.id = dataBD.cliente.id;
            cliente.value.nombre = nombre;
            if (tipoDocumento === "RUC") {
                cliente.value.razon_social_nombre = nombre;
            }

            clienteEncontrado.value = true;
            clienteEncontradoEnAPI.value = false;
            mensajeCliente.value = "Cliente encontrado en tu base de datos.";
            return;
        }

        if (tipoDocumento !== "DNI") {
            cliente.value.id = null;
            clienteEncontrado.value = false;
            mensajeCliente.value =
                "Cliente no encontrado. Puedes ingresar la razón social manualmente.";
            return;
        }

        // La consulta externa se usa solo si el DNI no está registrado localmente.
        const responseAPI = await fetch(route("clientes.consultar-dni"), {
            method: "POST",
            signal: solicitud.signal,
            headers: {
                "Content-Type": "application/json",
                Accept: "application/json",
                "X-Requested-With": "XMLHttpRequest",
                "X-CSRF-TOKEN":
                    document
                        .querySelector('meta[name="csrf-token"]')
                        ?.getAttribute("content") || "",
            },
            body: JSON.stringify({
                numero_documento: documento,
                tipo_documento: tipoDocumento,
            }),
        });

        let dataAPI = null;

        try {
            dataAPI = await responseAPI.json();
        } catch (error) {
            dataAPI = null;
        }
        if (solicitud.signal.aborted) return;

        // 3. API RESPONDIÓ CORRECTAMENTE
        if (responseAPI.ok && dataAPI?.success && dataAPI?.data) {
            const datos = dataAPI.data;

            const nombre =
                datos.nombre_completo ||
                datos.razon_social ||
                datos.nombre ||
                [datos.nombres, datos.apellido_paterno, datos.apellido_materno]
                    .filter(Boolean)
                    .join(" ")
                    .trim();

            if (nombre) {
                cliente.value = {
                    id: null,
                    tipo_documento: tipoDocumento,
                    numero_documento: documento,
                    nombre: nombre,

                    razon_social_nombre:
                        datos.razon_social ||
                        (tipoDocumento === "RUC" ? nombre : ""),

                    ap_paterno: datos.apellido_paterno || "",

                    ap_materno: datos.apellido_materno || "",

                    nombres: datos.nombres || "",

                    direccion: datos.direccion || "",

                    contacto: cliente.value.contacto,
                };

                clienteEncontradoEnAPI.value = true;
                clienteEncontrado.value = false;
                mensajeCliente.value =
                    "Datos obtenidos correctamente. Puedes continuar con la venta.";

                return;
            }
        }

        // 4. LA API NO DEVOLVIÓ DATOS → ENTRADA MANUAL
        // No borramos el documento: el usuario escribe el nombre a mano.
        cliente.value.id = null;
        clienteEncontrado.value = false;
        clienteEncontradoEnAPI.value = false;
        mensajeCliente.value =
            "No se pudieron obtener los datos automáticamente. Puedes ingresar el nombre manualmente.";
    } catch (error) {
        if (solicitud.signal.aborted) return;

        console.error("Error buscando cliente:", error);

        mensajeCliente.value =
            "No se pudo consultar el documento. Puedes ingresar los datos manualmente.";
    } finally {
        if (solicitudCliente === solicitud) {
            solicitudCliente = null;
            buscandoCliente.value = false;
        }
    }
};

/*
|--------------------------------------------------------------------------
| FACTURA REQUIERE RUC
|--------------------------------------------------------------------------
*/
watch(comprobante, (valor, anterior) => {
    if (valor === "Factura") {
        activarClienteIdentificado(); // deja tipo RUC vacío
    } else if (anterior === "Factura") {
        establecerClienteVarios();
    }
});

const clienteValido = computed(() => {
    // Cliente anónimo
    if (esClienteVarios.value) {
        return true;
    }

    // Documento válido: solo dígitos y largo exacto (8 DNI / 11 RUC)
    const documento = texto(cliente.value.numero_documento);
    const documentoValido =
        /^\d+$/.test(documento) &&
        documento.length === largoDocumentoEsperado.value;

    // Nombre válido (viene de la API o escrito a mano)
    const nombreValido = texto(cliente.value.nombre).length >= 3;

    return documentoValido && nombreValido;
});

/*
|--------------------------------------------------------------------------
| MÉTODO DE PAGO
|--------------------------------------------------------------------------
*/
const iconosPago = {
    efectivo: "pi pi-wallet",
    tarjeta: "pi pi-credit-card",
    yape: "pi pi-mobile",
    plin: "pi pi-mobile",
    yapeplin: "pi pi-mobile",
    transferencia: "pi pi-building-columns",
};

const metodosPago = computed(() => props.tiposPago.map(({ id, nombre }) => {
    const clave = nombre.toLowerCase().replace(/[^a-z]/g, "");
    return {
        value: id,
        label: nombre,
        icon: iconosPago[clave] ?? "pi pi-credit-card",
        efectivo: clave === "efectivo",
    };
}));

const metodoPago = ref(null);
const metodoSeleccionado = computed(() => metodosPago.value.find(({ value }) => value === metodoPago.value));
const pagoEsEfectivo = computed(() => metodoSeleccionado.value?.efectivo === true);

const montoRecibido = ref(null);
const montoCalculadora = ref("");
const calculadoraActiva = ref(false);

/**
 * El modo compacto colapsa Comprobante mientras se usa la calculadora.
 * El cliente y las tarjetas de pago siguen visibles.
 */
const modoPagoActivo = computed(() => {
    return calculadoraActiva.value && pagoEsEfectivo.value;
});

const expandirSecciones = () => {
    calculadoraActiva.value = false;
};

const abrirCalculadora = () => {
    calculadoraActiva.value = true;
};

const finalizarIngresoMonto = () => {
    if (montoRecibido.value !== null) {
        calculadoraActiva.value = false;
    }
};

/**
 * Sincroniza el texto de la calculadora con el monto recibido.
 */
watch(montoCalculadora, (valor, _, onCleanup) => {
    if (valor === "" || valor === null) {
        montoRecibido.value = null;
        calculadoraActiva.value = true;
        return;
    }

    calculadoraActiva.value = true;

    const numero = Number(valor);
    montoRecibido.value = Number.isFinite(numero) ? numero : null;

    const timeout = setTimeout(() => {
        calculadoraActiva.value = false;
    }, 700);

    onCleanup(() => clearTimeout(timeout));
});

/**
 * Agregar números desde el teclado de la calculadora
 */
const agregarNumero = (numero) => {
    calculadoraActiva.value = true;

    let valor = montoCalculadora.value;

    // Evitar múltiples puntos decimales
    if (numero === "." && valor.includes(".")) {
        return;
    }

    // "." al inicio → "0."
    if (numero === "." && valor === "") {
        montoCalculadora.value = "0.";
        return;
    }

    // Limitar a 2 decimales
    if (valor.includes(".")) {
        const decimales = valor.split(".")[1];

        if (decimales.length >= 2) {
            return;
        }

        // "00" con un solo decimal: solo agregar lo que quepa
        if (numero === "00" && decimales.length === 1) {
            numero = "0";
        }
    }

    // Evitar ceros innecesarios al inicio
    if (valor === "0" && numero !== ".") {
        valor = numero === "00" ? "0" : numero;
    } else if (valor === "" && numero === "00") {
        valor = "0";
    } else {
        valor += numero;
    }

    montoCalculadora.value = valor;
};

const borrarNumero = () => {
    montoCalculadora.value = montoCalculadora.value.slice(0, -1);
};

const limpiarMonto = () => {
    montoCalculadora.value = "";
};

/**
 * Establecer un monto directamente (billetes rápidos, pago exacto)
 */
const establecerMonto = (monto) => {
    montoCalculadora.value = redondear(monto).toFixed(2);
    calculadoraActiva.value = false;
};

/**
 * Pagar el monto exacto del total, sin dar vuelto.
 */
const pagarExacto = () => {
    if (totalRedondeado.value <= 0) return;

    establecerMonto(totalRedondeado.value);
};

const esMontoExacto = computed(() => {
    return (
        montoRecibido.value !== null &&
        redondear(montoRecibido.value) === totalRedondeado.value
    );
});

const montosRapidos = computed(() => {
    const total = totalRedondeado.value;

    if (total <= 0) {
        return [];
    }

    // Solo billetes que alcanzan para cubrir el total
    return [50, 100, 200].filter((billete) => billete >= total);
});

const vuelto = computed(() => {
    if (!pagoEsEfectivo.value) return 0;

    return redondear(
        Math.max(redondear(montoRecibido.value) - totalRedondeado.value, 0),
    );
});

const faltante = computed(() => {
    if (!pagoEsEfectivo.value) return 0;

    return redondear(
        Math.max(totalRedondeado.value - redondear(montoRecibido.value), 0),
    );
});

const pagoEfectivoValido = computed(() => {
    if (!pagoEsEfectivo.value) return true;

    if (montoRecibido.value === null) return false;

    return redondear(montoRecibido.value) >= totalRedondeado.value;
});

/*
|--------------------------------------------------------------------------
| TOTALES
|--------------------------------------------------------------------------
*/
const totales = computed(() => {
    const total = props.total;

    const op_gravada = total / 1.18;
    const igv = total - op_gravada;

    return {
        total,
        op_gravada,
        igv,
    };
});

const formatearMoneda = (valor) =>
    new Intl.NumberFormat("es-PE", {
        style: "currency",
        currency: "PEN",
    }).format(Number(valor || 0));

/*
|--------------------------------------------------------------------------
| VALIDACIÓN GENERAL Y CONFIRMACIÓN
|--------------------------------------------------------------------------
*/
const formularioValido = computed(() => {
    if (props.procesando) return false;
    if (totalRedondeado.value <= 0) return false;
    if (!props.carrito || props.carrito.length === 0) return false;
    if (!metodoSeleccionado.value || !props.tiposComprobante[comprobante.value.toLowerCase()]) return false;
    if (!clienteValido.value) return false;
    if (!pagoEfectivoValido.value) return false;

    return true;
});

/**
 * Explica por qué el botón está deshabilitado
 */
const motivoBloqueo = computed(() => {
    if (props.procesando || formularioValido.value) return "";

    if (!props.carrito || props.carrito.length === 0) {
        return "El carrito está vacío.";
    }

    if (!metodosPago.value.length) return "No hay medios de pago activos.";
    if (!props.tiposComprobante[comprobante.value.toLowerCase()]) return "No hay un comprobante activo seleccionado.";

    if (!clienteValido.value) {
        if (
            texto(cliente.value.numero_documento).length !==
            largoDocumentoEsperado.value
        ) {
            return `Ingresa un ${cliente.value.tipo_documento} de ${largoDocumentoEsperado.value} dígitos o marca "Cliente anónimo".`;
        }

        return "Busca el documento o escribe el nombre del cliente.";
    }

    if (!pagoEfectivoValido.value) {
        return montoRecibido.value === null
            ? "Ingresa el monto recibido en efectivo."
            : `Faltan ${formatearMoneda(faltante.value)} para completar el pago.`;
    }

    return "";
});

const confirmar = () => {
    if (!formularioValido.value || props.procesando) {
        return;
    }

    const datosCliente = esClienteVarios.value
        ? {
              id: null,
              tipo_documento: "DNI",
              numero_documento: "00000000",
              nombre: "CLIENTES VARIOS",
              razon_social_nombre: "CLIENTES VARIOS",
              ap_paterno: "",
              ap_materno: "",
              nombres: "CLIENTES VARIOS",
              direccion: "",
          }
        : {
              id: cliente.value.id,
              tipo_documento: texto(cliente.value.tipo_documento).toUpperCase(),
              numero_documento: texto(cliente.value.numero_documento),
              nombre: texto(cliente.value.nombre),
              razon_social_nombre: texto(cliente.value.razon_social_nombre),

              ap_paterno: texto(cliente.value.ap_paterno),

              ap_materno: texto(cliente.value.ap_materno),

              nombres: texto(cliente.value.nombres),

              direccion: texto(cliente.value.direccion),
              contacto: texto(cliente.value.contacto),
          };

    emit("confirmar", {
        comprobante: comprobante.value,

        cliente: datosCliente,

        tipo_pago_id: metodoPago.value,

        monto_recibido:
            pagoEsEfectivo.value
                ? redondear(montoRecibido.value)
                : totalRedondeado.value,

        vuelto: pagoEsEfectivo.value ? vuelto.value : 0,

        total: totalRedondeado.value,

        items: props.carrito.map((item) => ({
            producto_id: item.producto_id,
            cantidad: Number(item.cantidad),
            precio_unitario: Number(item.precio_unitario ?? item.precio ?? 0),
            subtotal: Number(item.subtotal ?? 0),
        })),
    });
};

const cerrar = () => {
    if (props.procesando) return;

    dialogVisible.value = false;
};

/*
|--------------------------------------------------------------------------
| REINICIAR FORMULARIO AL ABRIR
|--------------------------------------------------------------------------
*/
watch(
    () => props.visible,
    (abierto) => {
        if (!abierto) {
            limpiarEstadoBusqueda();
            return;
        }

        comprobante.value = props.tiposComprobante.boleta ? "Boleta" : "Factura";

        if (comprobante.value === "Factura") activarClienteIdentificado();
        else establecerClienteVarios();

        const metodoInicial = metodosPago.value.find(({ efectivo }) => efectivo) ?? metodosPago.value[0];
        metodoPago.value = metodoInicial?.value ?? null;

        montoRecibido.value = null;
        montoCalculadora.value = "";
        calculadoraActiva.value = true;
    },
);
</script>

<template>
    <Dialog
        v-model:visible="dialogVisible"
        modal
        :closable="!procesando"
        :close-on-escape="!procesando"
        :dismissable-mask="!procesando"
        :style="{ width: '600px' }"
        class="pago-dialog"
    >
        <!-- HEADER -->
        <template #header>
            <div class="flex items-center gap-3">
                <div
                    class="w-10 h-10 rounded-xl bg-rose-50 flex items-center justify-center"
                >
                    <i class="pi pi-credit-card text-rose-800"></i>
                </div>

                <div>
                    <h2 class="text-base font-semibold text-gray-800 m-0">
                        Confirmar venta
                    </h2>

                    <p class="text-xs text-gray-500 m-0">
                        Completa los datos para emitir el comprobante
                    </p>
                </div>
            </div>
        </template>

        <div class="flex flex-col gap-5">
            <!-- COMPROBANTE -->
            <div
                class="transition-all duration-300"
                :class="modoPagoActivo ? 'opacity-70' : ''"
            >
                <div
                    class="flex items-center justify-between"
                    :class="modoPagoActivo ? 'cursor-pointer' : ''"
                    @click="modoPagoActivo && expandirSecciones()"
                >
                    <label
                        class="text-xs font-semibold text-gray-500 uppercase tracking-wide"
                    >
                        Comprobante
                    </label>

                    <span
                        v-if="modoPagoActivo"
                        class="text-[11px] text-gray-400 flex items-center gap-1"
                    >
                        {{ comprobante }}
                        <i class="pi pi-chevron-down text-[9px]"></i>
                    </span>
                </div>

                <div v-if="!modoPagoActivo" class="w-full mt-2">
                    <button
                        type="button"
                        class="border rounded-lg py-2 text-sm font-medium w-full
                                border-rose-700 bg-rose-50 text-rose-800"
                    >
                        {{ comprobante }}
                    </button>
                </div>
            </div>

            <!-- CLIENTE (siempre visible) -->
            <div class="border border-gray-200 rounded-xl p-3">
                <!-- ENCABEZADO -->
                <div class="flex items-center justify-between mb-2">
                    <div class="flex items-center gap-2">
                        <Checkbox
                            :modelValue="esClienteVarios"
                            :binary="true"
                            :disabled="comprobante === 'Factura'"
                            inputId="cliVarios"
                            @update:modelValue="alternarClienteVarios"
                        />

                        <label
                            for="cliVarios"
                            class="text-xs font-semibold text-gray-700 cursor-pointer"
                        >
                            Cliente anónimo
                        </label>
                    </div>

                    <span
                        v-if="comprobante === 'Factura'"
                        class="text-[11px] text-amber-600"
                    >
                        La factura requiere RUC
                    </span>
                </div>

                <!-- DATOS DEL CLIENTE (API o manual) -->
                <div v-if="!esClienteVarios" class="flex flex-col gap-2">
                    <div class="flex gap-2">
                        <Select
                            v-model="cliente.tipo_documento"
                            :options="
                                comprobante === 'Factura'
                                    ? ['RUC']
                                    : ['DNI', 'RUC']
                            "
                            :disabled="comprobante === 'Factura'"
                            class="w-28"
                            @change="cambiarTipoDocumento"
                        />

                        <div class="relative min-w-0 flex-1">
                            <InputText
                                v-model="cliente.numero_documento"
                                :maxlength="largoDocumentoEsperado"
                                inputmode="numeric"
                                :placeholder="
                                    cliente.tipo_documento === 'RUC'
                                        ? 'RUC (11 dígitos)'
                                        : 'DNI (8 dígitos)'
                                "
                                class="w-full !pr-11"
                                aria-label="Número de documento del cliente"
                                @keydown.enter.prevent="buscarCliente"
                            />

                            <button
                                type="button"
                                class="absolute inset-y-0 right-0 flex w-10 items-center justify-center rounded-r-md text-gray-500 hover:text-rose-700 disabled:opacity-60"
                                aria-label="Buscar cliente por documento"
                                :disabled="buscandoCliente"
                                @click="buscarCliente"
                            >
                                <i
                                    :class="buscandoCliente ? 'pi pi-spin pi-spinner' : 'pi pi-search'"
                                    aria-hidden="true"
                                ></i>
                            </button>
                        </div>
                    </div>

                    <InputText
                        v-model="cliente.nombre"
                        :placeholder="
                            cliente.tipo_documento === 'RUC'
                                ? 'Razón social'
                                : 'Nombre completo'
                        "
                        class="w-full"
                    />

                    <p
                        v-if="mensajeCliente"
                        role="status"
                        class="text-[11px] m-0"
                        :class="
                            clienteEncontrado || clienteEncontradoEnAPI
                                ? 'text-emerald-600'
                                : 'text-amber-600'
                        "
                    >
                        <i
                            :class="
                                clienteEncontrado || clienteEncontradoEnAPI
                                    ? 'pi pi-check-circle'
                                    : 'pi pi-info-circle'
                            "
                            class="mr-1"
                        ></i>
                        {{ mensajeCliente }}
                    </p>

                    <p
                        v-else-if="
                            texto(cliente.numero_documento).length !==
                            largoDocumentoEsperado
                        "
                        class="text-[11px] text-gray-400 m-0"
                    >
                        Presiona Enter o la lupa para buscar, o escribe el
                        nombre manualmente.
                    </p>

                    <div>
                        <label
                            for="venta-cliente-contacto"
                            class="block text-xs text-slate-600 mb-1"
                        >
                            Celular o correo electrónico (opcional)
                        </label>
                        <InputText
                            id="venta-cliente-contacto"
                            v-model="cliente.contacto"
                            placeholder="Ej. 987654321 o nombre@correo.com"
                            maxlength="255"
                            class="w-full"
                        />
                    </div>
                </div>

                <p v-else class="text-xs text-gray-400 m-0">
                    Se registrará como "Clientes varios".
                </p>
            </div>

            <!-- MÉTODO DE PAGO -->
            <div class="transition-all duration-300">
                <p
                    class="text-xs font-semibold text-gray-500 uppercase tracking-wide"
                >
                    Método de pago
                </p>

                <!-- MÉTODOS -->
                <div
                    class="grid grid-cols-3 gap-2 mt-2"
                    role="group"
                    aria-label="Método de pago"
                >
                    <button
                        v-for="metodo in metodosPago"
                        :key="metodo.value"
                        type="button"
                        class="flex flex-col items-center gap-1 border rounded-lg py-3 transition-all"
                        :class="
                            metodoPago === metodo.value
                                ? 'border-rose-700 bg-rose-50 text-rose-800'
                                : 'border-gray-200 text-gray-500 hover:border-rose-300 hover:text-rose-700'
                        "
                        :aria-pressed="metodoPago === metodo.value"
                        @click="metodoPago = metodo.value"
                    >
                        <i :class="metodo.icon" class="text-lg"></i>

                        <span
                            class="text-[11px] font-medium leading-tight text-center"
                        >
                            {{ metodo.label }}
                        </span>
                    </button>
                </div>

                <!-- CALCULADORA -->
                <div
                    v-if="pagoEsEfectivo"
                    class="mt-3 border rounded-xl overflow-hidden transition-all duration-300"
                    :class="
                        modoPagoActivo
                            ? 'border-rose-300 shadow-md ring-1 ring-rose-100'
                            : 'border-gray-200'
                    "
                >
                    <!-- PANTALLA -->
                    <div
                        class="px-4 py-3 transition-all duration-300"
                        :class="modoPagoActivo ? 'bg-rose-100' : 'bg-rose-50'"
                    >
                        <div class="flex justify-between items-center mb-1">
                            <span class="text-xs text-gray-500">
                                Total a pagar
                            </span>

                            <span class="text-sm font-semibold text-gray-700">
                                {{ formatearMoneda(props.total) }}
                            </span>
                        </div>

                        <div class="flex justify-between items-end gap-3">
                            <span class="text-xs font-semibold text-gray-500">
                                Monto recibido
                            </span>

                            <InputText
                                v-model="montoCalculadora"
                                inputmode="decimal"
                                autocomplete="off"
                                class="!w-40 !text-right !text-2xl !font-bold !text-rose-800"
                                placeholder="0.00"
                                @focus="abrirCalculadora"
                                @keydown.enter.prevent="finalizarIngresoMonto"
                            />
                        </div>
                    </div>

                    <!-- MONTOS RÁPIDOS -->
                    <div v-if="!modoPagoActivo" class="px-3 pt-3">
                        <div class="flex gap-2 flex-wrap">
                            <button
                                type="button"
                                class="flex-1 min-w-[75px] text-xs font-semibold px-3 py-2 rounded-lg border transition"
                                :class="
                                    esMontoExacto
                                        ? 'border-emerald-600 bg-emerald-600 text-white'
                                        : 'border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100'
                                "
                                @click="pagarExacto"
                            >
                                <i class="pi pi-check-circle mr-1"></i>
                                Exacto
                            </button>

                            <button
                                v-for="monto in montosRapidos"
                                :key="monto"
                                type="button"
                                class="flex-1 min-w-[75px] text-xs font-semibold px-3 py-2 rounded-lg border transition"
                                :class="
                                    montoRecibido === monto
                                        ? 'border-rose-700 bg-rose-700 text-white'
                                        : 'border-rose-200 bg-rose-50 text-rose-800 hover:bg-rose-100'
                                "
                                @click="establecerMonto(monto)"
                            >
                                {{ formatearMoneda(monto) }}
                            </button>
                        </div>
                    </div>

                    <!-- TECLADO -->
                    <div
                        v-if="calculadoraActiva"
                        class="grid grid-cols-3 gap-2 p-3"
                        :class="modoPagoActivo ? 'pt-3' : ''"
                    >
                        <button
                            v-for="numero in [
                                '7',
                                '8',
                                '9',
                                '4',
                                '5',
                                '6',
                                '1',
                                '2',
                                '3',
                            ]"
                            :key="numero"
                            type="button"
                            class="h-12 rounded-lg bg-white border border-gray-200 text-lg font-semibold text-gray-700 hover:bg-rose-50 hover:border-rose-200 hover:text-rose-800 active:scale-95 transition"
                            @click="agregarNumero(numero)"
                        >
                            {{ numero }}
                        </button>

                        <!-- LIMPIAR -->
                        <button
                            type="button"
                            class="h-12 rounded-lg bg-gray-100 border border-gray-200 text-sm font-semibold text-gray-600 hover:bg-gray-200 active:scale-95 transition"
                            @click="limpiarMonto"
                        >
                            C
                        </button>

                        <!-- 0 -->
                        <button
                            type="button"
                            class="h-12 rounded-lg bg-white border border-gray-200 text-lg font-semibold text-gray-700 hover:bg-rose-50 hover:border-rose-200 hover:text-rose-800 active:scale-95 transition"
                            @click="agregarNumero('0')"
                        >
                            0
                        </button>

                        <!-- BORRAR -->
                        <button
                            type="button"
                            class="h-12 rounded-lg bg-gray-100 border border-gray-200 text-gray-600 hover:bg-gray-200 active:scale-95 transition"
                            @click="borrarNumero"
                        >
                            <i class="pi pi-delete-left"></i>
                        </button>

                        <!-- DECIMAL -->
                        <button
                            type="button"
                            class="h-12 rounded-lg bg-white border border-gray-200 text-lg font-semibold text-gray-700 hover:bg-rose-50 hover:border-rose-200 hover:text-rose-800 active:scale-95 transition"
                            @click="agregarNumero('.')"
                        >
                            .
                        </button>

                        <!-- 00 -->
                        <button
                            type="button"
                            class="col-span-2 h-12 rounded-lg bg-white border border-gray-200 text-lg font-semibold text-gray-700 hover:bg-rose-50 hover:border-rose-200 hover:text-rose-800 active:scale-95 transition"
                            @click="agregarNumero('00')"
                        >
                            00
                        </button>

                        <button
                            type="button"
                            class="col-span-3 h-11 rounded-lg bg-rose-700 text-sm font-semibold text-white hover:bg-rose-800 active:scale-95 transition"
                            @click="finalizarIngresoMonto"
                        >
                            Listo
                        </button>
                    </div>

                    <!-- VUELTO / FALTANTE -->
                    <div
                        v-if="montoRecibido !== null"
                        class="mx-3 mb-3 px-4 py-4 rounded-xl flex justify-between items-center transition-all duration-300"
                        :class="
                            pagoEfectivoValido
                                ? 'bg-green-50 border border-green-200 text-green-700'
                                : 'bg-amber-50 border border-amber-200 text-amber-700'
                        "
                    >
                        <div>
                            <span class="text-xs font-semibold block">
                                {{
                                    pagoEfectivoValido
                                        ? esMontoExacto
                                            ? "Pago exacto"
                                            : "Vuelto"
                                        : "Falta pagar"
                                }}
                            </span>

                            <span class="text-[11px] opacity-70">
                                {{
                                    pagoEfectivoValido
                                        ? esMontoExacto
                                            ? "Sin vuelto que entregar"
                                            : "Entregar al cliente"
                                        : "Monto insuficiente"
                                }}
                            </span>
                        </div>

                        <span class="text-2xl font-bold">
                            {{
                                formatearMoneda(
                                    pagoEfectivoValido ? vuelto : faltante,
                                )
                            }}
                        </span>
                    </div>
                </div>
            </div>

            <Divider class="my-0" />

            <!-- RESUMEN -->
            <div
                class="w-full rounded-lg bg-gray-50 border border-gray-200 p-3"
            >
                <div class="flex items-center justify-between mb-2">
                    <span
                        class="text-xs font-semibold text-gray-500 uppercase tracking-wide"
                    >
                        Resumen de compra
                    </span>

                    <span class="text-xs text-gray-400">
                        {{ carrito.length }} producto{{
                            carrito.length !== 1 ? "s" : ""
                        }}
                    </span>
                </div>

                <div class="flex flex-col gap-1 max-h-32 overflow-y-auto pr-1">
                    <div
                        v-for="item in carrito"
                        :key="item.producto_id"
                        class="flex items-center justify-between gap-3 py-1"
                    >
                        <div class="min-w-0 flex-1 flex items-center gap-2">
                            <span class="shrink-0 text-[11px] text-gray-400">
                                {{ item.cantidad }} ×
                                {{ formatearMoneda(item.precio_unitario) }}
                            </span>

                            <p
                                class="text-xs font-medium text-gray-700 truncate"
                                :title="item.producto?.nombre"
                            >
                                {{ item.producto?.nombre }}
                            </p>
                        </div>

                        <span
                            class="shrink-0 text-xs font-semibold text-gray-700"
                        >
                            {{ formatearMoneda(item.subtotal) }}
                        </span>
                    </div>
                </div>

                <div
                    class="flex items-center justify-between border-t border-gray-200 mt-2 pt-2"
                >
                    <span class="text-xs font-semibold text-gray-600">
                        Total
                    </span>

                    <span class="text-base font-bold text-rose-800">
                        {{ formatearMoneda(totales.total) }}
                    </span>
                </div>
            </div>
        </div>

        <!-- FOOTER -->
        <template #footer>
            <div class="flex flex-col gap-2 w-full">
                <p
                    v-if="motivoBloqueo"
                    class="text-[11px] text-amber-600 m-0 flex items-center gap-1"
                >
                    <i class="pi pi-info-circle"></i>
                    {{ motivoBloqueo }}
                </p>

                <div class="flex gap-2 w-full">
                    <Button
                        label="Cancelar"
                        severity="secondary"
                        text
                        :disabled="procesando"
                        class="flex-1"
                        @click="cerrar"
                    />

                    <Button
                        label="Confirmar venta"
                        icon="pi pi-check"
                        :loading="procesando"
                        :disabled="!formularioValido"
                        class="flex-1 !bg-rose-800 !border-rose-800 hover:!bg-rose-900 hover:!border-rose-900"
                        @click="confirmar"
                    />
                </div>
            </div>
        </template>
    </Dialog>
</template>

<style scoped>
.pago-dialog :deep(.p-dialog-header) {
    padding-bottom: 0.75rem;
}

/* Color vino para el foco de los campos */
.pago-dialog :deep(.p-inputtext:focus) {
    border-color: #9f1239;
    box-shadow: 0 0 0 1px #9f1239;
}

/* Color vino para el Select */
.pago-dialog :deep(.p-select:focus) {
    border-color: #9f1239;
    box-shadow: 0 0 0 1px #9f1239;
}
</style>
