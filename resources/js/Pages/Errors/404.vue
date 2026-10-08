<script setup>
import ErrorPage from "@/Components/ErrorPage.vue";
</script>

<template>
    <ErrorPage
        code="404"
        title="Página no encontrada"
        message="La página que estás buscando no existe o la dirección ingresada no es válida."
        icon="pi pi-search"
    />
</template>