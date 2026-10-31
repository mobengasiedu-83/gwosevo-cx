<script setup lang="ts">
/**
 * Status pill used by every list. The wording stays identical across screens
 * so operators read "Active"/"Inactive" the same way everywhere.
 */
withDefaults(
    defineProps<{
        active: boolean;
        activeLabel?: string;
        inactiveLabel?: string;
    }>(),
    { activeLabel: "Active", inactiveLabel: "Inactive" },
);
</script>

<template>
    <span class="badge" :class="{ inactive: !active }">{{
        active ? activeLabel : inactiveLabel
    }}</span>
</template>
