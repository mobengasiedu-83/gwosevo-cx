<script setup lang="ts">
import { computed, watch } from "vue";
import { Link, router, usePage } from "@inertiajs/vue3";
import type { SharedProps } from "../types";
import { useApi } from "../composables/api";
import Icon from "./Icon.vue";
import {
    closeNavigation,
    navigationOpen,
    toggleNavigation,
} from "../composables/navigation";

const page = usePage<SharedProps>();
const { url } = useApi();

const portals = computed(() =>
    (page.props.portals || []).map((portal) => ({
        id: portal,
        label:
            portal === "admin"
                ? "Administration"
                : portal === "supervisor"
                  ? "Supervisor"
                  : "Agent",
        icon:
            portal === "admin"
                ? "settings"
                : portal === "supervisor"
                  ? "chart"
                  : "headset",
    })),
);

const initials = computed(() => {
    const name = page.props.auth?.name?.trim();
    if (!name) return "GS";

    return name
        .split(/\s+/)
        .slice(0, 2)
        .map((part) => part.charAt(0).toUpperCase())
        .join("");
});

const currentPath = computed(() => page.url.split("?")[0].replace(/\/$/, ""));

// Login and MFA land users in their first permitted workspace. The brand must
// follow that same destination so Agent and Supervisor accounts never bounce
// into an administration route they cannot open.
const home = computed(
    () =>
        page.props.home ||
        (portals.value[0] ? `/${portals.value[0].id}` : "/login"),
);

function isActive(portal: string) {
    const target = url(portal).replace(/\/$/, "");

    return (
        currentPath.value === target ||
        currentPath.value.startsWith(`${target}/`)
    );
}

function signOut() {
    router.post(url("logout"));
}

watch(
    () => page.url,
    () => closeNavigation(),
);
</script>
<template>
    <div class="app-shell">
        <header class="topbar">
            <div class="topbar-inner">
                <button
                    type="button"
                    class="mobile-nav-toggle"
                    :aria-label="
                        navigationOpen ? 'Close navigation' : 'Open navigation'
                    "
                    :aria-expanded="navigationOpen"
                    @click="toggleNavigation"
                >
                    <Icon
                        :name="navigationOpen ? 'close' : 'menu'"
                        :size="19"
                    />
                </button>
                <Link :href="url(home)" class="brand">
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
                <nav class="portal-switch" aria-label="Portals">
                    <Link
                        v-for="portal in portals"
                        :key="portal.id"
                        :href="url(portal.id)"
                        :class="{ active: isActive(portal.id) }"
                    >
                        <Icon :name="portal.icon" :size="15" />
                        <span>{{ portal.label }}</span>
                    </Link>
                </nav>
                <div class="account">
                    <Link
                        :href="url('security')"
                        class="account-link"
                        aria-label="Security"
                    >
                        <Icon name="shield" :size="16" />
                        <span>Security</span>
                    </Link>
                    <span class="user-chip">
                        <span class="avatar" aria-hidden="true">{{
                            initials
                        }}</span>
                        <span class="user-meta">
                            <strong>{{ page.props.auth?.name }}</strong>
                            <small>{{ page.props.auth?.email }}</small>
                        </span>
                    </span>
                    <button type="button" class="btn-signout" @click="signOut">
                        <Icon name="logout" :size="16" />
                        <span>Sign out</span>
                    </button>
                </div>
            </div>
        </header>
        <main class="app-main"><slot /></main>
    </div>
</template>
