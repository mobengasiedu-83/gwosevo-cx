<script setup lang="ts">
import { Head, useForm } from "@inertiajs/vue3";
import AuthShell from "../../components/AuthShell.vue";
import Icon from "../../components/Icon.vue";
import FieldLabel from "../../components/FieldLabel.vue";
import { useApi } from "../../composables/api";
const { url } = useApi(),
    form = useForm({ password: "" });
</script>
<template>
    <Head title="Confirm password" />
    <AuthShell>
        <header class="auth-card-head">
            <span class="auth-badge">
                <Icon name="lock" :size="13" />
                SECURE AREA
            </span>
            <h1>Confirm your password</h1>
            <p class="muted">
                For your security, please re-enter your password to continue.
            </p>
        </header>
        <form
            class="auth-form"
            @submit.prevent="
                form.post(url('user/confirm-password'), {
                    onFinish: () => form.reset(),
                })
            "
        >
            <p v-for="error in form.errors" :key="error" class="error">
                {{ error }}
            </p>
            <div class="field">
                <FieldLabel for="confirm-password" label="Password" required />
                <input
                    id="confirm-password"
                    v-model="form.password"
                    type="password"
                    autocomplete="current-password"
                    placeholder="••••••••"
                    required
                    autofocus
                />
            </div>
            <div class="form-actions">
                <button class="primary" :disabled="form.processing">
                    Continue
                </button>
            </div>
        </form>
    </AuthShell>
</template>

