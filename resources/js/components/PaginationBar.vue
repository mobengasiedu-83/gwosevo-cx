<script setup lang="ts">
/**
 * Shared paging footer. Pages own the request, this component only reports the
 * requested page so every list behaves the same way.
 */
const props = withDefaults(
    defineProps<{
        page: number;
        lastPage: number;
        busy?: boolean;
    }>(),
    { busy: false },
);

const emit = defineEmits<{ change: [page: number] }>();
</script>

<template>
    <div class="pagination">
        <button
            type="button"
            :disabled="page <= 1 || busy"
            @click="emit('change', page - 1)"
        >
            Previous
        </button>
        <small>Page {{ page }} of {{ lastPage }}</small>
        <button
            type="button"
            :disabled="page >= lastPage || busy"
            @click="emit('change', page + 1)"
        >
            Next
        </button>
    </div>
</template>
