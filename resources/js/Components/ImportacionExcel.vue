<script setup>
import { ref } from "vue";
import ExcelJS from "exceljs";
import Swal from "sweetalert2";

const props = defineProps({
    tipo: {
        type: String,
        required: true,
        validator: (value) => ["entrada", "salida"].includes(value),
    },
    disabled: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(["imported"]);
const archivoInput = ref(null);

const configuraciones = {
    entrada: {
        hoja: "Entradas",
        nombreArchivo: "plantilla_entradas.xlsx",
        columnas: [
            ["codigo_producto", "Código interno o código de barras del producto", true],
            ["cantidad", "Unidades que ingresan", true],
            ["precio_compra", "Costo unitario", true],
            ["precio_venta", "Precio de venta unitario", true],
            ["tiene_descuento", "Sí/No; opcional, por defecto No", false],
            ["descuento", "Descuento unitario; opcional", false],
            ["fecha_vencimiento", "Fecha opcional (dd/mm/aaaa)", false],
        ],
    },
    salida: {
        hoja: "Salidas",
        nombreArchivo: "plantilla_salidas.xlsx",
        columnas: [
            ["codigo_producto", "Código interno o código de barras del producto", true],
            ["numero_lote", "Número del lote con stock (ej. LOT-000001)", true],
            ["cantidad", "Unidades que salen", true],
        ],
    },
};

const configuracion = configuraciones[props.tipo];
const normalizarEncabezado = (valor) =>
    String(valor ?? "")
        .trim()
        .toLocaleLowerCase()
        .replace(/^\uFEFF/, "");

const descargarPlantilla = async () => {
    try {
        const libro = new ExcelJS.Workbook();
        const hoja = libro.addWorksheet(configuracion.hoja);
        const encabezados = configuracion.columnas.map(([nombre]) => nombre);

        hoja.addRow(encabezados);
        hoja.getRow(1).font = { bold: true, color: { argb: "FFFFFFFF" } };
        hoja.getRow(1).fill = {
            type: "pattern",
            pattern: "solid",
            fgColor: { argb: "FF2563EB" },
        };
        hoja.views = [{ state: "frozen", ySplit: 1 }];
        hoja.autoFilter = {
            from: { row: 1, column: 1 },
            to: { row: 1, column: encabezados.length },
        };
        hoja.columns = encabezados.map((nombre) => ({
            key: nombre,
            width: Math.max(nombre.length + 4, 22),
        }));
        hoja.getColumn(1).numFmt = "@";

        const instrucciones = libro.addWorksheet("Instrucciones");
        instrucciones.addRow(["Columna", "Uso", "Requerida"]);
        configuracion.columnas.forEach(([nombre, uso, requerida]) => {
            instrucciones.addRow([nombre, uso, requerida ? "Sí" : "No"]);
        });
        instrucciones.getRow(1).font = { bold: true };
        instrucciones.columns = [
            { width: 26 },
            { width: 62 },
            { width: 14 },
        ];

        const contenido = await libro.xlsx.writeBuffer();
        const archivo = new Blob([contenido], {
            type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
        });
        const url = URL.createObjectURL(archivo);
        const enlace = document.createElement("a");

        enlace.href = url;
        enlace.download = configuracion.nombreArchivo;
        enlace.click();
        URL.revokeObjectURL(url);
    } catch (error) {
        await Swal.fire({
            title: "No se pudo descargar la plantilla",
            text: error.message || "Ocurrió un error al generar el archivo.",
            icon: "error",
            confirmButtonText: "Aceptar",
        });
    }
};

const abrirSelectorArchivo = () => {
    archivoInput.value?.click();
};

const leerArchivo = async (event) => {
    const archivo = event.target.files?.[0];
    event.target.value = "";

    if (!archivo) {
        return;
    }

    if (archivo.size > 10 * 1024 * 1024) {
        await Swal.fire({
            title: "Archivo demasiado grande",
            text: "El tamaño máximo permitido es 10 MB.",
            icon: "warning",
            confirmButtonText: "Aceptar",
        });
        return;
    }

    if (!archivo.name.toLocaleLowerCase().endsWith(".xlsx")) {
        await Swal.fire({
            title: "Formato no compatible",
            text: "Sube un archivo .xlsx. Puedes descargar la plantilla desde este formulario.",
            icon: "warning",
            confirmButtonText: "Aceptar",
        });
        return;
    }

    try {
        const libro = new ExcelJS.Workbook();
        await libro.xlsx.load(await archivo.arrayBuffer());

        const hoja = libro.worksheets[0];
        if (!hoja || hoja.rowCount < 2) {
            throw new Error("El archivo no contiene filas de datos.");
        }

        const posiciones = new Map();
        hoja.getRow(1).eachCell({ includeEmpty: true }, (celda, columna) => {
            const encabezado = normalizarEncabezado(celda.value);
            if (encabezado) {
                posiciones.set(encabezado, columna);
            }
        });

        const faltantes = configuracion.columnas
            .filter(([nombre, , requerida]) => requerida && !posiciones.has(nombre))
            .map(([nombre]) => nombre);

        if (faltantes.length) {
            throw new Error(
                `Faltan columnas requeridas: ${faltantes.join(", ")}. Usa la plantilla descargable.`,
            );
        }

        const filas = [];
        hoja.eachRow({ includeEmpty: false }, (fila, numeroFila) => {
            if (numeroFila === 1) {
                return;
            }

            const datos = {};
            configuracion.columnas.forEach(([nombre]) => {
                const columna = posiciones.get(nombre);
                const valor = columna ? fila.getCell(columna).value : null;
                datos[nombre] =
                    valor && typeof valor === "object" && "result" in valor
                        ? valor.result
                        : valor;
            });

            if (Object.values(datos).some((valor) => valor !== null && valor !== "")) {
                filas.push({ numero_fila: numeroFila, ...datos });
            }
        });

        if (!filas.length) {
            throw new Error("El archivo no contiene filas con datos.");
        }

        if (filas.length > 500) {
            throw new Error("La plantilla permite importar hasta 500 filas por archivo.");
        }

        emit("imported", filas);
    } catch (error) {
        await Swal.fire({
            title: "No se pudo leer el archivo",
            text: error.message || "Verifica que el Excel sea válido y usa la plantilla descargable.",
            icon: "error",
            confirmButtonText: "Aceptar",
        });
    }
};
</script>

<template>
    <div class="flex flex-wrap items-center gap-2 rounded-lg border border-blue-100 bg-blue-50 p-3">
        <div class="mr-auto">
            <p class="m-0 text-sm font-medium text-blue-900">
                Carga masiva desde Excel
            </p>
            <small class="text-blue-700">
                La importación agrega productos al formulario; revisa los datos y guarda normalmente.
            </small>
        </div>
        <input
            ref="archivoInput"
            class="hidden"
            type="file"
            accept=".xlsx"
            @change="leerArchivo"
        />
        <Button
            type="button"
            label="Descargar plantilla"
            icon="pi pi-download"
            severity="secondary"
            outlined
            :disabled="disabled"
            @click="descargarPlantilla"
        />
        <Button
            type="button"
            label="Subir Excel"
            icon="pi pi-upload"
            :disabled="disabled"
            @click="abrirSelectorArchivo"
        />
    </div>
</template>
