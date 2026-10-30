<script setup lang="ts">
import { Head, useForm } from "@inertiajs/vue3";
import { useApi } from "../../composables/api";
const { url } = useApi();
const form = useForm({ email: "", password: "" });
function submit() {
    form.post(url("login"), { onFinish: () => form.reset("password") });
}
</script>
<template>
    <Head title="Sign in" />
    <div class="auth-shell">
        <div class="brand">
            GWO <span>SEVO</span><small>CONTACT CENTER</small>
        </div>
        <form class="panel auth-card" @submit.prevent="submit">
            <p class="eyebrow">WELCOME BACK</p>
            <h1>Sign in to your workspace</h1>
            <p class="muted">
                Manage your people, queues and routing in one place.
            </p>
            <p
                v-for="error in form.errors"
                :key="error"
                class="error"
                role="alert"
            >
                {{ error }}
            </p>
            <label
                >Email address<input
                    v-model="form.email"
                    type="email"
                    autocomplete="username"
                    required
                    autofocus /></label
            ><label
                >Password<input
                    v-model="form.password"
                    type="password"
                    autocomplete="current-password"
                    required /></label
            ><button class="primary" :disabled="form.processing">Sign in</button
            ><small class="muted"
                >Contact your administrator for account access.</small
            >
        </form>
    </div>
</template>
