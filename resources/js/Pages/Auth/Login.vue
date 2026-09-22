<template>
    <div class="login-page flex justify-center items-center min-h-screen">
        <Card class="login-card" style="width: 940px; overflow: hidden">
            <template #content>
                <div class="flex">
                    <!-- Panel de marca -->
                    <div
                        class="w-2/5 brand-side flex flex-col justify-between p-10"
                    >
                        <div>
                            <span class="system-tag">SISTEMA DE GESTIÓN</span>
                        </div>

                        <div class="brand-content">
                            <img
                                src="/images/logo_colocar.png"
                                alt="Belleza"
                                class="brand-logo"
                            />
                            <div class="divider-line"></div>
                            <p class="brand-caption">
                                Ventas &middot; Inventario
                            </p>
                        </div>

                        <p class="brand-footer">
                            Perfumería, Maquillaje, Cosmética &amp; más
                        </p>
                    </div>

                    <!-- Formulario -->
                    <div
                        class="w-3/5 p-12 flex flex-col justify-center form-side"
                    >
                        <span class="welcome-tag">BIENVENIDO(A)</span>
                        <h2 class="form-title mb-1">Acceso al sistema</h2>
                        <p class="form-subtitle mb-10">
                            Ingresa tus credenciales para continuar
                        </p>

                        <form
                            class="flex flex-col gap-7"
                            @submit.prevent="login"
                        >
                            <div class="flex flex-col gap-1">
                                <FloatLabel variant="on">
                                    <IconField>
                                        <InputIcon class="pi pi-user" />
                                        <InputText
                                            id="username"
                                            v-model="form.username"
                                            class="w-full styled-input"
                                            size="large"
                                            :invalid="!!form.errors.username"
                                            @input="
                                                form.clearErrors('username')
                                            "
                                        />
                                        <label for="username">Usuario</label>
                                    </IconField>
                                </FloatLabel>

                                <small
                                    v-if="
                                        form.errors.username &&
                                        !form.errors.username.includes(
                                            'credenciales',
                                        )
                                    "
                                    class="text-red-500 text-sm"
                                >
                                    {{ form.errors.username }}
                                </small>
                            </div>

                            <div class="flex flex-col gap-1">
                                <FloatLabel variant="on">
                                    <IconField>
                                        <InputIcon class="pi pi-lock" />
                                        <Password
                                            id="password"
                                            v-model="form.password"
                                            class="w-full styled-input"
                                            inputClass="w-full"
                                            size="large"
                                            :feedback="false"
                                            toggleMask
                                            :invalid="!!form.errors.password"
                                            @input="
                                                form.clearErrors('password')
                                            "
                                        />
                                        <label for="password">Contraseña</label>
                                    </IconField>
                                </FloatLabel>

                                <small
                                    v-if="form.errors.password"
                                    class="text-red-500 text-sm"
                                >
                                    {{ form.errors.password }}
                                </small>

                                <small
                                    v-if="
                                        form.errors.username &&
                                        form.errors.username
                                            .toLowerCase()
                                            .includes('credenciales')
                                    "
                                    class="text-red-500 text-sm"
                                >
                                    {{ form.errors.username }}
                                </small>
                            </div>

                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <Checkbox
                                        inputId="recordar"
                                        v-model="form.remember"
                                        :binary="true"
                                    />
                                    <label
                                        for="recordar"
                                        class="cursor-pointer remember-label"
                                    >
                                        Recordar sesión
                                    </label>
                                </div>
                                
                            </div>

                            <div
                                v-if="
                                    form.hasErrors &&
                                    !form.errors.username &&
                                    !form.errors.password
                                "
                                class="flex items-center gap-2 p-3 rounded-lg bg-red-50 border border-red-200 text-red-600"
                            >
                                <i class="pi pi-exclamation-circle"></i>

                                <span class="text-sm">
                                    {{ Object.values(form.errors)[0] }}
                                </span>
                            </div>

                            <Button
                                type="submit"
                                label="Ingresar al sistema"
                                class="w-full submit-btn"
                                size="large"
                                :loading="form.processing"
                            />
                        </form>

                        <p class="version-tag">
                            v1.3 &nbsp;&middot;&nbsp; Acceso restringido a
                            personal autorizado
                        </p>
                    </div>
                </div>
            </template>
        </Card>
    </div>
</template>

<script setup>
import { useForm } from "@inertiajs/vue3";
import Card from "primevue/card";


const form = useForm({
    username: "",
    password: "",
    remember: false,
});

const login = () => {
    form.clearErrors();

    form.post(route("login"), {
        preserveScroll: true,
    });
};
</script>

<style scoped>
/* =========================================================
   BELLEZA - IDENTIDAD VISUAL
   Vino + Rosa suave + Neutros + Dorado
   ========================================================= */

/* =========================================================
   PÁGINA DE LOGIN
   ========================================================= */

.login-page {
    background: linear-gradient(135deg, #faf8f9 0%, #f8e9ee 100%);
}

/* =========================================================
   CARD PRINCIPAL
   ========================================================= */

.login-card {
    border-radius: 18px !important;
    border: 1px solid #ead5dc !important;

    box-shadow: 0 25px 60px rgba(90, 16, 40, 0.12) !important;
}

.login-card :deep(.p-card-body),
.login-card :deep(.p-card-content) {
    padding: 0 !important;
}

/* =========================================================
   PANEL DE MARCA
   ========================================================= */

.brand-side {
    background: linear-gradient(160deg, #5a1028 0%, #7b1e3a 55%, #8f294a 100%);

    color: white;
}

/* Texto superior */

.system-tag {
    font-size: 0.68rem;
    font-weight: 600;
    letter-spacing: 3px;
    color: rgba(255, 255, 255, 0.75);
}

/* Contenedor del logo */

.brand-content {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
}

/* Logo */

.brand-logo {
    width: 240px;
    height: auto;
    object-fit: contain;
    margin-bottom: 12px;
}

/* Línea decorativa */

.divider-line {
    width: 42px;
    height: 2px;

    background: #d8a6b5;

    margin: 0 0 16px 0;
}

/* Texto debajo del logo */

.brand-caption {
    font-size: 0.82rem;
    letter-spacing: 0.5px;
    color: rgba(255, 255, 255, 0.72);
}

/* Texto inferior */

.brand-footer {
    font-size: 0.72rem;
    font-style: italic;
    color: rgba(255, 255, 255, 0.48);
}

/* =========================================================
   FORMULARIO
   ========================================================= */

.form-side {
    background: #fffdfd;
}

/* BIENVENIDO(A) */

.welcome-tag {
    font-size: 0.7rem;
    font-weight: 700;
    letter-spacing: 3px;

    color: #9a4561;
}

/* Título */

.form-title {
    font-family: "Georgia", serif;

    font-size: 1.7rem;

    color: #7b1e3a;

    font-weight: 400;
}

/* Subtítulo */

.form-subtitle {
    color: #64748b;
    font-size: 0.9rem;
}

/* =========================================================
   RECORDAR SESIÓN
   ========================================================= */

.remember-label {
    color: #475569;
    font-size: 0.88rem;
}

/* =========================================================
   OLVIDASTE TU CONTRASEÑA
   ========================================================= */

.forgot-link {
    color: #8f294a;

    font-size: 0.83rem;

    text-decoration: none;

    transition: color 0.2s ease;
}

.forgot-link:hover {
    color: #5a1028;

    text-decoration: underline;
}

/* =========================================================
   INPUTS
   ========================================================= */

:deep(.styled-input),
:deep(.styled-input.p-inputtext),
:deep(.styled-input.p-password) {
    width: 100%;
}

/* Input normal */
:deep(.styled-input.p-inputtext) {
    border-radius: 10px !important;
    border: 1px solid #d9c5cc !important;
    background: #faf8f9 !important;
    transition: all 0.25s ease !important;
}

/* Input de contraseña */
:deep(.styled-input.p-password) {
    border: none !important;
    background: transparent !important;
    box-shadow: none !important;
}

/* Campo interno del Password */
:deep(.styled-input.p-password .p-password-input) {
    width: 100% !important;
    border-radius: 10px !important;
    border: 1px solid #d9c5cc !important;
    background: #faf8f9 !important;
    transition: all 0.25s ease !important;
}

/* =========================================================
   FOCUS
   ========================================================= */

/* Usuario */
:deep(.styled-input.p-inputtext:enabled:focus) {
    border-color: #7b1e3a !important;
    background: #ffffff !important;
    outline: none !important;
    box-shadow: 0 0 0 3px rgba(123, 30, 58, 0.14) !important;
}

/* Contraseña */
:deep(.styled-input.p-password .p-password-input:enabled:focus) {
    border-color: #7b1e3a !important;
    background: #ffffff !important;
    outline: none !important;
    box-shadow: 0 0 0 3px rgba(123, 30, 58, 0.14) !important;
}

/* Quitar cualquier foco del contenedor Password */
:deep(.styled-input.p-password:focus-within) {
    outline: none !important;
    box-shadow: none !important;
}

/* =========================================================
   ICONOS
   ========================================================= */

:deep(.p-iconfield .p-inputicon) {
    color: #7b1e3a;
}

/* =========================================================
   CHECKBOX
   ========================================================= */

:deep(.p-checkbox.p-highlight .p-checkbox-box) {
    background: #7b1e3a !important;

    border-color: #7b1e3a !important;
}

/* =========================================================
   BOTÓN INGRESAR
   ========================================================= */

.submit-btn {
    background: #7b1e3a !important;

    border: none !important;

    border-radius: 10px !important;

    font-weight: 600 !important;

    letter-spacing: 0.3px;

    box-shadow: 0 10px 20px rgba(123, 30, 58, 0.28);

    transition: 0.25s;
}

/* Hover */

.submit-btn:hover {
    background: #5a1028 !important;

    transform: translateY(-2px);

    box-shadow: 0 15px 30px rgba(90, 16, 40, 0.35);
}

/* =========================================================
   VERSION
   ========================================================= */

.version-tag {
    margin-top: 28px;

    text-align: center;

    font-size: 0.72rem;

    color: #94a3b8;
}
</style>
