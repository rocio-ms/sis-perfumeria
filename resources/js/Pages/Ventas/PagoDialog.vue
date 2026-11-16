<script setup>
import { solicitar } from "@/solicitudes";
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
| COMPROBANTE
|--------------------------------------------------------------------------
*/
const esFactura = computed(() => !props.tiposComprobante.notadeventa && Boolean(props.tiposComprobante.factura));
const comprobante = computed(() => esFactura.value ? "Factura" : "Nota de venta");
const comprobanteDisponible = computed(() =>
    Boolean(props.tiposComprobante[esFactura.value ? "factura" : "notadeventa"]),
);

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
        tipo_documento: esFactura.value ? "RUC" : "DNI",
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

        const responseBD = await solicitar(urlBD, {
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
        const responseAPI = await solicitar(route("clientes.consultar-dni"), {
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

const montoEfectivo = ref("");
const montoRecibido = computed(() => {
    const monto = texto(montoEfectivo.value);
    if (!/^(?:\d+(?:[.,]\d{0,2})?|[.,]\d{1,2})$/.test(monto)) return null;

    const importe = Number(monto.replace(",", "."));
    return Number.isFinite(importe) ? importe : null;
});

const pagarExacto = () => {
    montoEfectivo.value = totalRedondeado.value.toFixed(2);
};

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
    if (!metodoSeleccionado.value || !comprobanteDisponible.value) return false;
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
    if (!comprobanteDisponible.value) return "No hay un tipo de comprobante activo para esta venta.";

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
            : "El monto recibido debe cubrir el total.";
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

        if (esFactura.value) activarClienteIdentificado();
        else establecerClienteVarios();

        const metodoInicial = metodosPago.value.find(({ efectivo }) => efectivo) ?? metodosPago.value[0];
        metodoPago.value = metodoInicial?.value ?? null;

        montoEfectivo.value = "";
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
        :style="{ width: '560px', maxWidth: 'calc(100vw - 1.5rem)' }"
        class="pago-dialog"
    >
        <template #header>
            <div>
                <h2 class="text-lg font-semibold text-gray-900">Confirmar venta</h2>
                <p class="mt-1 text-sm text-gray-600">
                    {{ comprobante }} · {{ carrito.length }} {{ carrito.length === 1 ? "producto" : "productos" }}
                </p>
            </div>
        </template>

        <div class="flex flex-col gap-4">
            <div class="flex flex-wrap items-baseline justify-between gap-x-3 rounded-lg bg-rose-50 px-4 py-3 text-rose-900">
                <span class="text-sm font-semibold">Total a cobrar</span>
                <strong class="text-2xl tabular-nums">{{ formatearMoneda(totalRedondeado) }}</strong>
            </div>

            <fieldset>
                <legend class="mb-2 text-sm font-semibold text-gray-800">Método de pago</legend>
                <div class="grid grid-cols-2 gap-2 min-[400px]:grid-cols-3">
                    <button
                        v-for="metodo in metodosPago"
                        :key="metodo.value"
                        type="button"
                        class="flex min-h-11 items-center justify-center gap-1.5 rounded-lg border px-2 py-2 text-center text-sm font-medium"
                        :class="metodoPago === metodo.value
                            ? 'border-rose-700 bg-rose-50 text-rose-900'
                            : 'border-gray-200 text-gray-700 hover:border-rose-300'"
                        :aria-pressed="metodoPago === metodo.value"
                        :disabled="procesando"
                        @click="metodoPago = metodo.value"
                    >
                        <i :class="metodo.icon" aria-hidden="true"></i>
                        {{ metodo.label }}
                    </button>
                </div>
            </fieldset>

            <div v-if="pagoEsEfectivo" class="rounded-lg bg-rose-50 p-3">
                <label for="monto-efectivo-venta" class="mb-2 block text-base font-semibold text-rose-950">
                    Monto recibido
                </label>
                <div class="grid gap-2 min-[420px]:grid-cols-[minmax(0,1fr)_auto]">
                    <InputText
                        id="monto-efectivo-venta"
                        v-model="montoEfectivo"
                        inputmode="decimal"
                        autocomplete="off"
                        class="min-h-12 w-full min-w-0 !border-rose-300 !bg-white !text-2xl !font-bold !text-rose-950 tabular-nums"
                        :disabled="procesando"
                        autofocus
                    />
                    <Button
                        type="button"
                        label="Monto exacto"
                        icon="pi pi-check-circle"
                        severity="success"
                        class="min-h-11 !border-emerald-700 !bg-emerald-700 !px-4 !font-semibold !text-white hover:!border-emerald-800 hover:!bg-emerald-800"
                        :disabled="procesando || totalRedondeado <= 0"
                        @click="pagarExacto"
                    />
                </div>
                <div
                    class="mt-2 flex flex-wrap items-baseline justify-between gap-x-2 text-rose-900"
                    :class="{ '!text-amber-800': montoRecibido !== null && !pagoEfectivoValido }"
                    aria-live="polite"
                >
                    <span class="font-semibold">{{ montoRecibido !== null && !pagoEfectivoValido ? "Falta" : "Vuelto" }}</span>
                    <strong class="text-3xl tabular-nums">
                        {{ formatearMoneda(montoRecibido === null || pagoEfectivoValido ? vuelto : faltante) }}
                    </strong>
                </div>
            </div>

            <section aria-labelledby="cliente-venta-label">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h3 id="cliente-venta-label" class="text-sm font-semibold text-gray-800">Cliente</h3>
                        <p class="text-sm text-gray-600">
                            {{ esClienteVarios ? "Cliente anónimo" : (esFactura ? "La factura requiere RUC" : "Cliente identificado") }}
                        </p>
                    </div>
                    <Button
                        v-if="!esFactura"
                        type="button"
                        :label="esClienteVarios ? 'Identificar cliente' : 'Usar cliente anónimo'"
                        :icon="esClienteVarios ? 'pi pi-user-plus' : 'pi pi-user-minus'"
                        outlined
                        class="min-h-11 !border-rose-300 !bg-rose-50 !px-4 !font-semibold !text-rose-900 hover:!border-rose-600 hover:!bg-rose-100"
                        aria-controls="datos-cliente-venta"
                        :aria-expanded="!esClienteVarios"
                        :disabled="procesando"
                        @click="alternarClienteVarios(!esClienteVarios)"
                    />
                </div>

                <div v-if="!esClienteVarios" id="datos-cliente-venta" class="mt-3 flex flex-col gap-2">
                    <div class="flex gap-2">
                        <Select aria-label="Tipo de documento del cliente"
                            v-model="cliente.tipo_documento"
                            :options="
                                esFactura
                                    ? ['RUC']
                                    : ['DNI', 'RUC']
                            "
                            :disabled="esFactura"
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
                                class="absolute inset-y-px right-px flex w-10 items-center justify-center rounded-r-md bg-emerald-700 text-white hover:bg-emerald-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-emerald-700 disabled:opacity-60"
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
                        v-model="cliente.nombre" aria-label="Nombre o razón social del cliente"
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
            </section>

            <details class="border-t border-gray-200 pt-3">
                <summary class="cursor-pointer text-sm font-semibold text-rose-900">Ver productos</summary>
                <ul class="mt-2 max-h-36 space-y-2 overflow-y-auto">
                    <li
                        v-for="item in carrito"
                        :key="`${item.producto_id}-${item.lote_id}`"
                        class="flex justify-between gap-3 text-sm text-gray-700"
                    >
                        <span class="min-w-0 break-words">{{ item.cantidad }} × {{ item.producto?.nombre }}</span>
                        <strong class="shrink-0 tabular-nums">{{ formatearMoneda(item.subtotal) }}</strong>
                    </li>
                </ul>
            </details>
        </div>

        <template #footer>
            <div class="flex w-full flex-col gap-2">
                <p v-if="motivoBloqueo" class="text-sm text-gray-600" role="status">{{ motivoBloqueo }}</p>
                <div class="flex flex-wrap gap-2">
                    <Button
                        type="button"
                        label="Cancelar"
                        severity="secondary"
                        text
                        :disabled="procesando"
                        @click="cerrar"
                    />
                    <Button
                        type="button"
                        :label="`Confirmar venta · ${formatearMoneda(totalRedondeado)}`"
                        :loading="procesando"
                        :disabled="!formularioValido"
                        class="flex-1 !border-rose-800 !bg-rose-800 hover:!bg-rose-900"
                        @click="confirmar"
                    />
                </div>
            </div>
        </template>
    </Dialog>
</template>
