<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from "vue";
import { Link } from "@inertiajs/vue3";
import type { NavigationGroup, SearchRecord } from "../types";
import Icon from "./Icon.vue";
import { useApi } from "../composables/api";
import {
    closeNavigation,
    expandedGroup,
    navigationOpen,
    openGroup,
    toggleGroup,
} from "../composables/navigation";

const { request, url } = useApi();

const props = defineProps<{
    groups: NavigationGroup[];
    selected?: string;
    portal: string;
    local?: boolean;
}>();
const emit = defineEmits<{ select: [resource: string] }>();

const search = ref("");
const term = computed(() => search.value.trim());
const records = ref<SearchRecord[]>([]),
    searching = ref(false);
let searchTimer: ReturnType<typeof setTimeout> | undefined,
    searchGeneration = 0;

// The directory also looks up records (sites, queues, agents…) so operators
// can jump straight from the sidebar. Lookups are debounced and only run in
// the administration portal, where the record pages live.
watch(search, () => {
    clearTimeout(searchTimer);
    if (props.portal !== "admin" || term.value.length < 2) {
        searchGeneration++;
        records.value = [];
        searching.value = false;

        return;
    }

    searching.value = true;
    searchTimer = setTimeout(async () => {
        const generation = ++searchGeneration;
        try {
            const result = await request(
                `admin-api/search?q=${encodeURIComponent(term.value)}`,
            );
            if (generation === searchGeneration) records.value = result.records;
        } catch {
            if (generation === searchGeneration) records.value = [];
        } finally {
            if (generation === searchGeneration) searching.value = false;
        }
    }, 250);
});

onBeforeUnmount(() => clearTimeout(searchTimer));

const visible = computed(() =>
    props.groups
        .map((g) => ({
            ...g,
            items: g.items.filter((i) =>
                `${g.title} ${i.title}`
                    .toLowerCase()
                    .includes(search.value.toLowerCase()),
            ),
        }))
        .filter((g) => g.items.length),
);
const sections = computed(() => [
    ...new Set(visible.value.map((g) => g.section)),
]);
const count = computed(() =>
    props.groups.reduce((sum, g) => sum + g.items.length, 0),
);
/**
 * Group that owns the current selection. A resource can be reachable from more
 * than one menu (queue strategies sit under call routing and call center), so
 * the group the operator is already working in keeps priority.
 */
function groupHolds(id: string, value?: string) {
    if (!value) return false;

    return !!props.groups
        .find((group) => group.id === id)
        ?.items.some((item) => item.id === value || item.resource === value);
}

function ownerGroup(value?: string) {
    if (!value) return undefined;

    return props.groups.find((group) =>
        group.items.some(
            (item) => item.id === value || item.resource === value,
        ),
    );
}

const activeGroup = computed(() => {
    const open = props.groups.find((group) => group.id === expandedGroup.value);

    return open && groupHolds(open.id, props.selected)
        ? open
        : ownerGroup(props.selected);
});
const expanded = (id: string) => !!search.value || expandedGroup.value === id;

watch(
    () => [props.selected, props.groups] as const,
    () => {
        if (groupHolds(expandedGroup.value ?? "", props.selected)) return;
        if (activeGroup.value) openGroup(activeGroup.value.id);
    },
    { immediate: true },
);

// Token references rather than literals: the accent palette lives in app.css,
// so the menu tint can never drift from the rest of the design system.
const accents = [
    "var(--indigo-400)",
    "var(--cyan-400)",
    "var(--violet-400)",
    "var(--green-400)",
    "var(--amber-400)",
    "var(--pink-400)",
];

const sectionIcons: Record<string, string> = {
    overview: "gauge",
    telephony: "phone",
    "routing & queues": "route",
    workforce: "headset",
    insights: "chart",
    platform: "server",
    security: "shield",
    developer: "code",
};

function iconFor(title: string) {
    const value = title.toLowerCase();

    if (value.includes("dashboard") || value.includes("monitor"))
        return "gauge";
    if (
        value.includes("extension") ||
        value.includes("device") ||
        value.includes("sip") ||
        value.includes("softphone") ||
        value.includes("number") ||
        value.includes("trunk") ||
        value.includes("phone")
    )
        return "phone";
    if (
        value.includes("routing") ||
        value.includes("queue") ||
        value.includes("ivr") ||
        value.includes("flow") ||
        value.includes("strategy")
    )
        return "route";
    if (
        value.includes("agent") ||
        value.includes("supervisor") ||
        value.includes("workforce") ||
        value.includes("team")
    )
        return "headset";
    if (value.includes("skill")) return "star";
    if (
        value.includes("schedule") ||
        value.includes("hours") ||
        value.includes("calendar")
    )
        return "calendar";
    if (
        value.includes("recording") ||
        value.includes("quality") ||
        value.includes("voice")
    )
        return "sparkles";
    if (
        value.includes("report") ||
        value.includes("analytic") ||
        value.includes("insight") ||
        value.includes("performance")
    )
        return "chart";
    if (
        value.includes("security") ||
        value.includes("access") ||
        value.includes("role") ||
        value.includes("permission") ||
        value.includes("audit")
    )
        return "shield";
    if (
        value.includes("integration") ||
        value.includes("api") ||
        value.includes("developer") ||
        value.includes("webhook")
    )
        return "code";
    if (
        value.includes("system") ||
        value.includes("server") ||
        value.includes("network") ||
        value.includes("tenant") ||
        value.includes("setting")
    )
        return "server";
    if (
        value.includes("campaign") ||
        value.includes("outbound") ||
        value.includes("contact") ||
        value.includes("customer")
    )
        return "megaphone";
    if (value.includes("ticket") || value.includes("case")) return "ticket";
    if (value.includes("document") || value.includes("knowledge"))
        return "book";

    return "grid";
}

const sectionIcon = (section: string) =>
    sectionIcons[section.toLowerCase()] || iconFor(section);

const accent = (section: string) => {
    const index = sections.value.indexOf(section);

    return accents[(index < 0 ? 0 : index) % accents.length];
};

function toggle(id: string) {
    toggleGroup(id);
}

function expandActive() {
    openGroup(activeGroup.value?.id ?? null);
}

function collapseAll() {
    openGroup(null);
    search.value = "";
    records.value = [];
    searching.value = false;
}

function select(resource: string) {
    if (!groupHolds(expandedGroup.value ?? "", resource)) {
        const group = ownerGroup(resource);
        if (group) openGroup(group.id);
    }
    emit("select", resource);
    closeNavigation();
}
</script>

<template>
    <div
        v-if="navigationOpen"
        class="nav-scrim"
        aria-hidden="true"
        @click="closeNavigation()"
    />
    <aside
        class="directory"
        :class="{ open: navigationOpen }"
        :aria-label="portal + ' navigation'"
    >
        <button
            type="button"
            class="directory-close"
            aria-label="Close navigation"
            @click="closeNavigation()"
        >
            <Icon name="close" :size="16" />
        </button>
        <div class="directory-heading">
            <div class="directory-eyebrow">
                <span class="status-dot" aria-hidden="true"></span>
                <p class="eyebrow">{{ portal }} WORKSPACE</p>
            </div>
            <p class="directory-title">Service catalogue</p>
            <small>{{ groups.length }} menus · {{ count }} entries</small>
        </div>
        <label class="navigation-search">
            <span>{{
                portal === "admin"
                    ? "Find a feature or record"
                    : "Find a feature"
            }}</span>
            <span class="search-control">
                <Icon name="search" :size="15" />
                <input
                    v-model="search"
                    type="search"
                    :placeholder="
                        portal === 'admin'
                            ? 'Search menus and records…'
                            : 'Search menus…'
                    "
                />
            </span>
        </label>
        <div class="directory-actions">
            <button
                type="button"
                :disabled="!activeGroup"
                @click="expandActive"
            >
                <Icon name="layers" :size="13" /><span>Current menu</span>
            </button>
            <button type="button" @click="collapseAll">
                <Icon name="filter" :size="13" /><span>Collapse menu</span>
            </button>
        </div>
        <div
            v-if="portal === 'admin' && term.length >= 2"
            class="search-results"
            aria-live="polite"
        >
            <div class="search-results-heading">
                <span>Records</span>
                <small>{{ searching ? "Searching…" : records.length }}</small>
            </div>
            <ul v-if="records.length">
                <li v-for="record in records" :key="record.type + record.id">
                    <button
                        v-if="local"
                        type="button"
                        class="search-record"
                        @click="select(record.type)"
                    >
                        <span class="search-record-title">{{
                            record.title
                        }}</span>
                        <small
                            >{{ record.label
                            }}<template v-if="record.subtitle">
                                · {{ record.subtitle }}</template
                            ></small
                        >
                    </button>
                    <Link
                        v-else
                        class="search-record"
                        :href="record.url"
                        @click="closeNavigation()"
                    >
                        <span class="search-record-title">{{
                            record.title
                        }}</span>
                        <small
                            >{{ record.label
                            }}<template v-if="record.subtitle">
                                · {{ record.subtitle }}</template
                            ></small
                        >
                    </Link>
                </li>
            </ul>
            <p v-else-if="!searching" class="search-results-empty">
                No records match “{{ term }}”.
            </p>
        </div>
        <div
            v-if="!visible.length && !records.length && !searching"
            class="empty-state"
        >
            <img
                :src="url('images/empty-state.svg')"
                alt=""
                aria-hidden="true"
            />
            <p>No matching features.</p>
        </div>
        <section
            v-for="section in sections"
            :key="section"
            class="navigation-section"
        >
            <h2>
                <Icon :name="sectionIcon(section)" :size="12" />
                {{ section }}
            </h2>
            <div
                v-for="group in visible.filter((g) => g.section === section)"
                :key="group.id"
                class="navigation-group"
                :style="{ '--accent': accent(section) }"
            >
                <button
                    type="button"
                    class="group-toggle"
                    :aria-expanded="expanded(group.id)"
                    @click="toggle(group.id)"
                >
                    <span class="group-icon" aria-hidden="true">
                        <Icon :name="iconFor(group.title)" :size="15" />
                    </span>
                    <span class="group-title">{{ group.title }}</span>
                    <small class="group-count" aria-hidden="true">{{
                        group.items.length
                    }}</small>
                    <Icon
                        name="chevron"
                        :size="14"
                        class="chevron"
                        aria-hidden="true"
                    />
                </button>
                <ul v-if="expanded(group.id)">
                    <li v-for="item in group.items" :key="item.id">
                        <button
                            v-if="
                                local &&
                                item.resource &&
                                !item.resource.startsWith('@')
                            "
                            type="button"
                            class="menu-item"
                            :class="{ selected: selected === item.resource }"
                            @click="select(item.resource)"
                        >
                            {{ item.title }}
                        </button>
                        <Link
                            v-else
                            class="menu-item"
                            :class="{ selected: selected === item.id }"
                            :href="item.url"
                            @click="closeNavigation()"
                        >
                            <span>{{ item.title }}</span>
                            <small
                                v-if="item.status === 'planned'"
                                class="planned-badge"
                                >Planned</small
                            >
                        </Link>
                    </li>
                </ul>
            </div>
        </section>
    </aside>
</template>
