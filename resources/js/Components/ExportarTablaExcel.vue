<script setup>
import { ref } from "vue";
import Button from "primevue/button";
import Swal from "sweetalert2";

const props = defineProps({
    filas: { type: Array, required: true },
    columnas: { type: Array, required: true },
    hoja: { type: String, required: true },
    archivo: { type: String, required: true },
});

const exportando = ref(false);

async function exportar() {
    exportando.value = true;

    try {
        const { default: ExcelJS } = await import("exceljs");
        const libro = new ExcelJS.Workbook();
        const hoja = libro.addWorksheet(props.hoja, {
            views: [{ state: "frozen", ySplit: 1 }],
        });

        hoja.columns = props.columnas.map(({ titulo, ancho, formato }) => ({
            header: titulo,
            width: ancho ?? Math.max(titulo.length + 4, 16),
            style: { numFmt: formato ?? "General" },
        }));
        hoja.getRow(1).font = { bold: true };

        for (const fila of props.filas) {
            hoja.addRow(
                props.columnas.map(({ campo }) =>
                    typeof campo === "function" ? campo(fila) : fila[campo],
                ),
            );
        }

        hoja.autoFilter = {
            from: { row: 1, column: 1 },
            to: { row: hoja.rowCount, column: props.columnas.length },
        };

        const contenido = await libro.xlsx.writeBuffer();
        const archivo = new Blob([contenido], {
            type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
        });
        const url = URL.createObjectURL(archivo);
        const enlace = document.createElement("a");
        enlace.href = url;
        enlace.download = `${props.archivo}.xlsx`;
        enlace.click();
        setTimeout(() => URL.revokeObjectURL(url), 1000);
    } catch {
        Swal.fire({
            title: "No se pudo exportar el reporte",
            text: "Vuelve a intentar la descarga.",
            icon: "error",
        });
    } finally {
        exportando.value = false;
    }
}
</script>

<template>
    <Button
        type="button"
        label="Exportar Excel"
        :aria-label="`Exportar ${hoja} a Excel`"
        icon="pi pi-file-excel"
        severity="success"
        size="small"
        outlined
        :loading="exportando"
        :disabled="!filas.length"
        @click="exportar"
    />
</template>
