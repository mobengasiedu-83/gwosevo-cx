<script setup lang="ts">
import { Link } from "@inertiajs/vue3";
import Icon from "./Icon.vue";
import { useApi } from "../composables/api";

const { url } = useApi();

const points = [
    {
        icon: "check",
        text: "Role-based access with mandatory two-factor enrolment.",
    },
    {
        icon: "route",
        text: "Skills-based routing, queue strategies and business hours.",
    },
    {
        icon: "activity",
        text: "Live operational context for every site and team.",
    },
];
</script>
<template>
    <div class="auth-page">
        <aside class="auth-visual">
            <Link :href="url('login')" class="brand brand-on-dark">
                <img
                    class="brand-mark"
                    :src="url('images/brand-mark.svg')"
                    alt=""
                    aria-hidden="true"
                />
                <span class="brand-text">
                    <span class="brand-name">GWO <span>SEVO</span></span>
                    <small>CONTACT CENTER</small>
                </span>
            </Link>
            <div class="auth-visual-copy">
                <p class="eyebrow">GWO SEVO CONTACT CENTER</p>
                <h2>Every conversation, routed with intent.</h2>
                <p class="muted">
                    A single command centre for people, queues and telephony —
                    built for administrators, supervisors and agents.
                </p>
                <ul class="auth-points">
                    <li v-for="point in points" :key="point.text">
                        <Icon :name="point.icon" :size="17" />
                        <span>{{ point.text }}</span>
                    </li>
                </ul>
            </div>
            <img
                class="auth-illustration"
                :src="url('images/auth-hero.svg')"
                alt=""
                aria-hidden="true"
            />
            <p class="auth-visual-foot">
                Secure by design · MFA ready · Tenant aware
            </p>
        </aside>
        <main class="auth-main">
            <div class="auth-brand-mobile">
                <Link :href="url('login')" class="brand">
                    <img
                        class="brand-mark"
                        :src="url('images/brand-mark.svg')"
                        alt=""
                        aria-hidden="true"
                    />
                    <span class="brand-text">
                        <span class="brand-name">GWO <span>SEVO</span></span>
                        <small>CONTACT CENTER</small>
                    </span>
                </Link>
            </div>
            <div class="panel auth-card">
                <slot />
            </div>
            <p class="auth-legal">
                © {{ new Date().getFullYear() }} GWO SEVO · Authorised users only
            </p>
        </main>
    </div>
</template>
