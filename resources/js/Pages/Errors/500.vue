<script setup>
import ErrorPage from "@/Components/ErrorPage.vue";
</script>

<template>
    <ErrorPage
        code="500"
        title="Error interno del servidor"
        message="Ocurrió un problema inesperado en el sistema. Intenta nuevamente en unos momentos."
        icon="pi pi-server"
    />
</template>