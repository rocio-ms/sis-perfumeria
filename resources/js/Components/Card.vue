<!-- <template>
  <v-card
    class="mx-auto"
    elevation="4"
  >
    <v-card-item>
      <v-card-title>
        <slot name="title" />
      </v-card-title>

      <v-card-subtitle>
        <slot name="subtitle" />
      </v-card-subtitle>
    </v-card-item>

    <v-card-text>
      <slot name="content" />
    </v-card-text>
  </v-card>
</template> -->

<script setup>
import Card from "primevue/card";

defineProps({
    cardClass: {
        type: [String, Array, Object],
        default: "",
    },
});
</script>

<template>
    <Card class="app-card" :class="cardClass">
        <template #title>
            <slot name="title" />
        </template>

        <template v-if="$slots.subtitle" #subtitle>
            <slot name="subtitle" />
        </template>

        <template #content>
            <slot name="content" />
        </template>
    </Card>
</template>

<style scoped>
.app-card {
    border-radius: 10px;

    box-shadow:
        0px 2px 4px -1px rgba(0, 0, 0, 0.2),
        0px 4px 5px 0px rgba(0, 0, 0, 0.14),
        0px 1px 10px 0px rgba(0, 0, 0, 0.12);

    transition:
        background-color 0.2s ease,
        color 0.2s ease,
        box-shadow 0.2s ease;
}

/* =========================
   MODO CLARO
   ========================= */

.app-card :deep(.p-card-body) {
    padding: 16px;
}

.app-card :deep(.p-card-title) {
    font-size: 15px;
    font-weight: 700;
    color: #263238;
    margin-bottom: 4px;
}

.app-card :deep(.p-card-subtitle) {
    font-size: 13px;
    color: #78909c;
    margin-bottom: 12px;
}

.app-card :deep(.p-card-content) {
    padding-top: 0;
}

/* =========================
   MODO OSCURO
   ========================= */

.dark-mode .app-card {
    background: var(--p-surface-900);
    color: var(--p-surface-0);

    box-shadow:
        0px 2px 4px -1px rgba(0, 0, 0, 0.4),
        0px 4px 5px 0px rgba(0, 0, 0, 0.3),
        0px 1px 10px 0px rgba(0, 0, 0, 0.25);
}

.dark-mode .app-card :deep(.p-card-body) {
    background: transparent;
}

.dark-mode .app-card :deep(.p-card-title) {
    color: var(--p-surface-0);
}

.dark-mode .app-card :deep(.p-card-subtitle) {
    color: var(--p-surface-400);
}

.dark-mode .app-card :deep(.p-card-content) {
    color: var(--p-surface-200);
}

</style>