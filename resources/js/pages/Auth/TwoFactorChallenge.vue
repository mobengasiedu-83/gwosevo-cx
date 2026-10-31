<script setup lang="ts">
import { ref } from "vue";
import { Head, useForm } from "@inertiajs/vue3";
import AuthShell from "../../components/AuthShell.vue";
import Icon from "../../components/Icon.vue";
import FieldLabel from "../../components/FieldLabel.vue";
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
    <AuthShell>
        <header class="auth-card-head">
            <span class="auth-badge">
                <Icon name="key" :size="13" />
                ACCOUNT SECURITY
            </span>
            <h1>Verify your sign in</h1>
            <p class="muted">
                {{
                    recovery
                        ? "Enter one of your saved recovery codes."
                        : "Enter the current six-digit code from your authenticator app."
                }}
            </p>
        </header>
        <form class="auth-form" @submit.prevent="submit">
            <p v-for="error in form.errors" :key="error" class="error">
                {{ error }}
            </p>
            <div v-if="!recovery" class="field">
                <FieldLabel
                    for="two-factor-code"
                    label="Authentication code"
                    required
                />
                <input
                    id="two-factor-code"
                    v-model="form.code"
                    inputmode="numeric"
                    autocomplete="one-time-code"
                    pattern="[0-9]{6}"
                    placeholder="000000"
                    class="code-input"
                    required
                    autofocus
                />
            </div>
            <div v-else class="field">
                <FieldLabel
                    for="two-factor-recovery"
                    label="Recovery code"
                    required
                />
                <input
                    id="two-factor-recovery"
                    v-model="form.recovery_code"
                    autocomplete="off"
                    placeholder="xxxxxxxx"
                    required
                />
            </div>
            <div class="form-actions">
                <button class="primary" :disabled="form.processing">
                    Verify
                </button>
                <button type="button" @click="recovery = !recovery">
                    <Icon name="refresh" :size="15" />
                    {{ recovery ? "Use authenticator" : "Use recovery code" }}
                </button>
            </div>
        </form>
    </AuthShell>
</template>

