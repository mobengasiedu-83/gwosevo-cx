<script setup lang="ts">
import { ref, computed, watch, onMounted } from "vue";
import { Head, router, usePage } from "@inertiajs/vue3";
import NavigationPanel from "../../components/NavigationPanel.vue";
import type { NavigationGroup } from "../../types";
import AppLayout from "../../components/AppLayout.vue";
import PageHeader from "../../components/PageHeader.vue";
import PaginationBar from "../../components/PaginationBar.vue";
import StatusPill from "../../components/StatusPill.vue";
import FieldLabel from "../../components/FieldLabel.vue";
import { useApi } from "../../composables/api";
import { schema, defaults, label } from "../../schema";
import type { SharedProps, RecordItem } from "../../types";
const props = defineProps<{
    navigation: NavigationGroup[];
    selectedModule: string;
}>();
const page = usePage<SharedProps>(),
    { request, message, url } = useApi();
const can = (p: string) => page.props.auth?.permissions.includes(p) ?? false;
const module = ref(props.selectedModule),
    rows = ref<RecordItem[]>([]),
    currentPage = ref(1),
    lastPage = ref(1),
    total = ref(0),
    busy = ref(false),
    status = ref(""),
    editor = ref(false),
    editing = ref<string | number | null>(null);
const form = ref<Record<string, any>>({}),
    choices = ref<Record<string, RecordItem[]>>({}),
    roleList = ref<RecordItem[]>([]),
    permissions = ref<string[]>([]);
let generation = 0;
const modules = computed(() => [
    // Extensions render through their own tabbed editor, so the generic record
    // table skips them while still exposing the relation for agent pickers.
    ...Object.keys(schema).filter(
        (r) =>
            r !== "extensions" &&
            (can("contact-center.view") || can(r + ".view")),
    ),
    ...(can("users.view") ? ["accounts"] : []),
    ...(can("roles.view") ? ["roles"] : []),
    ...(can("settings.view") ? ["settings"] : []),
    ...(can("audit-logs.view") ? ["audits"] : []),
]);
const editable = computed(() =>
    module.value === "accounts"
        ? can("users.manage")
        : module.value === "roles"
          ? can("roles.manage")
          : module.value === "settings"
            ? can("settings.update")
            : module.value === "audits"
              ? false
              : can("contact-center.manage") ||
                can(module.value + "." + (editing.value ? "update" : "create")),
);
const fields = computed(() =>
    module.value === "settings"
        ? {
              display_name: "text",
              timezone: "timezone",
              contact_email: "email?",
              default_wrapup_seconds: "number",
          }
        : module.value === "roles"
          ? { name: "text", permissions: "permissions" }
          : module.value === "accounts"
            ? {
                  name: "text",
                  email: "email",
                  password: "password?",
                  active: ["true", "false"],
                  restrict_site_access: ["false", "true"],
                  roles: "roles",
                  sites: "site-access",
              }
            : {
                  name: "text",
                  active: ["true", "false"],
                  ...schema[module.value]?.fields,
              },
);
const relation = (spec: any) =>
    typeof spec === "string" ? spec.replace("?", "") : "";
// A two-option "true"/"false" array is a boolean flag. Those render as the
// same segmented Yes/No control the extension editor uses, instead of a
// dropdown that reads "True"/"False".
const isBoolean = (spec: any) =>
    Array.isArray(spec) &&
    spec.every((option) => option === "true" || option === "false");
const isSelect = (spec: any) =>
    !isBoolean(spec) &&
    (Array.isArray(spec) ||
        relation(spec) in schema ||
        relation(spec) === "users");
function options(spec: any): RecordItem[] {
    const type = relation(spec);
    let items = choices.value[type] || [];
    if (
        ["departments", "teams", "extensions"].includes(type) &&
        form.value.site_id
    )
        items = items.filter((i) => i.site_id === form.value.site_id);
    return items;
}
async function all(resource: string) {
    let result,
        items: RecordItem[] = [],
        p = 1;
    do {
        result = await request("admin-api/" + resource + "?page=" + p++);
        if (Array.isArray(result)) return result;
        items.push(...result.data);
    } while (result.next_page_url);
    return items;
}
async function load() {
    const version = ++generation,
        resource = module.value;
    busy.value = true;
    try {
        const result = await request(
            "admin-api/" + resource + "?page=" + currentPage.value,
        );
        if (version !== generation) return;
        if (resource === "settings") {
            form.value = result;
            editor.value = true;
            editing.value = "settings";
            rows.value = [];
        } else if (resource === "roles") {
            rows.value = result.roles;
            permissions.value = result.permissions;
            total.value = rows.value.length;
            lastPage.value = 1;
        } else if (Array.isArray(result)) {
            rows.value = result;
            total.value = result.length;
            lastPage.value = 1;
        } else {
            rows.value = result.data;
            total.value = result.total;
            lastPage.value = result.last_page;
        }
    } catch (e) {
        status.value = message(e);
    } finally {
        if (version === generation) busy.value = false;
    }
}
// PaginationBar is presentation only: it reports the requested page and the
// page owns the request, so every list pages the same way.
function goto(page: number) {
    currentPage.value = page;
    load();
}
watch(module, () => {
    currentPage.value = 1;
    editor.value = false;
    status.value = "";
    load();
});
// Inertia reuses this component for /admin?module=… visits (for example
// history navigation), so keep the local module in sync with the server prop.
watch(
    () => props.selectedModule,
    (value) => {
        if (value && value !== module.value) module.value = value;
    },
);
onMounted(() => {
    if (!modules.value.includes(module.value))
        module.value = modules.value[0] || "sites";
    else load();
});
// Switching modules stays client side, except for extensions which need the
// dedicated editor page rendered by the server route. The URL must come from
// the shared base so the visit still resolves when the app is mounted in a
// sub-directory; a root-relative "/admin" would escape it and 404 at the
// web server before Laravel ever sees the request.
function select(resource: string) {
    if (resource === "extensions") {
        router.get(url("admin"), { module: resource });
        return;
    }
    module.value = resource;
}
async function open(row?: RecordItem) {
    status.value = "";
    busy.value = true;
    try {
        const resource = module.value;
        const state: Record<string, any> = {
            ...structuredClone(defaults),
            name: "",
            active: "true",
            restrict_site_access: "false",
            roles: [],
            sites: [],
            permissions: [],
            ...row,
        };
        if (row) {
            state.active = String(row.active);
            state.restrict_site_access = String(
                row.restrict_site_access ?? false,
            );
            if (resource === "roles") {
                state.name = row.name.includes(":")
                    ? row.name.split(":").slice(1).join(":")
                    : row.name;
                state.permissions = row.permissions.map((p: any) => p.name);
            }
            if (resource in schema) {
                for (const key of ["skills", "members"])
                    if (Array.isArray(row[key])) {
                        const pivot =
                            key === "members"
                                ? "priority"
                                : resource === "agents"
                                  ? "proficiency"
                                  : "minimum_proficiency";
                        state[key] = row[key].map((i: any) => ({
                            id: i.id,
                            [pivot]: i.pivot[pivot],
                        }));
                    }
            }
        }
        state.password = "";
        const refs = new Set<string>();
        for (const spec of Object.values(
            resource === "accounts"
                ? { sites: "site-access", roles: "roles" }
                : schema[resource]?.fields || {},
        )) {
            const type = relation(spec);
            if (type in schema || type === "users") refs.add(type);
            if (type.includes("skills")) refs.add("skills");
            if (type === "members") refs.add("agents");
            if (type === "site-access") refs.add("sites");
        }
        const entries = await Promise.all(
            [...refs].map(async (r) => [r, await all(r)] as const),
        );
        if (module.value !== resource) return;
        choices.value = Object.fromEntries(entries);
        if (resource === "accounts")
            roleList.value = (await request("admin-api/roles")).roles;
        form.value = state;
        editing.value = row?.id ?? null;
        editor.value = true;
    } catch (e) {
        status.value = message(e);
    } finally {
        busy.value = false;
    }
}
async function save() {
    busy.value = true;
    status.value = "";
    try {
        const data = JSON.parse(JSON.stringify(form.value));
        if ("active" in data) data.active = data.active === "true";
        if ("restrict_site_access" in data)
            data.restrict_site_access = data.restrict_site_access === "true";
        for (const [k, s] of Object.entries(fields.value)) {
            if (s === "number") data[k] = Number(data[k]);
            if (typeof s === "string" && s.endsWith("?") && data[k] === "")
                data[k] = null;
        }
        let path = "admin-api/" + module.value;
        let method = editing.value ? "PUT" : "POST";
        if (module.value === "settings") {
            method = "PUT";
        } else if (editing.value) path += "/" + editing.value;
        await request(path, method, data);
        editor.value = false;
        status.value = "Configuration saved.";
        await load();
    } catch (e) {
        status.value = message(e);
    } finally {
        busy.value = false;
    }
}
async function remove(row: RecordItem) {
    if (!confirm(`Delete ${row.name}?`)) return;
    try {
        await request(`admin-api/${module.value}/${row.id}`, "DELETE");
        status.value = "Record deleted.";
        await load();
    } catch (e) {
        status.value = message(e);
    }
}
async function revoke(row: RecordItem) {
    if (!confirm(`Revoke all sessions for ${row.name}?`)) return;
    try {
        await request(`admin-api/accounts/${row.id}/revoke`, "POST");
        status.value = "Sessions revoked.";
    } catch (e) {
        status.value = message(e);
    }
}
function addItem(key: string, spec: any) {
    if (!Array.isArray(form.value[key])) form.value[key] = [];
    form.value[key].push(
        spec === "schedule"
            ? { day: 1, opens: "09:00", closes: "17:00" }
            : spec === "holidays"
              ? ""
              : {
                    id: "",
                    [spec === "members"
                        ? "priority"
                        : spec === "agent-skills"
                          ? "proficiency"
                          : "minimum_proficiency"]: spec === "members" ? 0 : 1,
                },
    );
}
function toggle(key: string, id: any) {
    const values = form.value[key] || [];
    form.value[key] = values.includes(id)
        ? values.filter((v: any) => v !== id)
        : [...values, id];
}
const json = (value: any) => {
    try {
        return JSON.stringify(
            typeof value === "string" ? JSON.parse(value) : value,
            null,
            2,
        );
    } catch {
        return String(value);
    }
};
</script>
<template>
    <Head :title="label(module)" /><AppLayout
        ><div class="workspace">
            <NavigationPanel
                :groups="navigation"
                portal="admin"
                :selected="module"
                local
                @select="select"
            />
            <div class="content">
                <PageHeader
                    eyebrow="CONTACT CENTER / ADMINISTRATION"
                    :title="module === 'accounts' ? 'Users' : label(module)"
                    :description="
                        schema[module]?.description ||
                        (module === 'audits'
                            ? 'Review configuration and account activity.'
                            : module === 'settings'
                              ? 'Manage your tenant defaults.'
                              : 'Manage access within your organization.')
                    "
                >
                    <template #actions>
                        <button
                            v-if="
                                module !== 'settings' &&
                                module !== 'audits' &&
                                editable
                            "
                            class="primary"
                            :disabled="busy"
                            @click="open()"
                        >
                            + Create
                            {{ module === "accounts" ? "user" : "record" }}
                        </button>
                    </template>
                </PageHeader>
                <p v-if="status" class="notice" role="status">{{ status }}</p>
                <section v-if="module !== 'settings'" class="panel">
                    <div class="table-heading">
                        <h2>
                            {{ label(module) }}
                            <span class="count">{{ total }}</span>
                        </h2>
                        <span v-if="busy" class="muted">Loading…</span>
                    </div>
                    <div class="table-scroll">
                        <table>
                            <thead>
                                <tr>
                                    <th>
                                        {{
                                            module === "audits"
                                                ? "Action"
                                                : "Name"
                                        }}
                                    </th>
                                    <th>
                                        {{
                                            module === "audits"
                                                ? "Resource"
                                                : module === "accounts"
                                                  ? "Email"
                                                  : "Status"
                                        }}
                                    </th>
                                    <th>
                                        {{
                                            module === "audits"
                                                ? "When"
                                                : "Details"
                                        }}
                                    </th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in rows" :key="row.id">
                                    <td>{{ row.name || row.action }}</td>
                                    <td v-if="module === 'audits'">
                                        {{ row.resource }}
                                    </td>
                                    <td v-else-if="module === 'accounts'">
                                        {{ row.email }}
                                    </td>
                                    <td v-else>
                                        <StatusPill
                                            :active="row.active !== false"
                                        />
                                    </td>
                                    <td>
                                        <span v-if="module === 'audits'"
                                            >{{ row.created_at }} UTC</span
                                        ><span v-else-if="module === 'accounts'"
                                            >MFA
                                            {{
                                                row.mfa_enabled
                                                    ? "enabled"
                                                    : "pending"
                                            }}</span
                                        ><small v-else>{{ row.id }}</small>
                                    </td>
                                    <td>
                                        <details v-if="module === 'audits'">
                                            <summary>View changes</summary>
                                            <pre>{{ json(row.changes) }}</pre>
                                        </details>
                                        <div v-else class="row-actions">
                                            <button
                                                v-if="
                                                    (editable ||
                                                        can(
                                                            module + '.update',
                                                        )) &&
                                                    (module !== 'roles' ||
                                                        row.tenant_id)
                                                "
                                                class="btn-sm"
                                                @click="open(row)"
                                            >
                                                Edit
                                            </button>
                                            <button
                                                v-if="
                                                    module in schema &&
                                                    (can(
                                                        'contact-center.manage',
                                                    ) ||
                                                        can(
                                                            module + '.delete',
                                                        ))
                                                "
                                                class="btn-sm danger"
                                                @click="remove(row)"
                                            >
                                                Delete
                                            </button>
                                            <button
                                                v-if="
                                                    module === 'accounts' &&
                                                    can('users.manage')
                                                "
                                                class="btn-sm"
                                                @click="revoke(row)"
                                            >
                                                Revoke sessions
                                            </button>
                                            <small
                                                v-if="
                                                    module === 'roles' &&
                                                    !row.tenant_id
                                                "
                                                >Built-in role</small
                                            >
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="!rows.length && !busy">
                                    <td colspan="4" class="empty">
                                        No records yet. Create your first record
                                        to get started.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <PaginationBar
                        :page="currentPage"
                        :last-page="lastPage"
                        :busy="busy"
                        @change="goto"
                    />
                </section>
                <section v-if="editor" class="panel editor">
                    <div class="table-heading">
                        <h2>
                            {{
                                module === "settings"
                                    ? "Tenant settings"
                                    : (editing ? "Edit " : "Create ") +
                                      label(module)
                            }}
                        </h2>
                        <button
                            v-if="module !== 'settings'"
                            class="ghost btn-sm"
                            @click="editor = false"
                        >
                            Close
                        </button>
                    </div>
                    <form @submit.prevent="save">
                        <div class="field-grid">
                            <div
                                class="field"
                                v-for="(spec, key) in fields"
                                :key="key"
                                :class="{
                                    wide: [
                                        'schedule',
                                        'holidays',
                                        'members',
                                        'agent-skills',
                                        'queue-skills',
                                        'roles',
                                        'site-access',
                                        'permissions',
                                    ].includes(relation(spec)),
                                }"
                            >
                                <FieldLabel
                                    :for="'field-' + key"
                                    :label="label(key)"
                                    :required="
                                        !isBoolean(spec) &&
                                        !String(spec).endsWith('?')
                                    "
                                />
                                <div
                                    v-if="isBoolean(spec)"
                                    :id="'field-' + key"
                                    class="segmented"
                                    role="group"
                                    :aria-label="label(key)"
                                >
                                    <button
                                        v-for="option in spec"
                                        :key="option"
                                        type="button"
                                        :aria-pressed="
                                            String(form[key]) === option
                                        "
                                        @click="form[key] = option"
                                    >
                                        {{ option === "true" ? "Yes" : "No" }}
                                    </button>
                                </div>
                                <select
                                    :id="'field-' + key"
                                    v-else-if="isSelect(spec)"
                                    v-model="form[key]"
                                    :required="!String(spec).endsWith('?')"
                                >
                                    <template v-if="Array.isArray(spec)"
                                        ><option
                                            v-for="option in spec"
                                            :key="option"
                                            :value="option"
                                        >
                                            {{ label(option) }}
                                        </option></template
                                    ><template v-else
                                        ><option :value="null">Choose…</option>
                                        <option
                                            v-for="option in options(spec)"
                                            :key="option.id"
                                            :value="option.id"
                                        >
                                            {{ option.name }}
                                        </option></template
                                    >
                                </select>
                                <div
                                    v-else-if="
                                        [
                                            'roles',
                                            'site-access',
                                            'permissions',
                                        ].includes(relation(spec))
                                    "
                                    class="checkboxes"
                                >
                                    <label
                                        v-for="option in spec === 'roles'
                                            ? roleList
                                            : spec === 'permissions'
                                              ? permissions.map((p) => ({
                                                    id: p,
                                                    name: p,
                                                }))
                                              : choices.sites || []"
                                        :key="option.id"
                                        class="checkbox"
                                        ><input
                                            type="checkbox"
                                            :checked="
                                                (form[key] || []).includes(
                                                    option.id,
                                                )
                                            "
                                            @change="toggle(key, option.id)"
                                        />{{ option.name }}</label
                                    ><small v-if="spec === 'site-access'"
                                        >Applies when Restrict Site Access is
                                        true.</small
                                    >
                                </div>
                                <div
                                    v-else-if="
                                        [
                                            'schedule',
                                            'holidays',
                                            'members',
                                            'agent-skills',
                                            'queue-skills',
                                        ].includes(relation(spec))
                                    "
                                    class="assignment"
                                >
                                    <div
                                        v-for="(item, index) in form[key]"
                                        :key="index"
                                        class="assignment-row"
                                    >
                                        <template v-if="spec === 'schedule'"
                                            ><select
                                                v-model.number="item.day"
                                                aria-label="Weekday"
                                            >
                                                <option
                                                    v-for="(day, i) in [
                                                        'Monday',
                                                        'Tuesday',
                                                        'Wednesday',
                                                        'Thursday',
                                                        'Friday',
                                                        'Saturday',
                                                        'Sunday',
                                                    ]"
                                                    :key="day"
                                                    :value="i + 1"
                                                >
                                                    {{ day }}
                                                </option></select
                                            ><input
                                                v-model="item.opens"
                                                type="time"
                                                aria-label="Opens"
                                                required /><input
                                                v-model="item.closes"
                                                type="time"
                                                aria-label="Closes"
                                                required /></template
                                        ><input
                                            v-else-if="spec === 'holidays'"
                                            v-model="form[key][index]"
                                            type="date"
                                            aria-label="Holiday"
                                            required
                                        /><template v-else
                                            ><select
                                                v-model="item.id"
                                                aria-label="Assignment"
                                                required
                                            >
                                                <option value="">
                                                    Choose…
                                                </option>
                                                <option
                                                    v-for="choice in (spec ===
                                                    'members'
                                                        ? choices.agents
                                                        : choices.skills) || []"
                                                    :key="choice.id"
                                                    :value="choice.id"
                                                >
                                                    {{ choice.name }}
                                                </option></select
                                            ><label class="compact"
                                                >{{
                                                    spec === "members"
                                                        ? "Priority"
                                                        : "Proficiency (1–5)"
                                                }}<input
                                                    v-if="spec === 'members'"
                                                    v-model.number="
                                                        item.priority
                                                    "
                                                    type="number"
                                                    min="0"
                                                    max="1000"
                                                    required /><input
                                                    v-else-if="
                                                        spec === 'agent-skills'
                                                    "
                                                    v-model.number="
                                                        item.proficiency
                                                    "
                                                    type="number"
                                                    min="1"
                                                    max="5"
                                                    required /><input
                                                    v-else
                                                    v-model.number="
                                                        item.minimum_proficiency
                                                    "
                                                    type="number"
                                                    min="1"
                                                    max="5"
                                                    required /></label></template
                                        ><button
                                            type="button"
                                            @click="form[key].splice(index, 1)"
                                        >
                                            Remove
                                        </button>
                                    </div>
                                    <button
                                        type="button"
                                        @click="addItem(key, spec)"
                                    >
                                        + Add
                                        {{
                                            spec === "schedule"
                                                ? "opening window"
                                                : spec === "holidays"
                                                  ? "holiday"
                                                  : "assignment"
                                        }}
                                    </button>
                                </div>
                                <input
                                    v-else
                                    :id="'field-' + key"
                                    v-model="form[key]"
                                    :type="
                                        relation(spec) === 'number'
                                            ? 'number'
                                            : relation(spec) === 'email'
                                              ? 'email'
                                              : relation(spec) === 'password'
                                                ? 'password'
                                                : 'text'
                                    "
                                    :required="!String(spec).endsWith('?')"
                                    :autocomplete="
                                        relation(spec) === 'password'
                                            ? 'new-password'
                                            : 'off'
                                    "
                                /><small v-if="key === 'password'"
                                    >At least 12 characters with mixed case and
                                    numbers. Leave blank to retain an existing
                                    password.</small
                                >
                            </div>
                        </div>
                        <div v-if="editable" class="form-actions">
                            <button class="primary" :disabled="busy">
                                Save configuration
                            </button>
                            <button
                                v-if="module !== 'settings'"
                                type="button"
                                @click="editor = false"
                            >
                                Cancel
                            </button>
                        </div>
                    </form>
                </section>
            </div>
        </div></AppLayout
    >
</template>
