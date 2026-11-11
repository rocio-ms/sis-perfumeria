<script setup>
import AppLayout from "@/Layouts/AppLayout.vue";
import { Link } from "@inertiajs/vue3";
import DetalleCliente from "./Partials/DetalleCliente.vue";

defineOptions({ layout: AppLayout });
defineProps({ cliente: { type: Object, required: true } });
</script>

<template>
    <v-container fluid>
        <section class="mx-auto grid max-w-2xl gap-4 rounded-xl border bg-white p-5">
            <Link :href="route('clientes.index')" class="text-sm text-rose-800">
                Volver a clientes
            </Link>
            <h1 class="text-xl font-semibold">Detalle del cliente</h1>
            <DetalleCliente :cliente="cliente" />
        </section>
    </v-container>
</template>
