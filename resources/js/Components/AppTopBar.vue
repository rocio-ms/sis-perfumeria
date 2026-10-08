<script setup>
import { ref, onMounted, computed } from "vue";
import { usePage, router } from "@inertiajs/vue3";
import Swal from "sweetalert2";

import Button from "primevue/button";
import Avatar from "primevue/avatar";
import Popover from "primevue/popover";
import Divider from "primevue/divider";

const props = defineProps({
    sidebarWidth: {
        type: Number,
        default: 300,
    },
});

const page = usePage();

const user = computed(() => page.props.auth.user);

const userInitials = computed(() => {
    const name = user.value?.name ?? "";
    return (
        name
            .trim()
            .split(/\s+/)
            .map((part) => part[0])
            .slice(0, 2)
            .join("")
            .toUpperCase() || "?"
    );
});

//Notificaciones

const op = ref();
const notificationsOpen = ref(false);

const notifications = computed(() => {
    return (page.props.notificaciones?.items ?? []).filter(
        (item) => !item.solucionada,
    );
});

const unread = computed(() => {
    return notifications.value.filter((item) => !item.leida).length;
});

const unreadLabel = computed(() => (unread.value > 9 ? "9+" : unread.value));

const verTodas = () => {
    op.value.hide();
    router.visit(route("notificaciones.index"));
};

const toggleNotifications = (event) => {
    op.value.toggle(event);
};

//Menú de usuario

const userMenu = ref();
const userMenuOpen = ref(false);

const toggleUserMenu = (event) => {
    userMenu.value.toggle(event);
};

// Cierre de sesión
const logout = async () => {
    userMenu.value?.hide();
    try {
        const response = await fetch(route("sesiones-caja.verificar-abierta"), {
            headers: {
                Accept: "application/json",
                "X-Requested-With": "XMLHttpRequest",
            },
            credentials: "same-origin",
        });

        const data = await response.json();

        if (data.abierta) {
            Swal.fire({
                title: "Caja abierta",
                text: "Debes cerrar tu caja antes de cerrar sesión.",
                icon: "warning",
                confirmButtonText: "Ir a cerrar caja",
                cancelButtonText: "Cancelar",
                showCancelButton: true,
                reverseButtons: true,
                confirmButtonColor: "#7b1e3a",
                cancelButtonColor: "#94a3b8",
            }).then((result) => {
                if (result.isConfirmed) {
                    router.visit(route("sesiones-caja.index"));
                }
            });

            return;
        }

        Swal.fire({
            title: "¿Cerrar sesión?",
            text: "Vas a salir de tu cuenta.",
            icon: "question",
            showCancelButton: true,
            confirmButtonText: "Sí, salir",
            cancelButtonText: "Cancelar",
            reverseButtons: true,
            confirmButtonColor: "#7b1e3a",
            cancelButtonColor: "#94a3b8",
        }).then((result) => {
            if (result.isConfirmed) {
                router.post(route("logout"));
            }
        });
    } catch (error) {
        console.error("Error verificando la caja:", error);

        Swal.fire({
            title: "Error",
            text: "No se pudo verificar el estado de la caja.",
            icon: "error",
            confirmButtonText: "Aceptar",
            confirmButtonColor: "#7b1e3a",
        });
    }
};
</script>

<template>
    <header
        class="topbar"
        :style="{ '--sidebar-width': `${props.sidebarWidth}px` }"
    >
        <div class="topbar-right">
            <!-- Notificaciones -->
            <button
                class="icon-btn notif-btn"
                :class="{ 'icon-btn--active': notificationsOpen }"
                aria-label="Notificaciones"
                @click="toggleNotifications"
            >
                <i class="pi pi-bell bell-icon"></i>
                <span v-if="unread > 0" class="notif-dot">{{
                    unreadLabel
                }}</span>
            </button>

            <Popover
                ref="op"
                class="notification-panel"
                @show="notificationsOpen = true"
                @hide="notificationsOpen = false"
            >
                <div class="notification-header">
                    <div>
                        <h4 class="mb-1">Notificaciones</h4>
                        <small class="text-gray-500">
                            {{ unread }}
                            {{
                                unread === 1 ? "notificación" : "notificaciones"
                            }}
                            sin leer
                        </small>
                    </div>
                    <span v-if="unread > 0" class="notification-header-badge">{{
                        unreadLabel
                    }}</span>
                </div>
                <Divider class="my-2" />
                <div class="notification-list">
                    <div
                        v-for="item in notifications"
                        :key="item.id"
                        class="notification-item"
                    >
                        <!-- Icono -->
                        <div
                            class="notification-icon"
                            :class="{
                                'notification-danger':
                                    item.severity === 'danger',
                                'notification-warning':
                                    item.severity === 'warning',
                            }"
                        >
                            <i :class="item.icon"></i>
                        </div>
                        <!-- Información -->
                        <div class="notification-text">
                            <div class="notification-title">
                                {{ item.titulo }}
                            </div>

                            <div class="notification-message">
                                {{ item.mensaje }}
                            </div>

                            <!-- Tipo de notificación -->
                            <div class="notification-type">
                                <span v-if="item.tipo === 'stock_bajo'"
                                    >Stock</span
                                >
                                <span v-else-if="item.tipo === 'vencimiento'"
                                    >Vencimiento próximo</span
                                >
                                <span v-else-if="item.tipo === 'vencido'"
                                    >Producto vencido</span
                                >
                            </div>
                        </div>

                        <!-- Indicador de no leída -->
                        <span
                            v-if="!item.leida"
                            class="dot"
                            title="No leída"
                        ></span>
                    </div>
                </div>
                <!-- Sin notificaciones -->
                <div
                    v-if="notifications.length === 0"
                    class="empty-notifications"
                >
                    <i class="pi pi-check-circle"></i>
                    <span>No tienes notificaciones</span>
                </div>

                <Divider class="my-2" />

                <Button
                    label="Ver todas"
                    icon="pi pi-arrow-right"
                    iconPos="right"
                    text
                    fluid
                    @click="verTodas"
                />
            </Popover>

            <Divider layout="vertical" class="topbar-divider" />

            <!-- Usuario -->
            <div class="user-info" @click="toggleUserMenu">
                <Avatar
                    :label="userInitials"
                    shape="circle"
                    size="large"
                    class="user-avatar"
                />

                <div class="user-data">
                    <span class="user-name">{{ user.name }}</span>
                    <small>{{ user.username }}</small>
                </div>

                <i
                    class="pi pi-angle-down user-arrow"
                    :class="{ 'user-arrow--open': userMenuOpen }"
                />
            </div>

            <Popover
                ref="userMenu"
                class="user-menu"
                @show="userMenuOpen = true"
                @hide="userMenuOpen = false"
            >
                <Button
                    icon="pi pi-sign-out"
                    label="Cerrar sesión"
                    severity="danger"
                    text
                    fluid
                    class="justify-content-start logout-btn"
                    @click="logout"
                />
            </Popover>
        </div>
    </header>
</template>

<style scoped>
.topbar {
    height: 73px;

    position: fixed;
    top: 0;
    left: var(--sidebar-width);

    width: calc(100% - var(--sidebar-width));

    background: rgba(255, 255, 255, 0.96);

    backdrop-filter: blur(8px);

    border-bottom: 1px solid #f0e3e7;

    display: flex;
    justify-content: flex-end;
    align-items: center;

    padding: 0 28px;

    box-shadow:
        0 1px 2px rgba(15, 23, 42, 0.04),
        0 6px 20px -14px rgba(123, 30, 58, 0.3);

    z-index: 10;

    transition:
        left 0.2s cubic-bezier(0.4, 0, 0.2, 1),
        width 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}

/* =========================================================
   CONTENEDOR
   ========================================================= */

.topbar-right {
    display: flex;
    align-items: center;
    gap: 8px;
}

/* =========================================================
   BOTONES DE ICONOS
   ========================================================= */

.icon-btn {
    position: relative;

    width: 2.5rem;
    height: 2.5rem;

    border: none;

    background: transparent;

    border-radius: 999px;

    color: #6b7280;

    display: flex;
    align-items: center;
    justify-content: center;

    cursor: pointer;

    transition:
        background 0.2s ease,
        color 0.2s ease,
        transform 0.15s ease;
}

.icon-btn:hover {
    background: #f8e9ee !important;
    color: #7b1e3a !important;
}

.icon-btn:active {
    transform: scale(0.94);
}

.icon-btn--active {
    background: #f8e9ee !important;
    color: #7b1e3a !important;
}

/* =========================================================
   CAMPANA
   ========================================================= */

.bell-icon {
    font-size: 1.25rem;
}

/* =========================================================
   BADGE NOTIFICACIONES
   ========================================================= */

.notif-dot {
    position: absolute;

    top: 4px;
    right: 4px;

    min-width: 16px;
    height: 16px;

    padding: 0 3px;

    border-radius: 999px;

    background: linear-gradient(135deg, #9f1239, #7b1e3a);

    color: #fff;

    font-size: 0.6rem;
    font-weight: 700;

    line-height: 16px;

    text-align: center;

    box-shadow: 0 0 0 2px #ffffff;
}

.notif-dot::after {
    content: "";

    position: absolute;
    inset: 0;

    border-radius: 999px;

    background: #7b1e3a;

    z-index: -1;

    animation: notif-pulse 1.8s ease-out infinite;
}

@keyframes notif-pulse {
    0% {
        transform: scale(1);
        opacity: 0.55;
    }

    100% {
        transform: scale(2.1);
        opacity: 0;
    }
}

/* =========================================================
   DIVISOR
   ========================================================= */

.topbar-divider {
    height: 28px;
    margin: 0 4px;
}

/* =========================================================
   USUARIO
   ========================================================= */

.user-info {
    display: flex;
    align-items: center;

    gap: 12px;

    padding: 6px 12px 6px 6px;

    border-radius: 999px;

    cursor: pointer;

    transition: background 0.2s ease;
}

.user-info:hover {
    background: #f8e9ee;
}

/* Avatar */

.user-avatar {
    background: #7b1e3a !important;

    color: #fff !important;

    font-weight: 600;

    box-shadow:
        0 0 0 2px #ffffff,
        0 0 0 3px #e8cbd4;
}

/* Información */

.user-data {
    display: flex;
    flex-direction: column;

    line-height: 1.15;
}

.user-name {
    color: #3f1725;

    font-weight: 600;

    font-size: 0.88rem;
}

.user-data small {
    color: #7c6a71;

    font-size: 0.75rem;
}

/* Flecha */

.user-arrow {
    color: #9b858d;

    font-size: 0.85rem;

    transition:
        transform 0.2s ease,
        color 0.2s ease;
}

.user-info:hover .user-arrow {
    color: #7b1e3a;
}

.user-arrow--open {
    transform: rotate(180deg);
}

/* =========================================================
   POPOVER USUARIO
   ========================================================= */

.user-menu {
    width: 260px;
}

.user-menu :deep(.p-popover-content) {
    padding: 0.5rem;
}

.logout-btn {
    font-weight: 600;
}

/* =========================================================
   POPOVER NOTIFICACIONES
   ========================================================= */

.notification-panel {
    width: 340px;
}

.notification-header {
    display: flex;
    align-items: center;
    justify-content: space-between;

    padding: 0.15rem 0.1rem 0;
}

.notification-header h4 {
    font-size: 0.9rem;

    font-weight: 700;

    color: #3f1725;

    margin: 0 0 2px;
}

.notification-header-badge {
    background: #f8e9ee;

    color: #7b1e3a;

    font-size: 0.7rem;

    font-weight: 700;

    padding: 2px 8px;

    border-radius: 999px;
}

/* Lista */

.notification-list {
    max-height: 380px;

    overflow-y: auto;

    padding-right: 4px;
}

.notification-list::-webkit-scrollbar {
    width: 6px;
}

.notification-list::-webkit-scrollbar-track {
    background: transparent;
}

.notification-list::-webkit-scrollbar-thumb {
    background: #d8c2ca;

    border-radius: 10px;
}

.notification-list::-webkit-scrollbar-thumb:hover {
    background: #b99aa5;
}

/* Item */

.notification-item {
    display: flex;

    align-items: flex-start;

    gap: 12px;

    padding: 10px 8px;

    border-radius: 10px;

    transition: background 0.2s;
}

.notification-item:hover {
    background: #fdf6f8;
}

/* Icono */

.notification-icon {
    width: 32px;
    height: 32px;

    min-width: 32px;

    border-radius: 50%;

    display: flex;
    align-items: center;
    justify-content: center;

    font-size: 0.85rem;
}

.notification-danger {
    background: #fef2f2;

    color: #dc2626;
}

.notification-warning {
    background: #fffbeb;

    color: #d97706;
}

/* Texto */

.notification-text {
    flex: 1;

    min-width: 0;

    display: flex;
    flex-direction: column;

    gap: 3px;
}

.notification-title {
    font-size: 0.82rem;

    font-weight: 600;

    color: #3f1725;
}

.notification-message {
    font-size: 0.75rem;

    color: #64748b;

    line-height: 1.4;
}

.notification-type {
    font-size: 0.68rem;

    color: #a58e97;

    margin-top: 1px;
}

/* Indicador */

.dot {
    width: 7px;
    height: 7px;

    min-width: 7px;

    background: #7b1e3a;

    border-radius: 50%;

    margin-top: 5px;
}

/* Sin notificaciones */

.empty-notifications {
    display: flex;

    flex-direction: column;

    align-items: center;
    justify-content: center;

    padding: 30px 10px;

    color: #64748b;

    gap: 8px;
}

.empty-notifications i {
    font-size: 1.8rem;

    color: #7b1e3a;
}

/* Botón Ver todas */

.view-all-btn {
    color: #7b1e3a !important;

    font-weight: 600;
}

.view-all-btn:hover {
    background: #f8e9ee !important;
}
</style>
