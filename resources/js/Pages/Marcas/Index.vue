<script setup>
import { ref, watch } from "vue";
import { useForm } from "@inertiajs/vue3";

import AppLayout from "@/Layouts/AppLayout.vue";
import DataTableBase from "@/Components/DataTableBase.vue";
import Card from "@/Components/Card.vue";
import Form from "./Form.vue";
import Swal from "sweetalert2";

defineOptions({
    layout: AppLayout,
});

const loading = ref(false);
const totalRecords = ref(0);
const currentPage = ref(1);

const mostrarForm = ref(false);
const marcaSeleccionada = ref(null);

const props = defineProps({
    marcas: {
        type: Array,
        default: () => [],
    },

    empresas: {
        type: Array,
        default: () => [],
    },
    todasLasMarcas: {
        type: Array,
        default: () => [],
    },
});

const asignacion = useForm({
    empresa_id: null,
    marcas: [],
});

watch(
    () => asignacion.empresa_id,
    (empresaId) => {
        const empresa = props.empresas.find(
            (item) => String(item.id) === String(empresaId),
        );

        asignacion.marcas = empresa
            ? props.todasLasMarcas
                  .filter(
                      (marca) =>
                          String(marca.empresa_id) === String(empresa.id),
                  )
                  .map((marca) => marca.id)
            : [];
        asignacion.clearErrors();
    },
);

const guardarAsignacion = () => {
    asignacion.post(route("marcas.asignar-empresa"), {
        preserveScroll: true,
        onSuccess: () => {
            Swal.fire({
                title: "¡Actualizado!",
                text: "Las marcas de la empresa fueron actualizadas correctamente.",
                icon: "success",
                confirmButtonText: "Aceptar",
            });
        },
    });
};

const columns = [
    {
        field: "nombre",
        header: "Nombre",
    },
    {
        field: "empresa.razon_social",
        header: "Razón Social",
        default: "Sin empresa",
    },
    {
        field: "activo",
        header: "Estado",
        type: "status",
    },
];

const home = ref({
    icon: "pi pi-home",
});

const items = ref([{ label: "Marcas" }]);

const nuevaMarca = () => {
    marcaSeleccionada.value = null;
    mostrarForm.value = true;
};

const editarMarca = (marca) => {
    marcaSeleccionada.value = marca;
    mostrarForm.value = true;
};

const formularioGuardado = async () => {
    mostrarForm.value = false;
    marcaSeleccionada.value = null;
};

const abrirForm = () => {
    marcaSeleccionada.value = null;
    mostrarForm.value = true;
};

const cerrarForm = () => {
    mostrarForm.value = false;
    marcaSeleccionada.value = null;
};
</script>

<template>
    <v-container fluid>
        <Card>
            <template #title>
                <div class="card flex justify-content-center">
                    <Breadcrumb :home="home" :model="items" />
                </div>
                <!-- TÍTULO -->
                <div
                    class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4"
                >
                    <div>
                        <h1 class="text-2xl font-bold text-gray-800">Marcas</h1>

                        <p class="text-sm text-gray-500">
                            Gestiona las marcas de los productos registrados
                        </p>
                    </div>
                </div>
            </template>
            <template #content>
                <section
                    class="mb-6 rounded-xl border border-gray-200 bg-gray-50 p-4"
                >
                    <h2 class="text-lg font-semibold text-gray-800">
                        Marcas por empresa
                    </h2>
                    <p class="mb-4 mt-1 text-sm text-gray-500">
                        Selecciona una empresa para asignarle varias marcas.
                        Puedes seleccionar todas o quitar marcas con la X.
                    </p>

                    <div class="grid grid-cols-1 items-start gap-4 md:grid-cols-2">
                        <div class="flex flex-col gap-1">
                            <label for="empresa_marcas" class="font-medium">
                                Empresa
                            </label>
                            <Select
                                id="empresa_marcas"
                                v-model="asignacion.empresa_id"
                                :options="props.empresas"
                                optionLabel="razon_social"
                                optionValue="id"
                                class="w-full"
                                placeholder="Selecciona una empresa"
                                emptyMessage="No hay empresas disponibles"
                                :invalid="!!asignacion.errors.empresa_id"
                            />
                            <small
                                v-if="asignacion.errors.empresa_id"
                                class="text-red-500"
                            >
                                {{ asignacion.errors.empresa_id }}
                            </small>
                        </div>

                        <div class="flex flex-col gap-1">
                            <label for="empresa_marcas_lista" class="font-medium">
                                Marcas
                            </label>
                            <MultiSelect
                                id="empresa_marcas_lista"
                                v-model="asignacion.marcas"
                                :options="props.todasLasMarcas"
                                optionLabel="nombre"
                                optionValue="id"
                                display="chip"
                                filter
                                showToggleAll
                                placeholder="Selecciona las marcas"
                                class="w-full"
                                :disabled="!asignacion.empresa_id"
                                :invalid="!!asignacion.errors.marcas"
                                emptyMessage="No hay marcas disponibles"
                            />
                            <small
                                v-if="asignacion.errors.marcas"
                                class="text-red-500"
                            >
                                {{ asignacion.errors.marcas }}
                            </small>
                            <small
                                v-else-if="asignacion.empresa_id && asignacion.errors['marcas.0']"
                                class="text-red-500"
                            >
                                {{ asignacion.errors["marcas.0"] }}
                            </small>
                        </div>
                    </div>

                    <div class="mt-4 flex justify-end">
                        <Button
                            label="Guardar marcas"
                            icon="pi pi-save"
                            :loading="asignacion.processing"
                            :disabled="!asignacion.empresa_id"
                            @click="guardarAsignacion"
                        />
                    </div>
                </section>

                <DataTableBase
                    title="Marcas"
                    :data="props.marcas"
                    :columns="columns"
                    :loading="loading"
                    :search-fields="['nombre']"
                    createRoute="marcas.create"
                    titleButton="Nueva marca"
                    :show-view="false"
                    :show-delete="false"
                    :showFilters="false"
                    :mostrarCrear="true"
                    @editar="editarMarca"
                    @nuevo="abrirForm"
                />
            </template>
        </Card>

        <Form
            v-model:visible="mostrarForm"
            :marca="marcaSeleccionada"
            :empresas="props.empresas"
            @saved="formularioGuardado"
            @cancel="cerrarForm"
        />
    </v-container>
</template>
