<script setup>
import { ref, computed, watch } from "vue";
import { useForm } from "@inertiajs/vue3";

const props = defineProps({
    producto: {
        type: Object,
        default: null,
    },

    categorias: {
        type: Array,
        default: () => [],
    },

    marcas: {
        type: Array,
        default: () => [],
    },
    codigoInicial: {
        type: String,
        default: "000001",
    },
});
const visible = defineModel("visible");

const emit = defineEmits(["update:visible", "saved", "cancel"]);

const form = useForm({
    codigo: "",
    codigo_barras: "",
    nombre: "",
    descripcion: "",
    ubicacion: "",
    unidad_medida: null,
    tiene_descuento: false,
    descuento: null,
    stock_minimo: null,
    stock_actual: 0,
    volumen_ml: 0,
    imagen_url: null,
    activo: true,
    categoria_id: null,
    marca_id: null,
    precio_venta: null,
});
const imagenPreview = ref("/images/producto_default.svg");

const unidadesMedida = [
    { label: "Unidad", value: "unidad" },
    { label: "Pieza", value: "pieza" },
    { label: "Juego", value: "juego" },
    { label: "Set", value: "set" },
    { label: "Par", value: "par" },
    { label: "Colección", value: "coleccion" },
    { label: "Paquete", value: "paquete" },
    { label: "Mililitros", value: "ml" },
    { label: "Tubo", value: "tubo" },
    { label: "Equipo", value: "equipo" },
    { label: "Docena", value: "docena" },
    { label: "Frasco", value: "frasco" },
    { label: "Bolsa", value: "bolsita" },
    { label: "Litro", value: "l" },
    { label: "Gramos", value: "gr" },
    { label: "Kit", value: "kit" },
    { label: "Caja", value: "cajita" },
];
const esEdicion = computed(() => {
    return props.producto !== null;
});

const titulo = computed(() => {
    return esEdicion.value ? "Editar producto" : "Nuevo producto";
});

const subtitulo = computed(() => {
    return esEdicion.value
        ? "Actualiza la información del producto"
        : "Registra la información del producto para incorporarlo en tu inventario";
});

const descripcionLength = computed(() => form.nombre?.length ?? 0);

//Limpiar form
const limpiarFormulario = () => {
    form.reset();
    form.clearErrors();

    form.codigo = props.codigoInicial;
    form.codigo_barras = "";
    form.nombre = "";
    form.descripcion = "";
    form.ubicacion = "";
    form.unidad_medida = null;
    form.precio_venta = null;

    form.tiene_descuento = false;
    form.descuento = null;

    form.stock_minimo = null;
    form.stock_actual = 0;

    form.volumen_ml = 0;

    form.imagen_url = null;

    form.activo = true;

    form.categoria_id = null;
    form.marca_id = null;

    imagenPreview.value = "/images/producto_default.svg";
};

//Cargar datos de edición
const cargarProducto = (producto) => {
    form.clearErrors();

    form.codigo = producto.codigo ?? "";
    form.codigo_barras = producto.codigo_barras ?? "";
    form.nombre = producto.nombre ?? "";
    form.descripcion = producto.descripcion ?? "";
    form.ubicacion = producto.ubicacion ?? "";
    form.unidad_medida = producto.unidad_medida ?? null;
    form.precio_venta = producto.precio_venta ?? null;

    form.precio_venta = Number(
        producto.precio_actual?.precio_venta ?? producto.precio_venta ?? 0,
    );

    form.tiene_descuento = Boolean(
        producto.precio_actual?.tiene_descuento ?? false,
    );

    form.descuento = Number(producto.precio_actual?.descuento ?? 0);

    form.stock_minimo = producto.stock_minimo ?? null;
    form.stock_actual = producto.stock_actual ?? 0;

    form.volumen_ml = producto.volumen_ml ?? 0;

    // Al editar no reemplazamos la imagen hasta que el usuario seleccione otra
    form.imagen_url = null;

    imagenPreview.value = producto.imagen_url
        ? `/storage/${producto.imagen_url}`
        : "/images/producto_default.svg";

    form.activo = Boolean(producto.activo);

    form.categoria_id = producto.categoria_id ?? null;
    form.marca_id = producto.marca_id ?? null;
};

// Cuando cambia el producto seleccionado
watch(
    () => props.producto,
    (producto) => {
        if (producto) {
            cargarProducto(producto);
        } else {
            limpiarFormulario();
        }
    },
    {
        immediate: true,
    },
);

// Cuando se abre el Dialog
watch(visible, (nuevoValor) => {
    if (!nuevoValor) {
        return;
    }

    if (props.producto) {
        cargarProducto(props.producto);
    } else {
        limpiarFormulario();
    }
});

//Guardar

const guardar = () => {
    if (esEdicion.value) {
        form.transform((data) => ({
            ...data,
            _method: "PUT",
        })).post(route("productos.update", props.producto.id), {
            forceFormData: true,

            onSuccess: () => {
                limpiarFormulario();

                visible.value = false;

                emit("saved", "actualizado");
            },
        });
    } else {
        form.post(route("productos.store"), {
            forceFormData: true,

            onSuccess: () => {
                limpiarFormulario();

                visible.value = false;

                emit("saved", "guardado");
            },
        });
    }
};

watch(
    () => form.unidad_medida,
    (unidad) => {
        const unidadesConContenido = ["ml", "l", "gr", "frasco", "botella"];

        if (!unidadesConContenido.includes(unidad)) {
            form.volumen_ml = 0;
        }
    },
);

const seleccionarImagen = (event) => {
    const archivo = event.files?.[0];

    if (!archivo) {
        return;
    }

    form.imagen_url = archivo;

    imagenPreview.value = URL.createObjectURL(archivo);
};

const requiereVolumen = computed(() => {
    return ["frasco", "botella", "ml", "l", "gr"].includes(form.unidad_medida);
});

const unidadContenido = computed(() => {
    const unidades = {
        ml: "ml",
        l: "L",
        gr: "gr",
        frasco: "ml",
        botella: "ml",
    };

    return unidades[form.unidad_medida] ?? "";
});

const cerrar = () => {
    limpiarFormulario();

    visible.value = false;

    emit("cancel");
};
</script>

<template>
    <Dialog
        v-model:visible="visible"
        :header="titulo"
        modal
        :style="{ width: '850px' }"
    >
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

        <div class="grid grid-cols-2 gap-x-5 gap-y-5 mt-3">
            <!-- Código interno -->
            <div class="flex flex-col gap-1">
                <FloatLabel variant="on">
                    <InputText
                        id="codigo"
                        v-model="form.codigo"
                        class="w-full"
                        disabled
                    />

                    <label for="codigo"> Código </label>
                </FloatLabel>

                <small class="text-gray-500"> Generado automáticamente </small>
            </div>

            <!-- Código de barras -->
            <div class="flex flex-col gap-1">
                <FloatLabel variant="on">
                    <InputText
                        id="codigo_barras"
                        v-model="form.codigo_barras"
                        class="w-full"
                        :invalid="!!form.errors.codigo_barras"
                    />

                    <label for="codigo_barras"> Código de barras </label>
                </FloatLabel>

                <small v-if="form.errors.codigo_barras" class="text-red-500">
                    {{ form.errors.codigo_barras }}
                </small>
            </div>

            <!-- Nombre -->

            <div class="flex flex-col gap-1 col-span-2">
                <FloatLabel variant="on">
                    <Textarea
                        id="descripcion"
                        v-model="form.nombre"
                        class="w-full"
                        rows="3"
                        :invalid="!!form.errors.nombre"
                    />

                    <label for="descripcion">
                        Descripción <span class="text-red-500">*</span>
                    </label>
                </FloatLabel>

                <div class="flex justify-end">
                    <small class="text-gray-400">
                        {{ descripcionLength }} / 255
                    </small>
                </div>

                <small v-if="form.errors.nombre" class="text-red-500">
                    {{ form.errors.nombre }}
                </small>
            </div>
            <!-- Categoría -->
            <div class="flex flex-col gap-1">
                <FloatLabel variant="on">
                    <Select
                        id="categoria_id"
                        v-model="form.categoria_id"
                        :options="categorias"
                        optionLabel="nombre"
                        optionValue="id"
                        class="w-full"
                        :invalid="!!form.errors.categoria_id"
                    />

                    <label for="categoria_id">
                        Categoría
                        <span class="text-red-500">*</span>
                    </label>
                </FloatLabel>

                <small v-if="form.errors.categoria_id" class="text-red-500">
                    {{ form.errors.categoria_id }}
                </small>
            </div>

            <!-- Ubicación -->
            <div class="flex flex-col gap-1">
                <FloatLabel variant="on">
                    <InputText
                        id="ubicacion"
                        v-model="form.ubicacion"
                        class="w-full"
                        :invalid="!!form.errors.ubicacion"
                    />

                    <label for="ubicacion">Ubicación</label>
                </FloatLabel>

                <small v-if="form.errors.ubicacion" class="text-red-500">
                    {{ form.errors.ubicacion }}
                </small>
            </div>

            <!-- Marca -->
            <div class="flex flex-col gap-1">
                <FloatLabel variant="on">
                    <Select
                        id="marca_id"
                        v-model="form.marca_id"
                        :options="marcas"
                        optionLabel="nombre"
                        optionValue="id"
                        class="w-full"
                        :invalid="!!form.errors.marca_id"
                    />

                    <label for="marca_id">
                        Marca <span class="text-red-500">*</span>
                    </label>
                </FloatLabel>

                <small v-if="form.errors.marca_id" class="text-red-500">
                    {{ form.errors.marca_id }}
                </small>
            </div>

            <!-- Unidad de medida -->
            <div class="flex flex-col gap-1">
                <FloatLabel variant="on">
                    <Select
                        id="unidad_medida"
                        v-model="form.unidad_medida"
                        :options="unidadesMedida"
                        optionLabel="label"
                        optionValue="value"
                        class="w-full"
                        :invalid="!!form.errors.unidad_medida"
                    />

                    <label for="unidad_medida">
                        Unidad de medida
                        <span class="text-red-500">*</span>
                    </label>
                </FloatLabel>

                <small v-if="form.errors.unidad_medida" class="text-red-500">
                    {{ form.errors.unidad_medida }}
                </small>
            </div>

            <!-- Precio venta -->
            <!-- <div class="flex flex-col gap-1">
                <FloatLabel variant="on">
                    <InputNumber
                        id="precio_venta"
                        v-model="form.precio_venta"
                        class="w-full"
                        mode="currency"
                        currency="PEN"
                        locale="es-PE"
                        :min="0"
                        :minFractionDigits="2"
                        :invalid="!!form.errors.precio_venta"
                    />

                    <label for="precio_venta">
                        Precio de venta
                        <span class="text-red-500">*</span>
                    </label>
                </FloatLabel>

                <small v-if="form.errors.precio_venta" class="text-red-500">
                    {{ form.errors.precio_venta }}
                </small>
            </div> -->

            <!-- <div class="flex items-center gap-3 col-span-2">
                <Checkbox
                    v-model="form.tiene_descuento"
                    inputId="tiene_descuento"
                    :binary="true"
                /> -->

            <!-- <div>
                    <label
                        for="tiene_descuento"
                        class="font-medium cursor-pointer"
                    >
                        Aplicar descuento
                    </label>

                    <small class="block text-gray-500">
                        Activa esta opción si el producto tendrá un descuento.
                    </small>
                </div> -->
            <!-- </div> -->

            <!-- <div v-if="form.tiene_descuento" class="flex flex-col gap-1">
                <FloatLabel variant="on">
                    <InputNumber
                        id="descuento"
                        v-model="form.descuento"
                        class="w-full"
                        mode="currency"
                        currency="PEN"
                        locale="es-PE"
                        :min="0"
                        :max="form.precio_venta ?? 0"
                        :minFractionDigits="2"
                        :invalid="!!form.errors.descuento"
                    />

                    <label for="descuento">
                        Monto de descuento
                        <span class="text-red-500">*</span>
                    </label>
                </FloatLabel>

                <small v-if="form.errors.descuento" class="text-red-500">
                    {{ form.errors.descuento }}
                </small>
            </div> -->

            <!-- Stock mínimo -->
            <div class="flex flex-col gap-1">
                <FloatLabel variant="on">
                    <InputNumber
                        id="stock_minimo"
                        v-model="form.stock_minimo"
                        class="w-full"
                        :min="1"
                        :invalid="!!form.errors.stock_minimo"
                    />

                    <label for="stock_minimo">
                        Stock mínimo<span class="text-red-500">*</span>
                    </label>
                </FloatLabel>

                <small v-if="form.errors.stock_minimo" class="text-red-500">
                    {{ form.errors.stock_minimo }}
                </small>
            </div>

            <!-- Stock actual -->
            <!-- <div class="flex flex-col gap-1">
                <FloatLabel variant="on">
                    <InputNumber
                        id="stock_actual"
                        v-model="form.stock_actual"
                        class="w-full"
                        :disabled="true"
                    />

                    <label for="stock_actual"> Stock actual </label>
                </FloatLabel>

                <small class="text-gray-500">
                    Se modifica mediante entradas y salidas.
                </small>
            </div> -->

            <!-- Contenido según la unidad de medida -->
            <div v-if="requiereVolumen" class="flex flex-col gap-1">
                <FloatLabel variant="on">
                    <InputNumber
                        id="volumen_ml"
                        v-model="form.volumen_ml"
                        class="w-full"
                        :suffix="` ${unidadContenido}`"
                        :min="0"
                        :invalid="!!form.errors.volumen_ml"
                    />

                    <label for="volumen_ml"> Contenido </label>
                </FloatLabel>

                <small v-if="form.errors.volumen_ml" class="text-red-500">
                    {{ form.errors.volumen_ml }}
                </small>
            </div>

            <!-- Precio de venta y descuento - SOLO EN EDICIÓN -->
            <div v-if="esEdicion" class="col-span-2 grid grid-cols-2 gap-5">
                <!-- Precio de venta -->
                <div class="flex flex-col gap-1">
                    <FloatLabel variant="on">
                        <InputNumber
                            id="precio_venta"
                            v-model="form.precio_venta"
                            class="w-full"
                            mode="currency"
                            currency="PEN"
                            locale="es-PE"
                            :min="0"
                            :minFractionDigits="2"
                            :maxFractionDigits="2"
                            :invalid="!!form.errors.precio_venta"
                        />

                        <label for="precio_venta"> Precio de venta </label>
                    </FloatLabel>

                    <small v-if="form.errors.precio_venta" class="text-red-500">
                        {{ form.errors.precio_venta }}
                    </small>
                </div>

                <!-- Descuento -->
                <div class="flex items-center gap-3">
                    <!-- Checkbox -->
                    <div class="flex items-center gap-2 shrink-0">
                        <Checkbox
                            v-model="form.tiene_descuento"
                            inputId="tiene_descuento"
                            :binary="true"
                        />

                        <label
                            for="tiene_descuento"
                            class="font-medium text-gray-700 cursor-pointer whitespace-nowrap"
                        >
                            Tiene descuento
                        </label>
                    </div>

                    <!-- Monto descuento -->
                    <div v-if="form.tiene_descuento" class="flex-1">
                        <FloatLabel variant="on">
                            <InputNumber
                                id="descuento"
                                v-model="form.descuento"
                                class="w-full"
                                mode="currency"
                                currency="PEN"
                                locale="es-PE"
                                :min="0"
                                :max="form.precio_venta ?? 0"
                                :minFractionDigits="2"
                                :maxFractionDigits="2"
                                :invalid="!!form.errors.descuento"
                            />

                            <label for="descuento"> Descuento </label>
                        </FloatLabel>
                    </div>
                </div>

                <!-- Error descuento -->
                <small
                    v-if="form.errors.descuento"
                    class="col-span-2 text-red-500"
                >
                    {{ form.errors.descuento }}
                </small>
            </div>

            <!-- Imagen del producto -->
            <div class="flex flex-col gap-2 col-span-2">
                <label class="font-medium"> Imagen del producto </label>

                <div class="grid grid-cols-[140px_1fr] gap-4 items-center">
                    <!-- Preview -->
                    <div
                        class="flex items-center justify-center w-32 h-32 border border-gray-200 rounded-lg bg-gray-50 overflow-hidden"
                    >
                        <img
                            :src="imagenPreview"
                            alt="Vista previa del producto"
                            class="w-full h-full object-contain"
                        />
                    </div>

                    <!-- FileUpload -->
                    <div>
                        <FileUpload
                            mode="basic"
                            name="imagen_url"
                            accept="image/*"
                            :maxFileSize="2000000"
                            chooseLabel="Seleccionar imagen"
                            chooseIcon="pi pi-image"
                            :auto="false"
                            customUpload
                            @select="seleccionarImagen"
                        />

                        <small class="block text-gray-500 mt-2">
                            JPG, PNG, WEBP o SVG. Máximo 2 MB.
                        </small>

                        <small
                            v-if="form.errors.imagen_url"
                            class="block text-red-500 mt-1"
                        >
                            {{ form.errors.imagen_url }}
                        </small>
                    </div>
                </div>
            </div>
            <!-- Estado -->
            <div class="flex items-center justify-between col-span-2 pt-2">
                <div>
                    <label for="activo" class="font-medium block">
                        Estado del producto
                    </label>

                    <small class="text-gray-500">
                        {{
                            form.activo
                                ? "El producto está activo"
                                : "El producto está inactivo"
                        }}
                    </small>
                </div>

                <ToggleSwitch v-model="form.activo" inputId="activo" />
            </div>
        </div>
        <template #footer>
            <Button
                label="Cancelar"
                severity="secondary"
                variant="outlined"
                @click="cerrar"
            />

            <Button
                :label="esEdicion ? 'Actualizar' : 'Guardar'"
                icon="pi pi-save"
                :loading="form.processing"
                @click="guardar"
            />
        </template>
    </Dialog>
</template>
