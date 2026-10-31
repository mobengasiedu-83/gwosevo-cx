<script setup lang="ts">
import { Head, useForm } from "@inertiajs/vue3";
import AuthShell from "../../components/AuthShell.vue";
import Icon from "../../components/Icon.vue";
import FieldLabel from "../../components/FieldLabel.vue";
import { useApi } from "../../composables/api";
const { url } = useApi();
const form = useForm({ email: "", password: "" });
function submit() {
    form.post(url("login"), { onFinish: () => form.reset("password") });
}
</script>
<template>
    <Head title="Sign in" />
    <AuthShell>
        <header class="auth-card-head">
            <span class="auth-badge">
                <Icon name="lock" :size="13" />
                WELCOME BACK
            </span>
            <h1>Sign in to your workspace</h1>
            <p class="muted">
                Manage your people, queues and routing in one place.
            </p>
        </header>
        <form class="auth-form" @submit.prevent="submit">
            <p
                v-for="error in form.errors"
                :key="error"
                class="error"
                role="alert"
            >
                {{ error }}
            </p>
            <div class="field">
                <FieldLabel for="login-email" label="Email address" required />
                <input
                    id="login-email"
                    v-model="form.email"
                    type="email"
                    autocomplete="username"
                    placeholder="you@company.com"
                    required
                    autofocus
                />
            </div>
            <div class="field">
                <FieldLabel for="login-password" label="Password" required />
                <input
                    id="login-password"
                    v-model="form.password"
                    type="password"
                    autocomplete="current-password"
                    placeholder="••••••••"
                    required
                />
            </div>
            <div class="form-actions">
                <button class="primary" :disabled="form.processing">
                    Sign in
                </button>
            </div>
            <p class="auth-hint">
                <Icon name="shield" :size="14" />
                Contact your administrator for account access.
            </p>
        </form>
    </AuthShell>
</template>

