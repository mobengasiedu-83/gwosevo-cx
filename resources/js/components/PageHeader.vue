<script setup lang="ts">
/**
 * Single page title treatment shared by every workspace screen: eyebrow,
 * heading, supporting copy and an optional actions slot on the right.
 */
defineProps<{
    eyebrow?: string;
    title: string;
    description?: string;
}>();
</script>

<template>
    <header class="page-heading">
        <div>
            <p v-if="eyebrow" class="eyebrow">{{ eyebrow }}</p>
            <h1>{{ title }}</h1>
            <p v-if="description" class="muted">{{ description }}</p>
        </div>
        <div v-if="$slots.actions" class="page-heading-actions">
            <slot name="actions" />
        </div>
    </header>
</template>
