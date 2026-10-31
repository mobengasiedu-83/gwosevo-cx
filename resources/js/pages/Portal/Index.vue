<script setup lang="ts">
import { computed } from "vue";
import { Head, Link } from "@inertiajs/vue3";
import AppLayout from "../../components/AppLayout.vue";
import NavigationPanel from "../../components/NavigationPanel.vue";
import Icon from "../../components/Icon.vue";
import PageHeader from "../../components/PageHeader.vue";
import { useApi } from "../../composables/api";
import type { NavigationGroup, NavigationItem } from "../../types";
const props = defineProps<{
    portal: string;
    navigation: NavigationGroup[];
    selected?: NavigationItem;
    category?: string;
}>();
const { url } = useApi();
const portalLabel = computed(() =>
    props.portal === "admin"
        ? "Administration"
        : props.portal === "supervisor"
          ? "Supervisor"
          : "Agent",
);
const title = computed(
    () => props.selected?.title || `${portalLabel.value} workspace`,
);
const entries = computed(() =>
    props.navigation.reduce((sum, group) => sum + group.items.length, 0),
);
const live = computed(() =>
    props.navigation.reduce(
        (sum, group) =>
            sum + group.items.filter((item) => item.status !== "planned").length,
        0,
    ),
);
</script>
<template>
    <Head :title="title" />
    <AppLayout>
        <div class="workspace">
            <NavigationPanel
                :groups="navigation"
                :portal="portal"
                :selected="selected?.id"
            />
            <div class="content">
                <section v-if="!selected" class="portal-hero">
                    <div class="portal-hero-copy">
                        <p class="eyebrow">
                            GWO SEVO / {{ category || portalLabel }}
                        </p>
                        <h1>{{ title }}</h1>
                        <p class="muted">
                            Browse your workspace or search the directory to
                            find a feature. Available entries open working
                            configuration screens; planned entries show their
                            implementation status.
                        </p>
                        <div class="portal-hero-actions">
                            <Link class="button primary" :href="url('security')">
                                <Icon name="shield" :size="16" />
                                Review account security
                            </Link>
                        </div>
                        <ul class="hero-tags">
                            <li>{{ navigation.length }} menus</li>
                            <li>{{ entries }} entries</li>
                            <li>{{ live }} available now</li>
                        </ul>
                    </div>
                    <figure class="portal-hero-figure">
                        <img
                            :src="url('images/portal-hero.svg')"
                            alt=""
                            aria-hidden="true"
                        />
                    </figure>
                </section>
                <template v-else>
                    <PageHeader
                        :eyebrow="`GWO SEVO / ${category || portalLabel}`"
                        :title="title"
                    >
                        <template #actions>
                            <span class="badge inactive">Planned feature</span>
                        </template>
                    </PageHeader>
                    <section class="panel feature-status">
                        <span class="planned-badge">Planned feature</span>
                        <h2>Implementation is pending</h2>
                        <p>
                            This feature is included in the platform menu
                            specification. Its backend and operational workflow
                            have not been implemented yet.
                        </p>
                        <p class="muted">
                            This screen does not provision devices, place calls,
                            modify server settings or display simulated live
                            metrics.
                        </p>
                    </section>
                </template>
                <div v-if="!selected" class="portal-grid">
                    <section
                        v-for="group in navigation"
                        :key="group.id"
                        class="panel portal-group"
                    >
                        <h2>{{ group.title }}</h2>
                        <ul>
                            <li v-for="item in group.items" :key="item.id">
                                <Link :href="item.url">{{ item.title }}</Link>
                                <span
                                    v-if="item.status === 'planned'"
                                    class="planned-badge"
                                    >Planned</span
                                >
                            </li>
                        </ul>
                    </section>
                </div>
            </div>
        </div>
    </AppLayout>
</template>


