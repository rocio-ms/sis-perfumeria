<script setup>
import DataTable from "primevue/datatable";
import Column from "primevue/column";

const props = defineProps({
    visible: {
        type: Boolean,
        default: false,
    },
    ventas: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(["update:visible"]);

const formatoMoneda = (valor) => {
    return new Intl.NumberFormat("es-PE", {
        style: "currency",
        currency: "PEN",
    }).format(Number(valor ?? 0));
};

const formatearFecha = (fecha) => {
    if (!fecha) return "—";

    return new Date(fecha).toLocaleString("es-PE", {
        day: "2-digit",
        month: "2-digit",
        year: "numeric",
        hour: "2-digit",
        minute: "2-digit",
    });
};

const nombreMetodoPago = (venta) => {
    return venta?.tipo_pago?.nombre ?? "—";
};

const nombreCliente = (venta) => {
    if (
        !venta?.cliente ||
        venta.cliente.numero_documento === "00000000"
    ) {
        return "CLIENTES VARIOS";
    }

    return (
        venta.cliente.nombre ??
        venta.cliente.razon_social_nombre ??
        venta.cliente.nombres ??
        "—"
    );
};

const comprobante = (venta) => {
    if (!venta?.serie || !venta?.correlativo) {
        return "—";
    }

    return `${venta.serie}-${String(venta.correlativo).padStart(6, "0")}`;
};
</script>

<template>
    <Dialog
        :visible="visible"
        modal
        header="Movimientos de caja"
        :style="{ width: '1000px' }"
        :breakpoints="{
            '1100px': '90vw',
            '768px': '95vw',
        }"
        @update:visible="emit('update:visible', $event)"
    >
        <!-- ENCABEZADO -->
        <div class="movimientos-header">
            <div>
                <h3>Historial de movimientos</h3>

                <span>
                    Ventas realizadas durante esta sesión de caja
                </span>
            </div>

            <Tag
                :value="`${props.ventas.length} ${
                    props.ventas.length === 1
                        ? 'movimiento'
                        : 'movimientos'
                }`"
                severity="info"
            />
        </div>

        <!-- TABLA -->
        <DataTable
            :value="props.ventas"
            :rows="10"
            stripedRows
            size="small"
            tableStyle="min-width: 60rem"
            emptyMessage="No hay movimientos registrados"
        >
            <Column header="Fecha y hora">
                <template #body="{ data }">
                    {{ formatearFecha(data.fecha_emision) }}
                </template>
            </Column>

            <Column header="Movimiento">
                <template #body>
                    <div class="movimiento">
                        <div class="movimiento-icon venta">
                            <i class="pi pi-shopping-cart"></i>
                        </div>

                        <span>Venta</span>
                    </div>
                </template>
            </Column>

            <Column header="Comprobante">
                <template #body="{ data }">
                    {{ comprobante(data) }}
                </template>
            </Column>

            <Column header="Cliente">
                <template #body="{ data }">
                    {{ nombreCliente(data) }}
                </template>
            </Column>

            <Column header="Método de pago">
                <template #body="{ data }">
                    <Tag
                        :value="nombreMetodoPago(data)"
                        severity="info"
                    />
                </template>
            </Column>

            <Column header="Monto">
                <template #body="{ data }">
                    <strong class="monto">
                        {{ formatoMoneda(data.importe_total) }}
                    </strong>
                </template>
            </Column>
        </DataTable>

        <!-- FOOTER -->
        <template #footer>
            <Button
                label="Cerrar"
                icon="pi pi-times"
                severity="secondary"
                text
                @click="emit('update:visible', false)"
            />
        </template>
    </Dialog>
</template>

<style scoped>
.movimientos-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1rem;
}

.movimientos-header h3 {
    margin: 0;
    font-size: 1.05rem;
    font-weight: 600;
}

.movimientos-header span {
    display: block;
    margin-top: 0.25rem;
    font-size: 0.8rem;
    color: var(--p-text-muted-color);
}

.movimiento {
    display: flex;
    align-items: center;
    gap: 0.6rem;
}

.movimiento-icon {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.movimiento-icon.venta {
    background: var(--p-green-100);
    color: var(--p-green-600);
}

.monto {
    color: var(--p-primary-color);
}
</style>