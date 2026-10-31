<script setup lang="ts">
/**
 * Label row shared by every field. The required marker lives beside the label
 * element instead of inside it, so the control's accessible name stays exactly
 * the visible label ("Name") and never becomes "Name *".
 */
defineProps<{
    for: string;
    label: string;
    required?: boolean;
}>();
</script>

<template>
    <div class="field-label">
        <label :for="for">{{ label }}</label>
        <em v-if="required" aria-hidden="true">*</em>
    </div>
</template>
