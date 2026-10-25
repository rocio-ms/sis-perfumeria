<script setup>
import { computed, ref, watch } from "vue";
import { Link, usePage } from "@inertiajs/vue3";

const props = defineProps({
    rail: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(["update:rail"]);

const rail = computed({
    get: () => props.rail,
    set: (value) => emit("update:rail", value),
});

const drawer = ref(true);
const opened = ref([]);

const page = usePage();

/*
|--------------------------------------------------------------------------
| PERMISOS DEL USUARIO AUTENTICADO
|--------------------------------------------------------------------------
*/

const permisos = computed(() => {
    return page.props.auth?.permissions ?? [];
});

const roles = computed(() => {
    return page.props.auth?.roles ?? [];
});

const esAdmin = computed(() => {
    return roles.value.includes("Administrador");
});

const tienePermiso = (permiso) => {
    if (esAdmin.value) {
        return true;
    }

    return permisos.value.includes(permiso);
};

/*
|--------------------------------------------------------------------------
| NOTIFICACIONES
|--------------------------------------------------------------------------
*/

const notificaciones = computed(() => {
    return (
        page.props.notificaciones ?? {
            stock_bajo: 0,
            vencimientos: 0,
            vencidos: 0,
            total: 0,
            items: [],
        }
    );
});

/*
|--------------------------------------------------------------------------
| MENU COMPLETO
|--------------------------------------------------------------------------
*/

const items = [
    {
        title: "Dashboard",
        icon: "mdi-home",
        route: "dashboard",
    },

    {
        title: "Ventas",
        icon: "mdi-cart",
        children: [
            {
                title: "Nueva venta",
                icon: "mdi-plus-circle",
                route: "ventas.create",
                permiso: "ventas.crear",
            },
            {
                title: "Historial de ventas",
                icon: "mdi-history",
                route: "ventas.index",
                permiso: "ventas.index",
            },
        ],
    },
    {
        title: "Clientes",
        icon: "mdi-account-multiple",
        route: "clientes.index",
        permiso: "clientes.index",
    },
    {
        title: "Gestión de cajas",
        icon: "mdi-cash-register",
        children: [
            {
                title: "Cajas",
                icon: "mdi-cash-register",
                route: "cajas.index",
                permiso: "cajas.index",
            },
            {
                title: "Apertura / Cierre",
                icon: "mdi-lock-open-variant",
                route: "sesiones-caja.index",
                permiso: "sesiones-caja.index",
            },
        ],
    },

    {
        title: "Gestión de productos",
        icon: "mdi-package-variant",
        children: [
            {
                title: "Productos",
                icon: "mdi-package-variant",
                route: "productos.index",
                permiso: "productos.index",
            },
            {
                title: "Categorías",
                icon: "mdi-shape",
                route: "categorias.index",
                permiso: "categorias.index",
            },
            {
                title: "Marcas",
                icon: "mdi-tag",
                route: "marcas.index",
                permiso: "marcas.index",
            },
        ],
    },

    {
        title: "Inventario",
        icon: "mdi-warehouse",
        children: [
            {
                title: "Entradas",
                icon: "mdi-package-up",
                route: "entradas.index",
                permiso: "entradas.index",
            },
            {
                title: "Salidas",
                icon: "mdi-shield-account",
                route: "salidas.index",
                permiso: "salidas.index",
            },
            {
                title: "Kardex",
                icon: "mdi-clipboard-list",
                route: "kardex.index",
                permiso: "kardex.index",
            },
            {
                title: "Lotes",
                icon: "mdi-barcode",
                route: "lotes.index",
                permiso: "lotes.index",
            },
           
        ],
    },
    {
        title: "Proveedores",
        icon: "mdi-account-tie",
        route: "proveedores.index",
        permiso: "proveedores.index",
    },

    {
        title: "Notificaciones",
        icon: "mdi-alert",
        route: "notificaciones.index",
        permiso: "notificaciones.index",
    },

    {
        title: "Reportes",
        icon: "mdi-chart-bar",
        route: "reportes.index",
        permiso: "reportes.index",
    },

    {
        title: "Configuración",
        icon: "mdi-cog",
        children: [
            {
                title: "Datos de la tienda",
                icon: "mdi-store",
                route: "tiendas.index",
                permiso: "tiendas.index",
            },
            {
                title: "Empresas",
                icon: "mdi-domain",
                route: "empresas.index",
                permiso: "empresas.index",
            },
            {
                title: "Usuarios",
                icon: "mdi-account-multiple",
                route: "usuarios.index",
                permiso: "usuarios.index",
            },
        ],
    },
];

/*
|--------------------------------------------------------------------------
| FILTRAR MENU SEGÚN PERMISOS
|--------------------------------------------------------------------------
*/

const itemsFiltrados = computed(() => {
    return items
        .map((item) => {
            // ITEM SIMPLE
            if (!item.children) {
                if (!item.permiso) {
                    return item;
                }

                return tienePermiso(item.permiso) ? item : null;
            }

            // GRUPO
            const childrenPermitidos = item.children.filter((child) => {
                if (!child.permiso) {
                    return true;
                }

                return tienePermiso(child.permiso);
            });

            // Si el grupo no tiene hijos permitidos, no mostrarlo
            if (childrenPermitidos.length === 0) {
                return null;
            }

            return {
                ...item,
                children: childrenPermitidos,
            };
        })
        .filter(Boolean);
});

/*
|--------------------------------------------------------------------------
| WATCH
|--------------------------------------------------------------------------
*/

watch(rail, (value) => {
    if (value) {
        opened.value = [];
    }
});

/*
|--------------------------------------------------------------------------
| GRUPOS
|--------------------------------------------------------------------------
*/

const isGroupActive = (item) => {
    if (!item.children) return false;

    return item.children.some((child) =>
        route().current(child.route)
    );
};

/*
|--------------------------------------------------------------------------
| ABRIR SIDEBAR
|--------------------------------------------------------------------------
*/

const abrirSidebar = () => {
    if (rail.value) {
        rail.value = false;
    }
};
</script>

<template>
    <v-navigation-drawer
        v-model="drawer"
        :rail="rail"
        rail-width="80"
        width="300"
        permanent
        fixed
        class="sidebar-drawer"
        :class="{ 'sidebar-drawer--rail': rail }"
    >
        <v-list class="sidebar-header">
            <v-list-item>
                <template v-if="!rail">
                    <div class="sidebar-header-content">
                        <img
                            src="/images/logo_colocar.png"
                            alt="Belleza"
                            class="sidebar-logo"
                        />

                        <v-btn
                            icon="mdi-chevron-left"
                            variant="text"
                            class="collapse-btn"
                            @click.stop="rail = true"
                        />
                    </div>
                </template>

                <template v-else>
                    <div class="sidebar-header-collapsed">
                        <v-btn
                            icon="mdi-menu"
                            variant="text"
                            class="menu-btn"
                            @click.stop="rail = false"
                        />
                    </div>
                </template>
            </v-list-item>
        </v-list>

        <v-divider></v-divider>

        <v-list
            v-model:opened="opened"
            density="compact"
            nav
            class="sidebar-menu"
        >
            <template v-for="item in itemsFiltrados" :key="item.title">
                <!-- Item simple -->

                <Link
                    v-if="!item.children"
                    :href="route(item.route)"
                    class="text-decoration-none"
                >
                    <v-list-item
                        :prepend-icon="item.icon"
                        :title="item.title"
                        :active="route().current(item.route)"
                        @click="abrirSidebar"
                    >
                        <template
                            v-if="
                                item.route === 'alertas.index' &&
                                notificaciones.total > 0
                            "
                            #append
                        >
                            <v-badge
                                v-if="notificaciones.total > 0"
                                :content="notificaciones.total"
                                color="error"
                                inline
                            />
                        </template>
                    </v-list-item>
                </Link>

                <!-- Grupo -->
                <v-list-group v-else :value="item.title">
                    <template #activator="{ props }">
                        <v-list-item
                            v-bind="props"
                            :prepend-icon="item.icon"
                            :title="item.title"
                            :active="isGroupActive(item)"
                            @click="abrirSidebar"
                        >
                        </v-list-item>
                    </template>
                    <Link
                        v-for="child in item.children"
                        :key="child.route"
                        :href="route(child.route)"
                        class="text-decoration-none"
                    >
                        <v-list-item
                            :prepend-icon="child.icon"
                            :title="child.title"
                            :active="route().current(child.route)"
                            @click="abrirSidebar"
                        >
                        </v-list-item>
                    </Link>
                </v-list-group>
            </template>
        </v-list>
    </v-navigation-drawer>
</template>

<style scoped>
/* =========================================================
   SIDEBAR PRINCIPAL
   ========================================================= */

.sidebar-drawer {
    background: linear-gradient(
        180deg,
        #5a1028 0%,
        #7b1e3a 55%,
        #6d1933 100%
    ) !important;

    color: white !important;
}

/*
 * Vuetify coloca el contenido real del drawer
 * dentro de .v-navigation-drawer__content.
 */
.sidebar-drawer :deep(.v-navigation-drawer__content) {
    background: linear-gradient(
        180deg,
        #5a1028 0%,
        #7b1e3a 55%,
        #6d1933 100%
    ) !important;

    color: white !important;
}

/* =========================================================
   HEADER
   ========================================================= */

.sidebar-header {
    background: transparent !important;
    padding: 8px 0 !important;
}

.sidebar-header :deep(.v-list-item) {
    min-height: 64px;
    padding: 0 16px !important;
    background: transparent !important;
}

/* =========================================================
   LOGO
   ========================================================= */

.sidebar-header-content {
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.sidebar-logo {
    width: 150px;
    height: auto;
    object-fit: contain;
    display: block;
}

/* =========================================================
   BOTÓN COLAPSAR
   ========================================================= */

.collapse-btn {
    color: rgba(255, 255, 255, 0.85) !important;
    flex-shrink: 0;
    transition: all 0.2s ease;
}

.collapse-btn:hover {
    color: white !important;
    background: rgba(255, 255, 255, 0.12) !important;
}

/* =========================================================
   SIDEBAR COLAPSADO
   ========================================================= */

.sidebar-drawer--rail .sidebar-header {
    padding: 8px 0 !important;
}

.sidebar-drawer--rail .sidebar-header :deep(.v-list-item) {
    padding: 0 !important;
    min-height: 64px;
    justify-content: center;
}

/* =========================================================
   HAMBURGUESA
   ========================================================= */

.sidebar-header-collapsed {
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.menu-btn {
    color: white !important;
    width: 48px !important;
    height: 48px !important;
}

.menu-btn:hover {
    background: rgba(255, 255, 255, 0.12) !important;
}

/* =========================================================
   LISTA
   ========================================================= */

.sidebar-drawer :deep(.v-list) {
    background: transparent !important;
    color: white !important;
}

/* =========================================================
   ITEMS
   ========================================================= */

.sidebar-drawer :deep(.v-list-item) {
    color: rgba(255, 255, 255, 0.9) !important;
    min-height: 48px;
    margin-bottom: 4px;
}

.sidebar-drawer :deep(.v-list-item .v-icon) {
    color: rgba(255, 255, 255, 0.9) !important;
}

/* =========================================================
   ITEM ACTIVO
   ========================================================= */

.sidebar-drawer :deep(.v-list-item--active) {
    background: rgba(255, 255, 255, 0.18) !important;
    border-radius: 12px;
    color: white !important;
}

.sidebar-drawer :deep(.v-list-item--active .v-icon) {
    color: white !important;
}

/* =========================================================
   GRUPOS
   ========================================================= */

.sidebar-drawer :deep(.v-list-group__items) {
    background: transparent !important;
}

.sidebar-drawer :deep(.v-list-group__items .v-list-item) {
    color: rgba(255, 255, 255, 0.8) !important;
}

/* =========================================================
   DIVIDER
   ========================================================= */

.sidebar-drawer :deep(.v-divider) {
    border-color: rgba(255, 255, 255, 0.15) !important;
}

/* =========================================================
   TEXTO
   ========================================================= */

.sidebar-drawer :deep(.v-list-item-title) {
    color: rgba(255, 255, 255, 0.95) !important;
}

/* =========================================================
   SIDEBAR COLAPSADO - CENTRAR ICONOS
   ========================================================= */

.sidebar-drawer--rail :deep(.v-list-item) {
    padding-left: 0 !important;
    padding-right: 0 !important;
    justify-content: center;
    min-width: 80px;
    width: 80px;
}

.sidebar-drawer--rail :deep(.v-list-item__prepend) {
    display: flex;
    width: 48px;
    min-width: 48px;
    justify-content: center;
    margin: 0 auto !important;
    transform: translateX(15px);
}

.sidebar-drawer--rail .sidebar-menu :deep(.v-list-item--active) {
    background: transparent !important;
}

.sidebar-drawer--rail
    .sidebar-menu
    :deep(.v-list-item--active .v-list-item__overlay),
.sidebar-drawer--rail
    .sidebar-menu
    :deep(.v-list-item--active .v-list-item__underlay) {
    display: none !important;
}

.sidebar-drawer--rail
    .sidebar-menu
    :deep(.v-list-item--active .v-list-item__prepend) {
    position: relative;
    min-height: 44px;
    border-radius: 12px;
    background: transparent;
}

.sidebar-drawer--rail
    .sidebar-menu
    :deep(.v-list-item--active .v-list-item__prepend::before) {
    content: "";
    position: absolute;
    z-index: 0;
    width: 44px;
    height: 44px;
    top: 50%;
    left: 50%;
    border-radius: 12px;
    background: rgba(255, 255, 255, 0.18);
    transform: translate(-50%, -50%) translateX(-8px);
}

.sidebar-drawer--rail
    .sidebar-menu
    :deep(.v-list-item--active .v-list-item__prepend .v-icon) {
    position: relative;
    z-index: 1;
}

.sidebar-drawer--rail .sidebar-menu :deep(.v-list-item__content),
.sidebar-drawer--rail .sidebar-menu :deep(.v-list-item__append),
.sidebar-drawer--rail .sidebar-menu :deep(.v-list-item-title) {
    display: none !important;
}

.sidebar-drawer--rail .sidebar-menu :deep(.v-list-group__items) {
    display: none !important;
}
</style>
