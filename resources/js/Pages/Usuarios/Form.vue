<script setup>
import { computed, watch } from "vue";
import { useForm } from "@inertiajs/vue3";

const props = defineProps({
    visible: {
        type: Boolean,
        default: false,
    },

    usuario: {
        type: Object,
        default: null,
    },

    roles: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(["update:visible", "saved", "cancel"]);

//Datos del formulario de usuario

const form = useForm({
    name: "",
    dni: "",
    username: "",
    password: "",
    password_confirmation: "",
    activo: true,
    rol: null,
});

//Editando el form

const editando = computed(() => {
    return !!props.usuario;
});

//Título del form

const titulo = computed(() => {
    return editando.value ? "Editar usuario" : "Nuevo usuario";
});

//Subtítulo del form

const subtitulo = computed(() => {
    return editando.value
        ? "Actualiza la información del usuario"
        : "Registra un nuevo usuario para acceder al sistema";
});

///Limpiar formulario

const limpiarFormulario = () => {
    form.reset();

    form.name = "";
    form.dni = "";
    form.username = "";
    form.email = "";
    form.password = "";
    form.password_confirmation = "";
    form.activo = true;
    form.rol = null;

    form.clearErrors();
};

//Cargar usuario en el formulario  si está en edición

const cargarUsuario = () => {
    if (!props.usuario) {
        limpiarFormulario();
        return;
    }

    form.name = props.usuario.name ?? "";
    form.dni = props.usuario.dni ?? "";
    form.username = props.usuario.username ?? "";
    form.email = props.usuario.email ?? "";

    form.password = "";
    form.password_confirmation = "";

    form.activo = Boolean(props.usuario.activo);

    form.rol = props.usuario.roles?.length ? props.usuario.roles[0].id : null;

    form.clearErrors();
};

watch(
    () => props.visible,
    (visible) => {
        if (visible) {
            cargarUsuario();
        }
    },
);

watch(
    () => props.usuario,
    () => {
        if (props.visible) {
            cargarUsuario();
        }
    },
);

//Cerrar form

const cerrar = () => {
    if (form.processing) {
        return;
    }

    emit("update:visible", false);
    emit("cancel");
};

//Guardar usuario

const guardar = () => {
    if (editando.value) {
        form.put(route("usuarios.update", props.usuario.id), {
            preserveScroll: true,

            onSuccess: () => {
                emit("saved", "actualizado");
            },
        });

        return;
    }

    form.post(route("usuarios.store"), {
        preserveScroll: true,

        onSuccess: () => {
            emit("saved", "registrado");
        },
    });
};
</script>

<template>
    <Dialog
        :visible="visible"
        modal
        :header="titulo"
        :style="{ width: '650px' }"
        :closable="!form.processing"
        :closeOnEscape="!form.processing"
        @update:visible="cerrar"
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

        <div class="flex flex-col gap-5 mt-3">
            <div class="flex flex-col gap-1">
                <FloatLabel variant="on">
                    <InputText
                        id="name"
                        v-model="form.name"
                        class="w-full"
                        :invalid="!!form.errors.name"
                        autofocus
                    />
                    <label for="name">
                        Nombre completo <span class="text-red-500">*</span>
                    </label>
                </FloatLabel>
                <small v-if="form.errors.name" class="text-red-500">
                    {{ form.errors.name }}
                </small>
            </div>

            <div class="grid grid-cols-2 gap-5">
                <div class="flex flex-col gap-1">
                    <FloatLabel variant="on">
                        <InputText
                            id="dni"
                            v-model="form.dni"
                            class="w-full"
                            maxlength="8"
                            :invalid="!!form.errors.dni"
                        />

                        <label for="dni">
                            DNI <span class="text-red-500">*</span>
                        </label>
                    </FloatLabel>

                    <small
                        v-if="
                            form.dni &&
                            form.dni.length !== 8 &&
                            !form.errors.dni
                        "
                        class="text-red-500 text-xs"
                    >
                        El DNI debe tener 8 dígitos
                    </small>

                    <small v-if="form.errors.dni" class="text-red-500">
                        {{ form.errors.dni }}
                    </small>
                </div>
                <div class="flex flex-col gap-1">
                    <FloatLabel variant="on">
                        <InputText
                            id="username"
                            v-model="form.username"
                            class="w-full"
                            :invalid="!!form.errors.username"
                        />

                        <label for="username">
                            Usuario <span class="text-red-500">*</span>
                        </label>
                    </FloatLabel>
                    <small v-if="form.errors.username" class="text-red-500">
                        {{ form.errors.username }}
                    </small>
                </div>
            </div>

            <div class="flex flex-col gap-1">
                <FloatLabel variant="on">
                    <Select
                        id="rol"
                        v-model="form.rol"
                        :options="roles"
                        optionLabel="name"
                        optionValue="id"
                        class="w-full"
                        :invalid="!!form.errors.rol"
                    />

                    <label for="rol">
                        Rol <span class="text-red-500">*</span></label
                    >
                </FloatLabel>
                <small v-if="form.errors.rol" class="text-red-500">
                    {{ form.errors.rol }}
                </small>
            </div>

            <div class="grid grid-cols-2 gap-5">
                <div class="flex flex-col gap-1">
                    <FloatLabel variant="on">
                        <Password
                            inputId="password"
                            v-model="form.password"
                            class="w-full"
                            inputClass="w-full"
                            :feedback="false"
                            toggleMask
                            :invalid="!!form.errors.password"
                        />

                        <label for="password">
                            Contraseña
                            <span v-if="!editando" class="text-red-500">*</span>
                            <span v-if="editando" class="text-xs text-gray-400">
                                (opcional)
                            </span>
                        </label>
                    </FloatLabel>

                    <small
                        v-if="
                            form.password &&
                            form.password.length < 8 &&
                            !form.errors.password
                        "
                        class="text-red-500 text-xs"
                    >
                        La contraseña debe tener al menos 8 caracteres.
                    </small>

                    <small v-if="form.errors.password" class="text-red-500">
                        {{ form.errors.password }}
                    </small>
                </div>
                <div class="flex flex-col gap-1">
                    <FloatLabel variant="on">
                        <Password
                            inputId="password-confirmation"
                            v-model="form.password_confirmation"
                            class="w-full"
                            inputClass="w-full"
                            :feedback="false"
                            toggleMask
                            :invalid="!!form.errors.password_confirmation"
                        />

                        <label for="password-confirmation">
                            Confirmar contraseña
                            <span v-if="!editando" class="text-red-500">*</span>
                        </label>
                    </FloatLabel>

                    <small
                        v-if="
                            form.password_confirmation &&
                            form.password !== form.password_confirmation &&
                            !form.errors.password_confirmation
                        "
                        class="text-red-500 text-xs"
                    >
                        Las contraseñas no coinciden
                    </small>

                    <small
                        v-if="form.errors.password_confirmation"
                        class="text-red-500"
                        >{{ form.errors.password_confirmation }}</small
                    >
                </div>
            </div>

            <div class="flex items-center justify-between">
                <div>
                    <label for="activo" class="font-medium block">
                        Estado del usuario
                    </label>

                    <small class="text-gray-500">
                        {{
                            form.activo
                                ? "El usuario puede acceder al sistema"
                                : "El usuario no puede acceder al sistema"
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
                :label="editando ? 'Actualizar' : 'Guardar'"
                icon="pi pi-save"
                :loading="form.processing"
                @click="guardar"
            />
        </template>
    </Dialog>
</template>
