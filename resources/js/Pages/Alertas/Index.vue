<script setup>
import AppLayout from "@/Layouts/AppLayout.vue";
import Breadcrumb from "primevue/breadcrumb"; 
import Card from "primevue/card";
import { ref } from "vue"; 

defineOptions({
    layout: AppLayout,
});

const home = ref({
    icon: "pi pi-home",
});

const items = ref([{ label: "Notificaciones" }]);
</script>

<template>
    <v-container fluid>
        <Card>
            <template #title>
                <div class="card flex justify-content-center">
                    <Breadcrumb :home="home" :model="items"/>
                </div>
            </template>
            <template #content>
                <h1>Notificaciones</h1>
            </template>
        </Card>
    </v-container>
</template>