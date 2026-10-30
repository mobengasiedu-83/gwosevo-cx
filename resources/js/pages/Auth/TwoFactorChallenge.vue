<script setup lang="ts">
import { ref } from "vue";
import { Head, useForm } from "@inertiajs/vue3";
import { useApi } from "../../composables/api";
const recovery = ref(false),
    form = useForm({ code: "", recovery_code: "" });
const { url } = useApi();
function submit() {
    if (recovery.value) form.code = "";
    else form.recovery_code = "";
    form.post(url("two-factor-challenge"), { onFinish: () => form.reset() });
}
</script>
<template>
    <Head title="Verify sign in" />
    <div class="auth-shell">
        <form class="panel auth-card" @submit.prevent="submit">
            <p class="eyebrow">ACCOUNT SECURITY</p>
            <h1>Verify your sign in</h1>
            <p class="muted">
                {{
                    recovery
                        ? "Enter one of your saved recovery codes."
                        : "Enter the current six-digit code from your authenticator app."
                }}
            </p>
            <p v-for="error in form.errors" :key="error" class="error">
                {{ error }}
            </p>
            <label v-if="!recovery"
                >Authentication code<input
                    v-model="form.code"
                    inputmode="numeric"
                    autocomplete="one-time-code"
                    pattern="[0-9]{6}"
                    required
                    autofocus /></label
            ><label v-else
                >Recovery code<input
                    v-model="form.recovery_code"
                    autocomplete="off"
                    required /></label
            ><button class="primary" :disabled="form.processing">Verify</button
            ><button type="button" @click="recovery = !recovery">
                {{ recovery ? "Use authenticator" : "Use recovery code" }}
            </button>
        </form>
    </div>
</template>
