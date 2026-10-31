<script setup lang="ts">
import { computed, ref } from "vue";
import type { FieldSpec } from "../types";
import FieldLabel from "./FieldLabel.vue";

// Renders one labelled control from a FieldSpec. Secrets start masked and can
// be revealed for the current session only; the server never returns them.
const props = withDefaults(
    defineProps<{
        field: FieldSpec;
        modelValue: any;
        id: string;
        error?: string;
        /**
         * Tri-state override: omit it to inherit `field.required`. The explicit
         * `undefined` default matters because Vue casts an absent Boolean prop
         * to `false`, which would silently drop every inherited marker.
         */
        required?: boolean;
    }>(),
    { required: undefined },
);
const emit = defineEmits<{ "update:modelValue": [value: any] }>();

const revealed = ref(false);
const value = computed({
    get: () => props.modelValue,
    set: (next) => emit("update:modelValue", next),
});
const isRequired = computed(() => props.required ?? props.field.required);
</script>

<template>
    <div class="field">
        <FieldLabel
            :for="id"
            :label="field.label"
            :required="isRequired"
        />
        <select v-if="field.type === 'select'" :id="id" v-model="value">
            <option
                v-for="(text, key) in field.options"
                :key="key"
                :value="key"
            >
                {{ text }}
            </option>
        </select>
        <span v-else-if="field.reveal" class="secret">
            <input
                :id="id"
                v-model="value"
                :type="revealed ? 'text' : 'password'"
                :required="isRequired"
                :inputmode="field.inputmode"
                autocomplete="new-password"
            />
            <button
                type="button"
                class="reveal"
                :aria-label="(revealed ? 'Hide ' : 'Show ') + field.label"
                @click="revealed = !revealed"
            >
                {{ revealed ? "Hide" : "Show" }}
            </button>
        </span>
        <input
            v-else
            :id="id"
            v-model="value"
            :type="field.type || 'text'"
            :required="isRequired"
            :inputmode="field.inputmode"
            autocomplete="off"
        />
        <small v-if="error" class="field-error">{{ error }}</small>
        <small v-else-if="field.hint">{{ field.hint }}</small>
    </div>
</template>
