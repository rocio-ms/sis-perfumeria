<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from "vue";
import Sidebar from "@/Components/Sidebar.vue";
import AppTopBar from "@/Components/AppTopBar.vue";

const rail = ref(false);
const sidebarWidth = computed(() => (rail.value ? 80 : 300));

const colapsarSidebar = () => {
    rail.value = true;
};

onMounted(() => {
    window.addEventListener("sidebar-collapse", colapsarSidebar);
});

onBeforeUnmount(() => {
    window.removeEventListener("sidebar-collapse", colapsarSidebar);
});
</script>

<template>
    <v-app>
        <Sidebar v-model:rail="rail" />

        <v-main>
            <AppTopBar :sidebar-width="sidebarWidth" />

            <div class="page-content">
                <slot />
            </div>
        </v-main>
    </v-app>
</template>

<style scoped>
.page-content {
    padding-top: 73px;
}
</style>
