<script setup>
import ErrorPage from "@/Components/ErrorPage.vue";
</script>

<template>
    <ErrorPage
        code="403"
        title="Acceso denegado"
        message="No tienes permisos suficientes para acceder a esta sección."
        icon="pi pi-lock"
    />
</template>