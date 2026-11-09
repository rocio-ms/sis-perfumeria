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
            ["tiene_descuento", "Sí/No; por defecto es Sí. Se aplicará el monto indicado en descuento", false],
            ["descuento", "Monto de descuento unitario; opcional. Si es mayor a cero, se aplicará automáticamente", false],
            ["fecha_vencimiento", "Fecha opcional (dd/mm/aaaa)", false],
            ["codigo_barras_lote", "Código de barras propio del lote; opcional", false],
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
        libro.calcProperties.fullCalcOnLoad = true;
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

        if (props.tipo === "entrada") {
            const columnaCompra = hoja.getColumn(
                encabezados.indexOf("precio_compra") + 1,
            ).letter;
            const columnaDescuento = encabezados.indexOf("descuento") + 1;
            const columnaTieneDescuento =
                encabezados.indexOf("tiene_descuento") + 1;

            for (let fila = 2; fila <= 501; fila += 1) {
                hoja.getCell(fila, columnaTieneDescuento).value = "Sí";
            }

            hoja.getColumn(columnaCompra).numFmt = "0.00";
            hoja.getColumn(columnaDescuento).numFmt = "0.00";
            hoja.getColumn(columnaTieneDescuento).eachCell((celda, numeroFila) => {
                if (numeroFila > 1 && numeroFila <= 501) {
                    celda.dataValidation = {
                        type: "list",
                        allowBlank: true,
                        formulae: ['"No,Sí"'],
                    };
                }
            });
        }

        const instrucciones = libro.addWorksheet("Instrucciones");
        instrucciones.addRow(["Guía rápida"]);
        instrucciones.addRow([
            props.tipo === "entrada"
                ? "Completa una fila por producto. El precio de venta se calcula automáticamente al importar."
                : "Completa una fila por producto y lote.",
        ]);
        if (props.tipo === "entrada") {
            instrucciones.addRow([
                "Precio de venta: el sistema lo calcula automáticamente al importar, usando el margen predeterminado del producto.",
            ]);
            instrucciones.addRow([
                "Tiene descuento: comienza en Sí. El sistema aplicará el monto de la columna descuento; si es mayor a cero, el descuento se activa automáticamente.",
            ]);
        }
        const filaEncabezado = instrucciones.rowCount + 1;
        instrucciones.addRow(["Columna", "Uso", "Requerida"]);
        configuracion.columnas.forEach(([nombre, uso, requerida]) => {
            instrucciones.addRow([nombre, uso, requerida ? "Sí" : "No"]);
        });
        instrucciones.mergeCells("A1:C1");
        instrucciones.getRow(1).font = {
            bold: true,
            size: 16,
            color: { argb: "FF1E3A8A" },
        };
        instrucciones.getRow(2).height = 30;
        for (let fila = 2; fila < filaEncabezado; fila += 1) {
            instrucciones.mergeCells(`A${fila}:C${fila}`);
            if (fila > 2) {
                instrucciones.getRow(fila).height = 36;
            }
            instrucciones.getRow(fila).alignment = {
                vertical: "middle",
                wrapText: true,
            };
        }
        instrucciones.getRow(filaEncabezado).font = {
            bold: true,
            color: { argb: "FFFFFFFF" },
        };
        instrucciones.getRow(filaEncabezado).fill = {
            type: "pattern",
            pattern: "solid",
            fgColor: { argb: "FF2563EB" },
        };
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
                        ? valor.result ?? null
                        : valor;
            });

            const tieneDatos = Object.entries(datos).some(([nombre, valor]) => {
                if (
                    props.tipo === "entrada" &&
                    nombre === "tiene_descuento" &&
                    ["no", "sí", "si", "false", "true", "0", "1"].includes(
                        String(valor ?? "").trim().toLocaleLowerCase(),
                    )
                ) {
                    return false;
                }

                return valor !== null && valor !== undefined && valor !== "";
            });

            if (tieneDatos) {
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
            <small v-if="tipo === 'entrada'" class="mt-1 block text-blue-700">
                El precio de venta se calcula automáticamente. El descuento inicia en Sí y se aplicará según el monto indicado.
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
