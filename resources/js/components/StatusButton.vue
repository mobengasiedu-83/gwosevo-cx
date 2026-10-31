<script setup lang="ts">
import { computed } from "vue";

/**
 * Contextual activation control. One button is rendered per row and always
 * offers the action that is valid for the current state, so a list never shows
 * a dead "Activate" beside an active record.
 */
const props = defineProps<{
    active: boolean;
    label: string;
    busy?: boolean;
}>();

const emit = defineEmits<{ toggle: [] }>();

const text = computed(() => (props.active ? "Deactivate" : "Activate"));
const accessibleName = computed(() => `${text.value} ${props.label}`);
</script>

<template>
    <button
        type="button"
        class="btn-sm"
        :class="active ? 'danger' : 'success'"
        :aria-label="accessibleName"
        :disabled="busy"
        @click="emit('toggle')"
    >
        {{ text }}
    </button>
</template>
