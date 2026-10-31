<script setup lang="ts">
import { computed, onMounted, ref, watch } from "vue";
import { Head, router, usePage } from "@inertiajs/vue3";
import AppLayout from "../../components/AppLayout.vue";
import FieldControl from "../../components/FieldControl.vue";
import FieldLabel from "../../components/FieldLabel.vue";
import NavigationPanel from "../../components/NavigationPanel.vue";
import Icon from "../../components/Icon.vue";
import PageHeader from "../../components/PageHeader.vue";
import PaginationBar from "../../components/PaginationBar.vue";
import StatusButton from "../../components/StatusButton.vue";
import StatusPill from "../../components/StatusPill.vue";
import { useApi } from "../../composables/api";
import type {
    FieldSpec,
    NavigationGroup,
    RecordItem,
    SharedProps,
} from "../../types";

interface ExtensionOptions {
    technologies: Record<string, string>;
    classes: Record<string, string>;
    languages: Record<string, string>;
    dtmfModes: Record<string, string>;
    transports: Record<string, string>;
    recordingModes: Record<string, string>;
    voicemailOptions: Record<string, string>;
}

const props = defineProps<{
    navigation: NavigationGroup[];
    selectedModule: string;
    options: ExtensionOptions;
}>();

const page = usePage<SharedProps>();
const { request, message, url } = useApi();

const can = (permission: string) =>
    page.props.auth?.permissions.includes(permission) ?? false;
const may = (action: "create" | "update" | "delete") =>
    can("contact-center.manage") || can(`extensions.${action}`);

const tabs = [
    { id: "general", label: "General" },
    { id: "voicemail", label: "Voicemail" },
    { id: "recording", label: "Recording" },
    { id: "advanced", label: "Advanced" },
    { id: "follow-me", label: "Follow Me" },
    { id: "incoming-routes", label: "Incoming Routes" },
    { id: "contact", label: "Contact Info" },
];

// Validation errors are mapped back to the tab that owns the field so the
// operator always lands on the offending control.
const tabFields: Record<string, string> = {
    number: "general",
    name: "general",
    site_id: "general",
    active: "general",
    class_of_service: "general",
    features_password: "general",
    email_address: "general",
    language: "general",
    internal_cid_name: "general",
    internal_cid_number: "general",
    external_cid_name: "general",
    external_cid_number: "general",
    emergency_cid_name: "general",
    emergency_cid_number: "general",
    account_code: "general",
    technology: "general",
    user_device: "general",
    device_password: "general",
    device_description: "general",
    profile: "general",
    max_contacts: "general",
    codecs: "general",
    dtmf_mode: "general",
    device_emergency_cid_name: "general",
    device_emergency_cid_number: "general",
    dispatchable_location: "general",
    deny: "general",
    permit: "general",
    ring_device: "general",
    webrtc_client: "general",
    voicemail_enabled: "voicemail",
    voicemail_mailbox: "voicemail",
    voicemail_password: "voicemail",
    voicemail_email: "voicemail",
    voicemail_options: "voicemail",
    recording_mode: "recording",
    call_group: "advanced",
    pickup_group: "advanced",
    transport: "advanced",
    max_call_duration: "advanced",
    call_waiting: "advanced",
    follow_me_enabled: "follow-me",
    follow_me: "follow-me",
    incoming_routes: "incoming-routes",
    contact_company: "contact",
    contact_position: "contact",
    contact_phone: "contact",
    contact_mobile: "contact",
    contact_notes: "contact",
};

const rows = ref<RecordItem[]>([]),
    sites = ref<RecordItem[]>([]),
    total = ref(0),
    currentPage = ref(1),
    lastPage = ref(1),
    busy = ref(false),
    saving = ref(false),
    toggling = ref<string | number | null>(null),
    status = ref(""),
    term = ref(""),
    editor = ref(false),
    editing = ref<string | number | null>(null),
    tab = ref("general"),
    errors = ref<Record<string, string>>({}),
    revealed = ref<Record<string, boolean>>({}),
    stored = ref({ features: false, device: false, voicemail: false });
const form = ref<Record<string, any>>(blank());
let searchTimer: ReturnType<typeof setTimeout> | undefined;

function blank(): Record<string, any> {
    return {
        number: "",
        name: "",
        site_id: "",
        active: "true",
        class_of_service: "all",
        features_password: "",
        email_address: "",
        internal_cid_name: "",
        internal_cid_number: "",
        external_cid_name: "",
        external_cid_number: "",
        emergency_cid_name: "",
        emergency_cid_number: "",
        account_code: "",
        language: "en",
        technology: "pjsip",
        user_device: "",
        device_password: "",
        device_description: "",
        profile: "default",
        max_contacts: 5,
        codecs: "ulaw,alaw",
        dtmf_mode: "rfc4733",
        device_emergency_cid_name: "",
        device_emergency_cid_number: "",
        dispatchable_location: "",
        deny: "",
        permit: "",
        ring_device: "true",
        webrtc_client: "false",
        voicemail_enabled: "false",
        voicemail_mailbox: "",
        voicemail_password: "",
        voicemail_email: "",
        voicemail_options: [],
        recording_mode: "disabled",
        call_group: "",
        pickup_group: "",
        transport: "udp",
        max_call_duration: "",
        call_waiting: "true",
        follow_me_enabled: "false",
        follow_me: [],
        incoming_routes: [],
        contact_company: "",
        contact_position: "",
        contact_phone: "",
        contact_mobile: "",
        contact_notes: "",
    };
}

const identityFields = computed<FieldSpec[]>(() => [
    { key: "number", label: "Extension", required: true, inputmode: "numeric" },
    { key: "name", label: "Name", required: true },
]);

const settingFields = computed<FieldSpec[]>(() => [
    {
        key: "class_of_service",
        label: "Class of Service",
        type: "select",
        options: props.options.classes,
    },
    {
        key: "features_password",
        label: "Features Password",
        type: "password",
        reveal: true,
        hint: stored.value.features
            ? "A password is stored. Leave blank to keep it."
            : "Protects call features dialled from the device.",
    },
    { key: "email_address", label: "Email Address", type: "email" },
]);

const connectivityFields = computed<FieldSpec[]>(() => [
    { key: "account_code", label: "Account Code" },
    {
        key: "language",
        label: "Language",
        type: "select",
        options: props.options.languages,
    },
]);

const endpointFields = computed<FieldSpec[]>(() => [
    {
        key: "dispatchable_location",
        label: "Dispatchable Location",
        hint: "Emergency address or location identifier sent with 911 calls.",
    },
    {
        key: "deny",
        label: "Deny",
        hint: "Networks blocked from registering, for example 0.0.0.0/0.",
    },
    {
        key: "permit",
        label: "Permit",
        hint: "Restrict registrations to these networks.",
    },
]);

const deviceFields = computed<FieldSpec[]>(() => [
    {
        key: "user_device",
        label: "User Device",
        required: true,
        hint: "Endpoint name the phone registers with. Defaults to the extension number.",
    },
    {
        key: "device_password",
        label: "Password",
        type: "password",
        required: true,
        reveal: true,
        hint: stored.value.device
            ? "A password is stored. Leave blank to keep it."
            : "At least 8 characters, shared with the phone during provisioning.",
    },
    { key: "device_description", label: "Device Description", required: true },
    {
        key: "profile",
        label: "Profile",
        hint: "PJSIP or IAX2 profile applied when the endpoint is provisioned.",
    },
    { key: "max_contacts", label: "Max Contacts", type: "number" },
    {
        key: "codecs",
        label: "Codecs",
        hint: "Comma separated, in order of preference.",
    },
    {
        key: "dtmf_mode",
        label: "DTMF Mode",
        type: "select",
        options: props.options.dtmfModes,
    },
]);

const voicemailFields = computed<FieldSpec[]>(() => [
    {
        key: "voicemail_mailbox",
        label: "Mailbox",
        inputmode: "numeric",
        hint: "Defaults to the extension number when left blank.",
    },
    {
        key: "voicemail_password",
        label: "Voicemail Password",
        type: "password",
        reveal: true,
        hint: stored.value.voicemail
            ? "A password is stored. Leave blank to keep it."
            : "Used by the handset to collect messages.",
    },
    { key: "voicemail_email", label: "Email Address", type: "email" },
]);

const recordingFields = computed<FieldSpec[]>(() => [
    {
        key: "recording_mode",
        label: "Call Recording",
        type: "select",
        options: props.options.recordingModes,
        hint: "Always records every call, on demand only, or never.",
    },
]);

const advancedFields = computed<FieldSpec[]>(() => [
    {
        key: "call_group",
        label: "Call Group",
        hint: "Group this extension belongs to for group pickup.",
    },
    {
        key: "pickup_group",
        label: "Pickup Group",
        hint: "Groups whose ringing extensions this phone may answer.",
    },
    {
        key: "transport",
        label: "Transport",
        type: "select",
        options: props.options.transports,
    },
    {
        key: "max_call_duration",
        label: "Max Call Duration",
        type: "number",
        hint: "Seconds before the call is dropped. Leave blank for no limit.",
    },
]);

const contactFields = computed<FieldSpec[]>(() => [
    { key: "contact_company", label: "Company" },
    { key: "contact_position", label: "Position" },
    { key: "contact_phone", label: "Phone", type: "tel" },
    { key: "contact_mobile", label: "Mobile", type: "tel" },
]);

const cidPairs = [
    { key: "internal_cid", label: "Internal CID" },
    { key: "external_cid", label: "External CID" },
    { key: "emergency_cid", label: "Emergency CID" },
];

const siteName = (id: string | number) =>
    sites.value.find((site) => site.id === id)?.name ?? "—";
const technologyLabel = (value: string) =>
    props.options.technologies[value] || value || "None";

// Accessible labels for repeater rows are one based regardless of the index
// type Vue infers from an untyped array.
const rowLabel = (index: string | number) => Number(index) + 1;

const classLabel = (value: string) =>
    props.options.classes[value] || value || "All Permissions";

function errorBag(error: unknown): Record<string, string> {
    const bag = (
        error as { response?: { data?: { errors?: Record<string, string[]> } } }
    ).response?.data?.errors;

    return Object.fromEntries(
        Object.entries(bag || {}).map(([field, messages]) => [
            field,
            messages[0],
        ]),
    );
}

function focusFirstError() {
    const field = Object.keys(errors.value)[0];
    if (!field) return;
    tab.value = tabFields[field.split(".")[0]] || "general";
}

async function load() {
    busy.value = true;
    try {
        const query = term.value.trim()
            ? `&q=${encodeURIComponent(term.value.trim())}`
            : "";
        const result = await request(
            `admin-api/extensions?page=${currentPage.value}${query}`,
        );
        rows.value = result.data;
        total.value = result.total;
        lastPage.value = result.last_page;
        currentPage.value = result.current_page;
    } catch (error) {
        status.value = message(error);
    } finally {
        busy.value = false;
    }
}

// PaginationBar reports the page; this page owns the request.
function goto(page: number) {
    currentPage.value = page;
    load();
}

async function loadSites() {
    const collected: RecordItem[] = [];
    let page = 1;
    let pages = 1;
    try {
        do {
            const result = await request(`admin-api/sites?page=${page}`);
            collected.push(...result.data);
            pages = result.last_page;
            page++;
        } while (page <= pages);
        sites.value = collected;
    } catch {
        sites.value = [];
    }
}

// New endpoints start with a strong throwaway password that the operator can
// copy into the phone or replace before saving.
function randomPassword(length = 16) {
    const alphabet =
        "ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789";
    const values = new Uint32Array(length);
    crypto.getRandomValues(values);

    return Array.from(
        values,
        (value) => alphabet[value % alphabet.length],
    ).join("");
}

function create() {
    errors.value = {};
    revealed.value = {};
    stored.value = { features: false, device: false, voicemail: false };
    const state = blank();
    state.site_id = sites.value[0]?.id ?? "";
    state.device_password = randomPassword();
    form.value = state;
    editing.value = null;
    tab.value = "general";
    status.value = "";
    editor.value = true;
}

function edit(row: RecordItem) {
    errors.value = {};
    revealed.value = {};
    stored.value = {
        features: Boolean(row.has_features_password),
        device: Boolean(row.has_device_password),
        voicemail: Boolean(row.has_voicemail_password),
    };
    const state = blank();
    Object.keys(state).forEach((key) => {
        const value = row[key];
        if (value === undefined || value === null) return;
        if (Array.isArray(state[key])) {
            state[key] = Array.isArray(value)
                ? value.map((entry: RecordItem) => ({ ...entry }))
                : state[key];

            return;
        }
        if (typeof state[key] === "string" && typeof value === "boolean") {
            state[key] = value ? "true" : "false";

            return;
        }
        state[key] = value;
    });
    state.max_call_duration = state.max_call_duration ?? "";
    form.value = state;
    editing.value = row.id;
    tab.value = "general";
    status.value = "";
    editor.value = true;
}

function closeEditor() {
    editor.value = false;
    editing.value = null;
    errors.value = {};
}

function select(resource: string) {
    if (resource === "extensions") return;
    router.visit(`${url("admin")}?module=${encodeURIComponent(resource)}`);
}

function payload() {
    const data: Record<string, any> = { ...form.value };
    [
        "active",
        "ring_device",
        "webrtc_client",
        "voicemail_enabled",
        "follow_me_enabled",
        "call_waiting",
    ].forEach((key) => {
        data[key] = data[key] === "true" || data[key] === true;
    });
    data.max_contacts = Number(data.max_contacts) || 5;
    data.max_call_duration =
        data.max_call_duration === "" || data.max_call_duration === null
            ? null
            : Number(data.max_call_duration);
    data.follow_me = (data.follow_me || [])
        .filter((rule: RecordItem) => String(rule.number ?? "").trim())
        .map((rule: RecordItem) => ({
            number: String(rule.number).trim(),
            timeout: Number(rule.timeout) || 20,
        }));
    data.incoming_routes = (data.incoming_routes || [])
        .filter((route: RecordItem) => String(route.did ?? "").trim())
        .map((route: RecordItem) => ({
            did: String(route.did).trim(),
            description: route.description ? String(route.description) : null,
        }));
    ["features_password", "device_password", "voicemail_password"].forEach(
        (key) => {
            if (!data[key]) delete data[key];
        },
    );

    return data;
}

async function save() {
    saving.value = true;
    errors.value = {};
    status.value = "";
    try {
        if (editing.value) {
            await request(
                `admin-api/extensions/${editing.value}`,
                "PUT",
                payload(),
            );
            status.value = "Extension updated.";
        } else {
            await request("admin-api/extensions", "POST", payload());
            status.value = "Extension created.";
        }
        closeEditor();
        await load();
    } catch (error) {
        errors.value = errorBag(error);
        focusFirstError();
        status.value = message(error);
    } finally {
        saving.value = false;
    }
}

async function remove(row: RecordItem) {
    if (!window.confirm(`Delete extension ${row.number}?`)) return;
    busy.value = true;
    try {
        await request(`admin-api/extensions/${row.id}`, "DELETE");
        status.value = "Extension deleted.";
        await load();
    } catch (error) {
        status.value = message(error);
    } finally {
        busy.value = false;
    }
}

// Activation is a one tap action from the list: the row is patched in place so
// the table keeps its scroll position and the pagination never reloads.
async function toggleStatus(row: RecordItem) {
    if (toggling.value !== null) return;
    toggling.value = row.id;
    status.value = "";
    const next = !row.active;
    try {
        const updated = await request(
            `admin-api/extensions/${row.id}/status`,
            "PATCH",
            { active: next },
        );
        const index = rows.value.findIndex((entry) => entry.id === row.id);
        if (index >= 0)
            rows.value[index] = {
                ...rows.value[index],
                ...updated,
                active:
                    typeof updated?.active === "boolean"
                        ? updated.active
                        : next,
            };
        status.value = next
            ? `Extension ${row.number} activated.`
            : `Extension ${row.number} deactivated.`;
    } catch (error) {
        status.value = message(error);
    } finally {
        toggling.value = null;
    }
}

watch(term, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        currentPage.value = 1;
        load();
    }, 250);
});

// A new endpoint mirrors the extension number until the operator overrides it.
watch(
    () => form.value.number,
    (value, previous) => {
        if (editing.value) return;
        if (!form.value.user_device || form.value.user_device === previous)
            form.value.user_device = value;
        if (
            !form.value.device_description ||
            form.value.device_description === previous
        )
            form.value.device_description = value;
    },
);

onMounted(() => {
    load();
    loadSites();
});
</script>

<template>
    <Head title="Extensions" />
    <AppLayout>
        <div class="workspace">
            <NavigationPanel
                :groups="navigation"
                portal="admin"
                :selected="selectedModule"
                local
                @select="select"
            />
            <div class="content">
                <PageHeader
                    v-if="!editor"
                    eyebrow="PBX / ADMINISTRATION"
                    title="Extensions"
                    description="Extension numbers, endpoint devices, voicemail and inbound routing."
                >
                    <template #actions>
                        <button
                            v-if="may('create')"
                            class="primary"
                            :disabled="busy"
                            @click="create"
                        >
                            + New extension
                        </button>
                    </template>
                </PageHeader>
                <p v-if="status" class="notice" role="status">{{ status }}</p>
                <section v-if="!editor" class="panel">
                    <div class="table-heading">
                        <h2>
                            Extensions <span class="count">{{ total }}</span>
                        </h2>
                        <div class="table-tools">
                            <input
                                v-model="term"
                                type="search"
                                class="table-search"
                                placeholder="Search extension, name or device…"
                                aria-label="Search extensions"
                            />
                            <button
                                type="button"
                                :disabled="busy"
                                @click="load"
                            >
                                Refresh
                            </button>
                        </div>
                    </div>
                    <div v-if="rows.length" class="table-scroll">
                        <table>
                            <thead>
                                <tr>
                                    <th>Extension</th>
                                    <th>Name</th>
                                    <th>Site</th>
                                    <th>Device</th>
                                    <th>Class of Service</th>
                                    <th>Status</th>
                                    <th>
                                        <span class="sr-only">Actions</span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in rows" :key="row.id">
                                    <td>
                                        <button
                                            v-if="may('update')"
                                            type="button"
                                            class="row-link mono"
                                            :aria-label="
                                                'Open extension ' + row.number
                                            "
                                            :title="
                                                'Edit extension ' + row.number
                                            "
                                            :disabled="busy"
                                            @click="edit(row)"
                                        >
                                            {{ row.number }}
                                        </button>
                                        <span v-else class="mono">{{
                                            row.number
                                        }}</span>
                                    </td>
                                    <td>{{ row.name }}</td>
                                    <td>{{ siteName(row.site_id) }}</td>
                                    <td>
                                        <span class="device-cell">
                                            <strong>{{
                                                technologyLabel(row.technology)
                                            }}</strong>
                                            <small v-if="row.user_device">{{
                                                row.user_device
                                            }}</small>
                                        </span>
                                    </td>
                                    <td>
                                        {{ classLabel(row.class_of_service) }}
                                    </td>
                                    <td>
                                        <StatusPill
                                            :active="Boolean(row.active)"
                                            inactive-label="Disabled"
                                        />
                                    </td>
                                    <td>
                                        <div class="row-actions">
                                            <StatusButton
                                                v-if="may('update')"
                                                :active="Boolean(row.active)"
                                                :label="
                                                    'extension ' + row.number
                                                "
                                                :busy="
                                                    busy || toggling === row.id
                                                "
                                                @toggle="toggleStatus(row)"
                                            />
                                            <button
                                                v-if="may('update')"
                                                type="button"
                                                class="icon-btn"
                                                :aria-label="
                                                    'Edit extension ' +
                                                    row.number
                                                "
                                                :disabled="busy"
                                                @click="edit(row)"
                                            >
                                                <Icon name="edit" :size="16" />
                                            </button>
                                            <button
                                                v-if="may('delete')"
                                                type="button"
                                                class="icon-btn danger"
                                                :aria-label="
                                                    'Delete extension ' +
                                                    row.number
                                                "
                                                :disabled="busy"
                                                @click="remove(row)"
                                            >
                                                <Icon name="trash" :size="16" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div v-if="!rows.length && !busy" class="empty-state">
                        <img :src="url('images/empty-state.svg')" alt="" />
                        <p>
                            No extensions yet. Create the first extension
                            number to get started.
                        </p>
                    </div>
                    <PaginationBar
                        :page="currentPage"
                        :last-page="lastPage"
                        :busy="busy"
                        @change="goto"
                    />
                </section>
                <section v-if="editor" class="panel editor">
                    <div class="editor-head">
                        <div>
                            <button
                                type="button"
                                class="link-back"
                                @click="closeEditor"
                            >
                                ← Extensions
                            </button>
                            <h1>
                                {{
                                    editing
                                        ? "Extension " + (form.number || "…")
                                        : "New extension"
                                }}
                            </h1>
                            <p class="muted">
                                {{ form.name || "Unnamed extension" }} ·
                                {{ technologyLabel(form.technology) }} ·
                                {{ siteName(form.site_id) }}
                            </p>
                        </div>
                        <button
                            type="button"
                            class="ghost btn-sm"
                            @click="closeEditor"
                        >
                            Close
                        </button>
                    </div>
                    <div
                        class="tab-strip"
                        role="tablist"
                        aria-label="Extension settings"
                    >
                        <button
                            v-for="item in tabs"
                            :key="item.id"
                            type="button"
                            role="tab"
                            :id="'tab-' + item.id"
                            :aria-selected="tab === item.id"
                            :aria-controls="'panel-' + item.id"
                            @click="tab = item.id"
                        >
                            {{ item.label }}
                        </button>
                    </div>
                    <form @submit.prevent="save">
                        <div
                            v-if="tab === 'general'"
                            id="panel-general"
                            role="tabpanel"
                            aria-labelledby="tab-general"
                        >
                            <section class="form-section">
                                <h2>Extension</h2>
                                <div class="form-columns">
                                    <div class="field-stack">
                                        <FieldControl
                                            v-for="field in identityFields"
                                            :key="field.key"
                                            :field="field"
                                            :id="'ext-' + field.key"
                                            v-model="form[field.key]"
                                            :error="errors[field.key]"
                                        />
                                        <div class="field">
                                            <FieldLabel
                                                for="ext-site"
                                                label="Site"
                                                required
                                            />
                                            <select
                                                id="ext-site"
                                                v-model="form.site_id"
                                                required
                                            >
                                                <option
                                                    v-if="!sites.length"
                                                    :value="''"
                                                >
                                                    No accessible sites
                                                </option>
                                                <option
                                                    v-for="site in sites"
                                                    :key="site.id"
                                                    :value="site.id"
                                                >
                                                    {{ site.name }}
                                                </option>
                                            </select>
                                            <small
                                                v-if="errors.site_id"
                                                class="field-error"
                                                >{{ errors.site_id }}</small
                                            >
                                        </div>
                                        <FieldControl
                                            v-for="field in settingFields"
                                            :key="field.key"
                                            :field="field"
                                            :id="'ext-' + field.key"
                                            v-model="form[field.key]"
                                            :error="errors[field.key]"
                                        />
                                        <div class="field">
                                            <label id="ext-status-label"
                                                >Status</label
                                            >
                                            <div
                                                class="segmented"
                                                role="group"
                                                aria-labelledby="ext-status-label"
                                            >
                                                <button
                                                    type="button"
                                                    :aria-pressed="
                                                        form.active === 'true'
                                                    "
                                                    @click="
                                                        form.active = 'true'
                                                    "
                                                >
                                                    Enabled
                                                </button>
                                                <button
                                                    type="button"
                                                    :aria-pressed="
                                                        form.active === 'false'
                                                    "
                                                    @click="
                                                        form.active = 'false'
                                                    "
                                                >
                                                    Disabled
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="field-stack">
                                        <div
                                            v-for="pair in cidPairs"
                                            :key="pair.key"
                                            class="pair"
                                        >
                                            <span class="pair-label">{{
                                                pair.label
                                            }}</span>
                                            <div class="pair-inputs">
                                                <input
                                                    :id="
                                                        'ext-' +
                                                        pair.key +
                                                        '-name'
                                                    "
                                                    v-model="
                                                        form[pair.key + '_name']
                                                    "
                                                    placeholder="Name"
                                                    :aria-label="
                                                        pair.label + ' name'
                                                    "
                                                    autocomplete="off"
                                                />
                                                <input
                                                    :id="
                                                        'ext-' +
                                                        pair.key +
                                                        '-number'
                                                    "
                                                    v-model="
                                                        form[
                                                            pair.key + '_number'
                                                        ]
                                                    "
                                                    placeholder="Number"
                                                    :aria-label="
                                                        pair.label + ' number'
                                                    "
                                                    autocomplete="off"
                                                />
                                            </div>
                                            <small
                                                v-if="
                                                    errors[
                                                        pair.key + '_name'
                                                    ] ||
                                                    errors[pair.key + '_number']
                                                "
                                                class="field-error"
                                                >{{
                                                    errors[
                                                        pair.key + "_name"
                                                    ] ||
                                                    errors[pair.key + "_number"]
                                                }}</small
                                            >
                                        </div>
                                        <FieldControl
                                            v-for="field in connectivityFields"
                                            :key="field.key"
                                            :field="field"
                                            :id="'ext-' + field.key"
                                            v-model="form[field.key]"
                                            :error="errors[field.key]"
                                        />
                                    </div>
                                </div>
                            </section>
                            <section class="form-section">
                                <h2>Devices</h2>
                                <div class="form-columns">
                                    <div class="field-stack">
                                        <div class="field">
                                            <label id="ext-technology-label"
                                                >Technology</label
                                            >
                                            <div
                                                class="segmented"
                                                role="group"
                                                aria-labelledby="ext-technology-label"
                                            >
                                                <button
                                                    v-for="(
                                                        text, value
                                                    ) in options.technologies"
                                                    :key="value"
                                                    type="button"
                                                    :aria-pressed="
                                                        form.technology ===
                                                        value
                                                    "
                                                    @click="
                                                        form.technology = value
                                                    "
                                                >
                                                    {{ text }}
                                                </button>
                                            </div>
                                            <small
                                                v-if="
                                                    form.technology === 'none'
                                                "
                                            >
                                                No endpoint is provisioned for
                                                this extension.
                                            </small>
                                            <small
                                                v-if="errors.technology"
                                                class="field-error"
                                                >{{ errors.technology }}</small
                                            >
                                        </div>
                                        <FieldControl
                                            v-for="field in deviceFields"
                                            :key="field.key"
                                            :field="field"
                                            :id="'ext-' + field.key"
                                            v-model="form[field.key]"
                                            :error="errors[field.key]"
                                            :required="
                                                field.required &&
                                                form.technology !== 'none'
                                            "
                                        />
                                    </div>
                                    <div class="field-stack">
                                        <div class="pair">
                                            <span class="pair-label"
                                                >Emergency CID</span
                                            >
                                            <div class="pair-inputs">
                                                <input
                                                    id="ext-device-emergency-cid-name"
                                                    v-model="
                                                        form.device_emergency_cid_name
                                                    "
                                                    placeholder="Name"
                                                    aria-label="Device emergency CID name"
                                                    autocomplete="off"
                                                />
                                                <input
                                                    id="ext-device-emergency-cid-number"
                                                    v-model="
                                                        form.device_emergency_cid_number
                                                    "
                                                    placeholder="Number"
                                                    aria-label="Device emergency CID number"
                                                    autocomplete="off"
                                                />
                                            </div>
                                        </div>
                                        <FieldControl
                                            v-for="field in endpointFields"
                                            :key="field.key"
                                            :field="field"
                                            :id="'ext-' + field.key"
                                            v-model="form[field.key]"
                                            :error="errors[field.key]"
                                        />
                                        <div class="field">
                                            <label id="ext-ring-label"
                                                >Ring Device</label
                                            >
                                            <div
                                                class="segmented"
                                                role="group"
                                                aria-labelledby="ext-ring-label"
                                            >
                                                <button
                                                    type="button"
                                                    :aria-pressed="
                                                        form.ring_device ===
                                                        'true'
                                                    "
                                                    @click="
                                                        form.ring_device =
                                                            'true'
                                                    "
                                                >
                                                    Yes
                                                </button>
                                                <button
                                                    type="button"
                                                    :aria-pressed="
                                                        form.ring_device ===
                                                        'false'
                                                    "
                                                    @click="
                                                        form.ring_device =
                                                            'false'
                                                    "
                                                >
                                                    No
                                                </button>
                                            </div>
                                        </div>
                                        <div class="field">
                                            <label id="ext-webrtc-label"
                                                >WebRTC Client</label
                                            >
                                            <div
                                                class="segmented"
                                                role="group"
                                                aria-labelledby="ext-webrtc-label"
                                            >
                                                <button
                                                    type="button"
                                                    :aria-pressed="
                                                        form.webrtc_client ===
                                                        'true'
                                                    "
                                                    @click="
                                                        form.webrtc_client =
                                                            'true'
                                                    "
                                                >
                                                    Yes
                                                </button>
                                                <button
                                                    type="button"
                                                    :aria-pressed="
                                                        form.webrtc_client ===
                                                        'false'
                                                    "
                                                    @click="
                                                        form.webrtc_client =
                                                            'false'
                                                    "
                                                >
                                                    No
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </section>
                        </div>
                        <div
                            v-if="tab === 'voicemail'"
                            id="panel-voicemail"
                            role="tabpanel"
                            aria-labelledby="tab-voicemail"
                        >
                            <section class="form-section">
                                <h2>Voicemail</h2>
                                <div class="form-columns">
                                    <div class="field-stack">
                                        <div class="field">
                                            <label id="ext-voicemail-label"
                                                >Voicemail</label
                                            >
                                            <div
                                                class="segmented"
                                                role="group"
                                                aria-labelledby="ext-voicemail-label"
                                            >
                                                <button
                                                    type="button"
                                                    :aria-pressed="
                                                        form.voicemail_enabled ===
                                                        'true'
                                                    "
                                                    @click="
                                                        form.voicemail_enabled =
                                                            'true'
                                                    "
                                                >
                                                    Enabled
                                                </button>
                                                <button
                                                    type="button"
                                                    :aria-pressed="
                                                        form.voicemail_enabled ===
                                                        'false'
                                                    "
                                                    @click="
                                                        form.voicemail_enabled =
                                                            'false'
                                                    "
                                                >
                                                    Disabled
                                                </button>
                                            </div>
                                            <small>
                                                Unanswered calls are sent to
                                                this mailbox after the ring
                                                timeout.
                                            </small>
                                        </div>
                                        <FieldControl
                                            v-for="field in voicemailFields"
                                            :key="field.key"
                                            :field="field"
                                            :id="'ext-' + field.key"
                                            v-model="form[field.key]"
                                            :error="errors[field.key]"
                                        />
                                    </div>
                                    <div class="field-stack">
                                        <div class="field">
                                            <label
                                                id="ext-voicemail-options-label"
                                                >Options</label
                                            >
                                            <div
                                                class="checkboxes"
                                                role="group"
                                                aria-labelledby="ext-voicemail-options-label"
                                            >
                                                <label
                                                    v-for="(
                                                        text, value
                                                    ) in options.voicemailOptions"
                                                    :key="value"
                                                    class="checkbox"
                                                >
                                                    <input
                                                        type="checkbox"
                                                        :value="value"
                                                        v-model="
                                                            form.voicemail_options
                                                        "
                                                    />
                                                    <span>{{ text }}</span>
                                                </label>
                                            </div>
                                            <small
                                                v-if="errors.voicemail_options"
                                                class="field-error"
                                                >{{
                                                    errors.voicemail_options
                                                }}</small
                                            >
                                        </div>
                                    </div>
                                </div>
                            </section>
                        </div>
                        <div
                            v-if="tab === 'recording'"
                            id="panel-recording"
                            role="tabpanel"
                            aria-labelledby="tab-recording"
                        >
                            <section class="form-section">
                                <h2>Recording</h2>
                                <div class="form-columns">
                                    <div class="field-stack">
                                        <FieldControl
                                            v-for="field in recordingFields"
                                            :key="field.key"
                                            :field="field"
                                            :id="'ext-' + field.key"
                                            v-model="form[field.key]"
                                            :error="errors[field.key]"
                                        />
                                    </div>
                                    <div class="field-stack">
                                        <p class="field-note">
                                            Recordings are stored per site under
                                            the extension number. On-demand
                                            recordings start when the handset
                                            dials the feature code configured
                                            for this tenant.
                                        </p>
                                    </div>
                                </div>
                            </section>
                        </div>
                        <div
                            v-if="tab === 'advanced'"
                            id="panel-advanced"
                            role="tabpanel"
                            aria-labelledby="tab-advanced"
                        >
                            <section class="form-section">
                                <h2>Advanced</h2>
                                <div class="form-columns">
                                    <div class="field-stack">
                                        <FieldControl
                                            v-for="field in advancedFields"
                                            :key="field.key"
                                            :field="field"
                                            :id="'ext-' + field.key"
                                            v-model="form[field.key]"
                                            :error="errors[field.key]"
                                        />
                                    </div>
                                    <div class="field-stack">
                                        <div class="field">
                                            <label id="ext-waiting-label"
                                                >Call Waiting</label
                                            >
                                            <div
                                                class="segmented"
                                                role="group"
                                                aria-labelledby="ext-waiting-label"
                                            >
                                                <button
                                                    type="button"
                                                    :aria-pressed="
                                                        form.call_waiting ===
                                                        'true'
                                                    "
                                                    @click="
                                                        form.call_waiting =
                                                            'true'
                                                    "
                                                >
                                                    Enabled
                                                </button>
                                                <button
                                                    type="button"
                                                    :aria-pressed="
                                                        form.call_waiting ===
                                                        'false'
                                                    "
                                                    @click="
                                                        form.call_waiting =
                                                            'false'
                                                    "
                                                >
                                                    Disabled
                                                </button>
                                            </div>
                                            <small>
                                                Allows a second call to ring
                                                while the line is in use.
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </section>
                        </div>
                        <div
                            v-if="tab === 'follow-me'"
                            id="panel-follow-me"
                            role="tabpanel"
                            aria-labelledby="tab-follow-me"
                        >
                            <section class="form-section">
                                <h2>Follow Me</h2>
                                <div class="field">
                                    <label id="ext-followme-label"
                                        >Follow Me</label
                                    >
                                    <div
                                        class="segmented"
                                        role="group"
                                        aria-labelledby="ext-followme-label"
                                    >
                                        <button
                                            type="button"
                                            :aria-pressed="
                                                form.follow_me_enabled ===
                                                'true'
                                            "
                                            @click="
                                                form.follow_me_enabled = 'true'
                                            "
                                        >
                                            Enabled
                                        </button>
                                        <button
                                            type="button"
                                            :aria-pressed="
                                                form.follow_me_enabled ===
                                                'false'
                                            "
                                            @click="
                                                form.follow_me_enabled = 'false'
                                            "
                                        >
                                            Disabled
                                        </button>
                                    </div>
                                    <small>
                                        Rings the numbers below in order when
                                        the extension is not answered.
                                    </small>
                                </div>
                                <div class="repeater">
                                    <div
                                        class="repeater-head"
                                        aria-hidden="true"
                                    >
                                        <span>Number</span>
                                        <span>Ring (seconds)</span>
                                        <span></span>
                                    </div>
                                    <div
                                        v-for="(rule, index) in form.follow_me"
                                        :key="index"
                                        class="repeater-row"
                                    >
                                        <input
                                            v-model="rule.number"
                                            :aria-label="
                                                'Follow me number ' +
                                                rowLabel(index)
                                            "
                                            placeholder="e.g. 5551234"
                                            autocomplete="off"
                                        />
                                        <input
                                            v-model.number="rule.timeout"
                                            type="number"
                                            min="5"
                                            max="120"
                                            :aria-label="
                                                'Ring seconds for number ' +
                                                rowLabel(index)
                                            "
                                        />
                                        <button
                                            type="button"
                                            class="icon-btn danger"
                                            :aria-label="
                                                'Remove follow me number ' +
                                                rowLabel(index)
                                            "
                                            @click="
                                                form.follow_me.splice(index, 1)
                                            "
                                        >
                                            <Icon name="trash" :size="16" />
                                        </button>
                                    </div>
                                    <p
                                        v-if="!form.follow_me.length"
                                        class="empty-inline"
                                    >
                                        No numbers configured. The extension
                                        rings only its own device.
                                    </p>
                                </div>
                                <button
                                    type="button"
                                    class="add-row"
                                    @click="
                                        form.follow_me.push({
                                            number: '',
                                            timeout: 20,
                                        })
                                    "
                                >
                                    + Add number
                                </button>
                                <small
                                    v-if="errors.follow_me"
                                    class="field-error"
                                    >{{ errors.follow_me }}</small
                                >
                            </section>
                        </div>
                        <div
                            v-if="tab === 'incoming-routes'"
                            id="panel-incoming-routes"
                            role="tabpanel"
                            aria-labelledby="tab-incoming-routes"
                        >
                            <section class="form-section">
                                <h2>Incoming Routes</h2>
                                <p class="field-note">
                                    Direct inward dial numbers that ring this
                                    extension. Numbers are matched exactly as
                                    the carrier delivers them.
                                </p>
                                <div class="repeater">
                                    <div
                                        class="repeater-head wide"
                                        aria-hidden="true"
                                    >
                                        <span>DID</span>
                                        <span>Description</span>
                                        <span></span>
                                    </div>
                                    <div
                                        v-for="(
                                            route, index
                                        ) in form.incoming_routes"
                                        :key="index"
                                        class="repeater-row wide"
                                    >
                                        <input
                                            v-model="route.did"
                                            :aria-label="
                                                'DID ' + rowLabel(index)
                                            "
                                            placeholder="+15551234567"
                                            autocomplete="off"
                                        />
                                        <input
                                            v-model="route.description"
                                            :aria-label="
                                                'Description for DID ' +
                                                rowLabel(index)
                                            "
                                            placeholder="Main line"
                                            autocomplete="off"
                                        />
                                        <button
                                            type="button"
                                            class="icon-btn danger"
                                            :aria-label="
                                                'Remove DID ' + rowLabel(index)
                                            "
                                            @click="
                                                form.incoming_routes.splice(
                                                    index,
                                                    1,
                                                )
                                            "
                                        >
                                            <Icon name="trash" :size="16" />
                                        </button>
                                    </div>
                                    <p
                                        v-if="!form.incoming_routes.length"
                                        class="empty-inline"
                                    >
                                        No direct inward dials assigned to this
                                        extension.
                                    </p>
                                </div>
                                <button
                                    type="button"
                                    class="add-row"
                                    @click="
                                        form.incoming_routes.push({
                                            did: '',
                                            description: '',
                                        })
                                    "
                                >
                                    + Add DID
                                </button>
                                <small
                                    v-if="errors.incoming_routes"
                                    class="field-error"
                                    >{{ errors.incoming_routes }}</small
                                >
                            </section>
                        </div>
                        <div
                            v-if="tab === 'contact'"
                            id="panel-contact"
                            role="tabpanel"
                            aria-labelledby="tab-contact"
                        >
                            <section class="form-section">
                                <h2>Contact Info</h2>
                                <div class="form-columns">
                                    <div class="field-stack">
                                        <FieldControl
                                            v-for="field in contactFields"
                                            :key="field.key"
                                            :field="field"
                                            :id="'ext-' + field.key"
                                            v-model="form[field.key]"
                                            :error="errors[field.key]"
                                        />
                                    </div>
                                    <div class="field-stack">
                                        <div class="field">
                                            <FieldLabel
                                                for="ext-contact_notes"
                                                label="Notes"
                                            />
                                            <textarea
                                                id="ext-contact_notes"
                                                v-model="form.contact_notes"
                                                rows="6"
                                                placeholder="Directory notes, department, escalation details…"
                                            ></textarea>
                                            <small
                                                v-if="errors.contact_notes"
                                                class="field-error"
                                                >{{
                                                    errors.contact_notes
                                                }}</small
                                            >
                                        </div>
                                    </div>
                                </div>
                            </section>
                        </div>
                        <div class="form-actions">
                            <button
                                class="primary"
                                type="submit"
                                :disabled="saving"
                            >
                                {{
                                    saving
                                        ? "Saving…"
                                        : editing
                                          ? "Save changes"
                                          : "Create extension"
                                }}
                            </button>
                            <button
                                type="button"
                                :disabled="saving"
                                @click="closeEditor"
                            >
                                Cancel
                            </button>
                            <small
                                v-if="Object.keys(errors).length"
                                class="field-error"
                            >
                                Check the highlighted fields before saving.
                            </small>
                        </div>
                    </form>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
