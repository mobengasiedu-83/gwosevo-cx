<script setup lang="ts">
import { Head, useForm } from "@inertiajs/vue3";
import { useApi } from "../../composables/api";
const { url } = useApi(),
    form = useForm({ password: "" });
</script>
<template>
    <Head title="Confirm password" />
    <div class="auth-shell">
        <form
            class="panel auth-card"
            @submit.prevent="
                form.post(url('user/confirm-password'), {
                    onFinish: () => form.reset(),
                })
            "
        >
            <h1>Confirm your password</h1>
            <p v-for="error in form.errors" :key="error" class="error">
                {{ error }}
            </p>
            <label
                >Password<input
                    v-model="form.password"
                    type="password"
                    autocomplete="current-password"
                    required /></label
            ><button class="primary" :disabled="form.processing">
                Continue
            </button>
        </form>
    </div>
</template>
