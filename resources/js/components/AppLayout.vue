<script setup lang="ts">
import { Link, router, usePage } from "@inertiajs/vue3";
import type { SharedProps } from "../types";
import { useApi } from "../composables/api";
const page = usePage<SharedProps>();
const { url } = useApi();
</script>
<template>
    <header class="topbar">
        <Link :href="url('admin')" class="brand"
            >GWO <span>SEVO</span><small>CONTACT CENTER</small></Link
        >
        <div class="account">
            <span>{{ page.props.auth?.name }}</span
            ><Link :href="url('security')">Security</Link
            ><button @click="router.post(url('logout'))">Sign out</button>
        </div>
    </header>
    <main><slot /></main>
</template>
