<script setup>
import { ref, computed, watch } from "vue";

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
});

const emit = defineEmits(["update:visible", "confirmar"]);

const dialogVisible = computed({
    get: () => props.visible,
    set: (value) => emit("update:visible", value),
});

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

const cliente = ref({
    id: null,
    tipo_documento: "DNI",
    numero_documento: "",
    nombre: "",
});

const largoDocumentoEsperado = computed(() => {
    return cliente.value.tipo_documento === "RUC" ? 11 : 8;
});

    const buscarCliente = async () => {
        const documento = cliente.value.numero_documento.trim();

        if (documento.length !== largoDocumentoEsperado.value) {
            return;
        }

        buscandoCliente.value = true;
        mensajeCliente.value = "";

        try {
            // =====================================================
            // 1. PRIMERO BUSCAMOS EN NUESTRA BASE DE DATOS
            // =====================================================

            const responseBD = await fetch(
                `${route("clientes.buscar-documento")}?tipo_documento=${encodeURIComponent(
                    cliente.value.tipo_documento,
                )}&numero_documento=${encodeURIComponent(documento)}`,
                {
                    method: "GET",
                    headers: {
                        Accept: "application/json",
                        "X-Requested-With": "XMLHttpRequest",
                    },
                },
            );

            if (!responseBD.ok) {
                throw new Error(`Error BD HTTP ${responseBD.status}`);
            }

            const dataBD = await responseBD.json();

            // =====================================================
            // 2. SI EXISTE EN BD
            // =====================================================

            if (dataBD.success && dataBD.cliente) {
                cliente.value = {
                    id: dataBD.cliente.id,
                    tipo_documento: dataBD.cliente.tipo_documento,
                    numero_documento: dataBD.cliente.numero_documento,
                    nombre: dataBD.cliente.nombre,
                };

                clienteEncontrado.value = true;
                clienteEncontradoEnAPI.value = false;
                mensajeCliente.value = "Cliente encontrado en tu base de datos.";

                return;
            }

            // =====================================================
            // 3. NO EXISTE → CONSULTAMOS JSON.PE
            // =====================================================

            const responseAPI = await fetch(route("clientes.consultar-dni"), {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    Accept: "application/json",
                    "X-Requested-With": "XMLHttpRequest",
                    "X-CSRF-TOKEN": document
                        .querySelector('meta[name="csrf-token"]')
                        ?.getAttribute("content"),
                },
                body: JSON.stringify({
                    numero_documento: documento,
                }),
            });

            const textoAPI = await responseAPI.text();

            let dataAPI;

            try {
                dataAPI = JSON.parse(textoAPI);
            } catch (e) {
                console.error("Respuesta no JSON de JSON.pe:", textoAPI);
                throw new Error(`Respuesta inválida HTTP ${responseAPI.status}`);
            }

            if (!responseAPI.ok) {
                console.error(
                    "Error consultando JSON.pe:",
                    responseAPI.status,
                    dataAPI,
                );

                throw new Error(`Error HTTP ${responseAPI.status}`);
            }

            // =====================================================
            // 4. JSON.PE ENCONTRÓ EL DOCUMENTO
            // =====================================================

            if (dataAPI.success && dataAPI.data) {
                const datos = dataAPI.data;

                cliente.value = {
                    id: null,
                    tipo_documento: cliente.value.tipo_documento,
                    numero_documento: documento,
                    nombre:
                        datos.nombre_completo ||
                        `${datos.nombres ?? ""} ${datos.apellido_paterno ?? ""} ${datos.apellido_materno ?? ""}`.trim(),
                };

                clienteEncontrado.value = false;
                clienteEncontradoEnAPI.value = true;
                mensajeCliente.value = "Datos obtenidos de RENIEC/SUNAT. Se guardarán al confirmar.";
            } else {
                cliente.value.id = null;
                cliente.value.nombre = "";

                clienteEncontrado.value = false;
                clienteEncontradoEnAPI.value = false;
                mensajeCliente.value = "No se encontró el documento. Ingresa el nombre manualmente.";
            }
        } catch (error) {
            console.error("Error buscando cliente:", error);

            cliente.value.id = null;
            cliente.value.nombre = "";

            clienteEncontrado.value = false;
            clienteEncontradoEnAPI.value = false;
            mensajeCliente.value = "No se pudo validar el documento. Ingresa el nombre manualmente.";
        } finally {
            buscandoCliente.value = false;
        }
    };

const modoPagoActivo = computed(() => {
    return calculadoraActiva.value && metodoPago.value === "efectivo";
});

const mostrarContenidoSecundario = computed(() => {
    return !modoPagoActivo.value;
});

/*
|--------------------------------------------------------------------------
| FACTURA REQUIERE RUC
|--------------------------------------------------------------------------
*/
watch(comprobante, (valor) => {
    if (valor === "Factura") {
        esClienteVarios.value = false;

        cliente.value.tipo_documento = "RUC";
        cliente.value.numero_documento = "";
        cliente.value.nombre = "";
    }

    if (valor === "Boleta") {
        esClienteVarios.value = true;

        cliente.value.tipo_documento = "DNI";
        cliente.value.numero_documento = "";
        cliente.value.nombre = "";
    }
});

const clienteValido = computed(() => {
    if (esClienteVarios.value) {
        return true;
    }

    return (
        cliente.value.id !== null &&
        cliente.value.numero_documento.length ===
            largoDocumentoEsperado.value &&
        cliente.value.nombre.trim().length > 2
    );
});

/*
|--------------------------------------------------------------------------
| MÉTODO DE PAGO
|--------------------------------------------------------------------------
*/
const metodosPago = [
    {
        value: "efectivo",
        label: "Efectivo",
        icon: "pi pi-wallet",
    },
    {
        value: "tarjeta",
        label: "Tarjeta",
        icon: "pi pi-credit-card",
    },
    {
        value: "yape_plin",
        label: "Yape / Plin",
        icon: "pi pi-mobile",
    },
];

const metodoPago = ref("efectivo");

const montoRecibido = ref(null);
const montoCalculadora = ref("");

/**
 * Sincroniza el texto de la calculadora con el monto recibido.
 * Se ejecuta automáticamente cada vez que cambia el input,
 * por lo que no hace falta un botón para "confirmar" el monto.
 */
watch(montoCalculadora, (valor) => {
    if (valor === "" || valor === null) {
        montoRecibido.value = null;
        calculadoraActiva.value = false;
        return;
    }

    calculadoraActiva.value = true;

    const numero = Number(valor);

    montoRecibido.value = Number.isFinite(numero) ? numero : null;
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

    // Limitar a 2 decimales
    if (valor.includes(".")) {
        const decimales = valor.split(".")[1];

        if (decimales.length >= 2) {
            return;
        }
    }

    // Evitar ceros innecesarios al inicio
    if (valor === "0" && numero !== ".") {
        valor = numero;
    } else {
        valor += numero;
    }

    montoCalculadora.value = valor;
};

/**
 * Borrar último número
 */
const borrarNumero = () => {
    montoCalculadora.value = montoCalculadora.value.slice(0, -1);
};

/**
 * Limpiar completamente el monto
 */
const limpiarMonto = () => {
    montoCalculadora.value = "";
};

const calculadoraActiva = ref(false);

/**
 * Establecer un monto directamente (billetes rápidos, pago exacto)
 */
const establecerMonto = (monto) => {
    montoCalculadora.value = Number(monto).toFixed(2);
};

/**
 * Pagar el monto exacto del total, sin dar vuelto.
 */
const pagarExacto = () => {
    if (!props.total || props.total <= 0) return;

    establecerMonto(props.total);
};

const esMontoExacto = computed(() => {
    return (
        montoRecibido.value !== null &&
        Number(montoRecibido.value).toFixed(2) ===
            Number(props.total || 0).toFixed(2)
    );
});

const montosRapidos = computed(() => {
    const total = Number(props.total || 0);

    if (total <= 0) {
        return [];
    }

    // Billetes que quieres mostrar
    const billetes = [50, 100, 200];

    // Mostrar únicamente los billetes
    // que alcancen para cubrir el total
    return billetes.filter((billete) => billete >= total);
});

const vuelto = computed(() => {
    if (metodoPago.value !== "efectivo") return 0;

    return Math.max(Number(montoRecibido.value || 0) - props.total, 0);
});

const faltante = computed(() => {
    if (metodoPago.value !== "efectivo") return 0;

    return Math.max(props.total - Number(montoRecibido.value || 0), 0);
});

const pagoEfectivoValido = computed(() => {
    if (metodoPago.value !== "efectivo") return true;

    return Number(montoRecibido.value || 0) >= props.total;
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
    if (props.procesando) {
        return false;
    }

    if (!props.total || Number(props.total) <= 0) {
        return false;
    }

    if (!clienteValido.value) {
        return false;
    }

    if (!pagoEfectivoValido.value) {
        return false;
    }

    if (!props.carrito || props.carrito.length === 0) {
        return false;
    }

    return true;
});

const confirmar = () => {
    if (!formularioValido.value || props.procesando) {
        return;
    }

    const datosCliente = esClienteVarios.value
        ? {
              tipo_documento: "DNI",
              numero_documento: "00000000",
              nombre: "CLIENTES VARIOS",
          }
        : {
              tipo_documento: cliente.value.tipo_documento,
              numero_documento: cliente.value.numero_documento,
              nombre: cliente.value.nombre.trim(),
          };

    emit("confirmar", {
        comprobante: comprobante.value,

        cliente: datosCliente,

        metodo_pago: metodoPago.value,

        monto_recibido:
            metodoPago.value === "efectivo"
                ? Number(montoRecibido.value || 0)
                : Number(props.total),

        vuelto: metodoPago.value === "efectivo" ? Number(vuelto.value || 0) : 0,

        total: Number(props.total),

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
| REINICIAR FORMULARIO
|--------------------------------------------------------------------------
*/
watch(
    () => props.visible,
    (abierto) => {
        if (!abierto) return;

        comprobante.value = "Boleta";

        esClienteVarios.value = true;

        cliente.value = {
            id: null,
            tipo_documento: "DNI",
            numero_documento: "",
            nombre: "",
        };

        metodoPago.value = "efectivo";

        montoRecibido.value = null;
        montoCalculadora.value = "";

        mensajeCliente.value = "";
        clienteEncontrado.value = false;
        clienteEncontradoEnAPI.value = false;
    },
);

watch(
    () => cliente.value.tipo_documento,
    () => {
        cliente.value.id = null;
        cliente.value.numero_documento = "";
        cliente.value.nombre = "";

        clienteEncontrado.value = false;
        clienteEncontradoEnAPI.value = false;
        mensajeCliente.value = "";
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
                    class="flex items-center justify-between cursor-pointer"
                    @click="modoPagoActivo && (calculadoraActiva = false)"
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

                <div v-if="!modoPagoActivo" class="grid grid-cols-2 gap-2 mt-2">
                    <button
                        v-for="tipo in ['Boleta', 'Factura']"
                        :key="tipo"
                        type="button"
                        class="border rounded-lg py-2 text-sm font-medium transition-all"
                        :class="
                            comprobante === tipo
                                ? 'border-rose-700 bg-rose-50 text-rose-800'
                                : 'border-gray-200 text-gray-500 hover:border-rose-300 hover:text-rose-700'
                        "
                        @click="comprobante = tipo"
                    >
                        {{ tipo }}
                    </button>
                </div>
            </div>

            <!-- CLIENTE -->
            <div
                class="border border-gray-200 rounded-xl transition-all duration-300"
                :class="modoPagoActivo ? 'px-3 py-2 bg-gray-50/70' : 'p-3'"
            >
                <!-- ENCABEZADO -->
                <div
                    class="flex items-center justify-between"
                    :class="!modoPagoActivo ? 'mb-2' : ''"
                >
                    <div class="flex items-center gap-2">
                        <Checkbox
                            v-if="!modoPagoActivo"
                            v-model="esClienteVarios"
                            :binary="true"
                            :disabled="comprobante === 'Factura'"
                            inputId="cliVarios"
                        />

                        <label
                            v-if="!modoPagoActivo"
                            for="cliVarios"
                            class="text-xs font-semibold text-gray-700 cursor-pointer"
                        >
                            Cliente anónimo
                        </label>

                        <span
                            v-else
                            class="text-xs font-semibold text-gray-600"
                        >
                            Cliente
                        </span>
                    </div>

                    <span
                        v-if="modoPagoActivo"
                        class="text-[11px] text-gray-400"
                    >
                        {{
                            esClienteVarios
                                ? "Clientes varios"
                                : cliente.nombre || "Cliente identificado"
                        }}
                    </span>

                    <span
                        v-if="!modoPagoActivo && comprobante === 'Factura'"
                        class="text-[11px] text-amber-600"
                    >
                        La factura requiere RUC
                    </span>
                </div>

                <!-- DATOS DEL CLIENTE -->
                <div
                    v-if="!modoPagoActivo && !esClienteVarios"
                    class="flex flex-col gap-2"
                >
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
                        />

                        <IconField class="w-full">
                            <InputText
                                v-model="cliente.numero_documento"
                                :maxlength="largoDocumentoEsperado"
                                :placeholder="
                                    cliente.tipo_documento === 'RUC'
                                        ? 'RUC (11 dígitos)'
                                        : 'DNI (8 dígitos)'
                                "
                                class="w-full"
                                @keydown.enter="buscarCliente"
                            />

                            <InputIcon
                                :class="
                                    buscandoCliente
                                        ? 'pi pi-spin pi-spinner'
                                        : 'pi pi-search cursor-pointer hover:text-rose-700'
                                "
                                @click="buscarCliente"
                            />
                        </IconField>
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
                </div>

                <p
                    v-if="!modoPagoActivo && esClienteVarios"
                    class="text-xs text-gray-400 m-0"
                >
                    Se registrará como "Clientes varios".
                </p>
            </div>

            <!-- MÉTODO DE PAGO -->
            <div
                class="transition-all duration-300"
                :class="modoPagoActivo ? 'mt-0' : ''"
            >
                <div class="flex items-center justify-between">
                    <label
                        class="text-xs font-semibold text-gray-500 uppercase tracking-wide"
                    >
                        Método de pago
                    </label>

                    <span
                        v-if="modoPagoActivo"
                        class="text-[11px] font-medium text-rose-700"
                    >
                        {{
                            metodosPago.find((m) => m.value === metodoPago)
                                ?.label
                        }}
                    </span>
                </div>

                <!-- MÉTODOS -->
                <div v-if="!modoPagoActivo" class="grid grid-cols-3 gap-2 mt-2">
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
                    v-if="metodoPago === 'efectivo'"
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
                                @input="calculadoraActiva = true"
                            />
                        </div>
                    </div>

                    <!-- MONTOS RÁPIDOS -->
                    <div v-if="!modoPagoActivo" class="px-3 pt-3">
                        <div class="flex gap-2 flex-wrap">
                            <!-- Pagar exacto: siempre visible primero, es la opción más común -->
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

                        <!-- 00: ocupa el espacio restante, sin un tercer botón que confunda -->
                        <button
                            type="button"
                            class="col-span-2 h-12 rounded-lg bg-white border border-gray-200 text-lg font-semibold text-gray-700 hover:bg-rose-50 hover:border-rose-200 hover:text-rose-800 active:scale-95 transition"
                            @click="agregarNumero('00')"
                        >
                            00
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
            <div>
                <div
                    class="flex flex-col gap-1.5 max-h-32 overflow-y-auto pr-1 mb-3"
                >
                    <div
                        v-for="item in carrito"
                        :key="item.producto_id"
                        class="flex justify-between text-xs text-gray-500"
                    >
                        <span class="truncate pr-2">
                            {{ item.cantidad }}x
                            {{ item.producto?.nombre }}
                        </span>

                        <span class="shrink-0 font-medium text-gray-700">
                            {{ formatearMoneda(item.subtotal) }}
                        </span>
                    </div>
                </div>

                <div class="flex justify-between text-xs text-gray-500 mb-1">
                    <span>Op. gravada</span>

                    <span>
                        {{ formatearMoneda(totales.op_gravada) }}
                    </span>
                </div>

                <div class="flex justify-between text-xs text-gray-500 mb-3">
                    <span>IGV (18%)</span>

                    <span>
                        {{ formatearMoneda(totales.igv) }}
                    </span>
                </div>

                <!-- TOTAL -->
                <div
                    class="rounded-xl bg-rose-50 border border-rose-200 px-4 py-3 flex justify-between items-center"
                >
                    <span class="font-semibold text-gray-700">
                        Total a pagar
                    </span>

                    <span class="text-xl font-bold text-rose-800">
                        {{ formatearMoneda(totales.total) }}
                    </span>
                </div>
            </div>
        </div>

        <!-- FOOTER -->
        <template #footer>
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