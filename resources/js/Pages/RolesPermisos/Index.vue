<script setup>
import { computed, ref, watch } from "vue";
import { useForm } from "@inertiajs/vue3";
import AppLayout from "@/Layouts/AppLayout.vue";
import Card from "@/Components/Card.vue";
import Swal from "sweetalert2";

defineOptions({
    layout: AppLayout,
});

const props = defineProps({
    roles: {
        type: Array,
        default: () => [],
    },
    permisos: {
        type: Array,
        default: () => [],
    },
    nuevoRolId: {
        type: Number,
        default: null,
    },
});

const nombresGrupos = {
    dashboard: "General",
    ventas: "Ventas",
    clientes: "Clientes",
    cajas: "Cajas",
    "sesiones-caja": "Sesiones de caja",
    productos: "Productos",
    categorias: "Categorías",
    subcategorias: "Subcategorías",
    marcas: "Marcas",
    entradas: "Entradas",
    salidas: "Salidas",
    kardex: "Kardex",
    lotes: "Lotes",
    proveedores: "Proveedores",
    notificaciones: "Notificaciones",
    configuracion: "Configuración",
    tiendas: "Datos de la tienda",
    empresas: "Empresas",
    reportes: "Reportes",
    usuarios: "Usuarios",
    roles: "Roles y permisos",
};

const nombresAcciones = {
    index: "Acceder al módulo",
    ver: "Ver detalle",
    crear: "Crear",
    editar: "Editar",
    abrir: "Abrir",
    cerrar: "Cerrar",
};

const gruposPermisos = computed(() => {
    const grupos = new Map();

    props.permisos.forEach((permiso) => {
        const [grupo, accion] = permiso.name.split(".");

        if (!grupos.has(grupo)) {
            grupos.set(grupo, {
                nombre: nombresGrupos[grupo] ?? grupo,
                permisos: [],
            });
        }

        grupos.get(grupo).permisos.push({
            ...permiso,
            etiqueta: nombresAcciones[accion] ?? accion ?? permiso.name,
        });
    });

    return Array.from(grupos.values());
});

const rolSeleccionadoId = ref(props.nuevoRolId ?? props.roles[0]?.id ?? null);
const rolSeleccionado = computed(
    () => props.roles.find((rol) => rol.id === rolSeleccionadoId.value) ?? null,
);
const esAdministrador = computed(
    () => rolSeleccionado.value?.name === "Administrador",
);

const permisosForm = useForm({
    permissions: [],
});

watch(
    rolSeleccionado,
    (rol) => {
        permisosForm.permissions = rol
            ? rol.permissions.map((permiso) => permiso.id)
            : [];
        permisosForm.clearErrors();
    },
    { immediate: true },
);

const nuevoRolVisible = ref(false);
const nuevoRolForm = useForm({
    name: "",
    permissions: [],
});

const guardarPermisos = async () => {
    if (!rolSeleccionado.value || esAdministrador.value) return;

    permisosForm.put(
        route("roles-permisos.update", rolSeleccionado.value.id),
        {
            preserveScroll: true,
            onSuccess: async () => {
                await Swal.fire({
                    title: "¡Actualizado!",
                    text: "Los permisos del rol fueron actualizados.",
                    icon: "success",
                    confirmButtonText: "Aceptar",
                });
            },
        },
    );
};

const crearRol = () => {
    nuevoRolForm.post(route("roles-permisos.store"), {
        preserveScroll: true,
        onSuccess: async (page) => {
            rolSeleccionadoId.value = page.props.nuevoRolId;
            nuevoRolVisible.value = false;
            nuevoRolForm.reset();

            await Swal.fire({
                title: "¡Creado!",
                text: "El nuevo rol fue creado correctamente.",
                icon: "success",
                confirmButtonText: "Aceptar",
            });
        },
    });
};
</script>

<template>
    <v-container fluid>
        <Card>
            <template #title>
                <div class="card flex justify-content-center mb-3">
                    <Breadcrumb
                        :home="{ icon: 'pi pi-home' }"
                        :model="[{ label: 'Roles y permisos' }]"
                    />
                </div>
                <div
                    class="flex flex-col items-start justify-between gap-4 md:flex-row md:items-center"
                >
                    <div>
                        <h1 class="text-2xl font-bold text-gray-800">
                            Roles y permisos
                        </h1>
                        <p class="text-sm text-gray-500">
                            Crea roles y administra los permisos disponibles
                            para cada uno.
                        </p>
                    </div>
                    <Button
                        label="Nuevo rol"
                        icon="pi pi-plus"
                        @click="nuevoRolVisible = true"
                    />
                </div>
            </template>

            <template #content>
                <div class="grid grid-cols-1 gap-5 lg:grid-cols-[280px_1fr]">
                    <aside>
                        <h2
                            class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-500"
                        >
                            Roles del sistema
                        </h2>
                        <div class="flex flex-col gap-2">
                            <button
                                v-for="rol in props.roles"
                                :key="rol.id"
                                type="button"
                                class="rounded-xl border p-3 text-left transition"
                                :class="
                                    rol.id === rolSeleccionadoId
                                        ? 'border-[#7b1e3a] bg-[#fdf6f8]'
                                        : 'border-gray-200 hover:border-[#d8aab8]'
                                "
                                @click="rolSeleccionadoId = rol.id"
                            >
                                <span
                                    class="flex items-center justify-between gap-2 font-semibold text-gray-800"
                                >
                                    {{ rol.name }}
                                    <i
                                        v-if="rol.name === 'Administrador'"
                                        class="pi pi-lock text-sm text-gray-400"
                                        title="Rol protegido"
                                    />
                                </span>
                                <span class="mt-1 block text-xs text-gray-500">
                                    {{ rol.permissions.length }} permisos
                                </span>
                            </button>
                        </div>
                    </aside>

                    <section
                        v-if="rolSeleccionado"
                        class="min-w-0 rounded-xl border border-gray-200 p-4 md:p-5"
                    >
                        <div
                            class="mb-4 flex flex-col justify-between gap-3 sm:flex-row sm:items-center"
                        >
                            <div>
                                <h2 class="text-lg font-semibold text-gray-800">
                                    Permisos de {{ rolSeleccionado.name }}
                                </h2>
                                <p class="text-sm text-gray-500">
                                    {{
                                        esAdministrador
                                            ? "El rol administrador está protegido y siempre conserva el acceso completo."
                                            : "Selecciona las acciones que podrán realizar los usuarios con este rol."
                                    }}
                                </p>
                            </div>
                            <Button
                                v-if="!esAdministrador"
                                label="Guardar permisos"
                                icon="pi pi-save"
                                :loading="permisosForm.processing"
                                @click="guardarPermisos"
                            />
                        </div>

                        <small
                            v-if="permisosForm.errors.permissions"
                            class="mb-3 block text-red-500"
                        >
                            {{ permisosForm.errors.permissions }}
                        </small>

                        <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
                            <div
                                v-for="grupo in gruposPermisos"
                                :key="grupo.nombre"
                                class="rounded-lg border border-gray-100 bg-gray-50 p-4"
                            >
                                <h3
                                    class="mb-3 font-semibold text-gray-700"
                                >
                                    {{ grupo.nombre }}
                                </h3>
                                <div class="flex flex-col gap-3">
                                    <label
                                        v-for="permiso in grupo.permisos"
                                        :key="permiso.id"
                                        class="flex cursor-pointer items-center gap-2 text-sm text-gray-600"
                                    >
                                        <Checkbox
                                            v-model="permisosForm.permissions"
                                            :inputId="`permiso-${rolSeleccionado.id}-${permiso.id}`"
                                            :value="permiso.id"
                                            :disabled="esAdministrador"
                                        />
                                        <span>{{ permiso.etiqueta }}</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </section>

                    <div
                        v-else
                        class="rounded-xl border border-dashed border-gray-300 p-8 text-center text-gray-500"
                    >
                        No hay roles disponibles.
                    </div>
                </div>
            </template>
        </Card>

        <Dialog
            v-model:visible="nuevoRolVisible"
            modal
            header="Crear rol"
            :style="{ width: 'min(900px, 95vw)' }"
            @hide="nuevoRolForm.reset()"
        >
            <form class="flex flex-col gap-4" @submit.prevent="crearRol">
                <div class="flex flex-col gap-2">
                    <label for="role-name" class="font-medium">Nombre del rol</label>
                    <InputText
                        id="role-name"
                        v-model="nuevoRolForm.name"
                        autofocus
                        maxlength="255"
                        placeholder="Ej. Encargado de inventario"
                        :invalid="!!nuevoRolForm.errors.name"
                    />
                    <small
                        v-if="nuevoRolForm.errors.name"
                        class="text-red-500"
                    >
                        {{ nuevoRolForm.errors.name }}
                    </small>
                </div>

                <div>
                    <h3 class="mb-1 font-semibold text-gray-800">
                        Permisos iniciales
                    </h3>
                    <p class="mb-3 text-sm text-gray-500">
                        Puedes asignar o cambiar estos permisos más adelante.
                    </p>
                    <div
                        class="grid max-h-[50vh] grid-cols-1 gap-3 overflow-y-auto pr-2 md:grid-cols-2"
                    >
                        <div
                            v-for="grupo in gruposPermisos"
                            :key="grupo.nombre"
                            class="rounded-lg border border-gray-100 bg-gray-50 p-4"
                        >
                            <h4 class="mb-3 font-semibold text-gray-700">
                                {{ grupo.nombre }}
                            </h4>
                            <div class="flex flex-col gap-3">
                                <label
                                    v-for="permiso in grupo.permisos"
                                    :key="permiso.id"
                                    class="flex cursor-pointer items-center gap-2 text-sm text-gray-600"
                                >
                                    <Checkbox
                                        v-model="nuevoRolForm.permissions"
                                        :inputId="`nuevo-permiso-${permiso.id}`"
                                        :value="permiso.id"
                                    />
                                    <span>{{ permiso.etiqueta }}</span>
                                </label>
                            </div>
                        </div>
                    </div>
                    <small
                        v-if="nuevoRolForm.errors.permissions"
                        class="mt-2 block text-red-500"
                    >
                        {{ nuevoRolForm.errors.permissions }}
                    </small>
                </div>

                <div class="flex justify-end gap-2">
                    <Button
                        label="Cancelar"
                        severity="secondary"
                        variant="outlined"
                        type="button"
                        @click="nuevoRolVisible = false"
                    />
                    <Button
                        label="Crear rol"
                        icon="pi pi-plus"
                        type="submit"
                        :loading="nuevoRolForm.processing"
                    />
                </div>
            </form>
        </Dialog>
    </v-container>
</template>
