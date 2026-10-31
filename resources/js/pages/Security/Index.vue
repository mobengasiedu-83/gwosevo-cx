<script setup lang="ts">
import { ref } from "vue";
import { Head, Link, router, usePage } from "@inertiajs/vue3";
import AppLayout from "../../components/AppLayout.vue";
import Icon from "../../components/Icon.vue";
import FieldLabel from "../../components/FieldLabel.vue";
import PageHeader from "../../components/PageHeader.vue";
import { useApi } from "../../composables/api";
import type { SharedProps } from "../../types";
const page = usePage<SharedProps>(),
    { request, url, message } = useApi();
const workspace = page.props.home || "login";
const workspaceLabel = workspace.endsWith("/supervisor")
    ? "Continue to supervisor workspace"
    : workspace.endsWith("/agent")
      ? "Continue to agent workspace"
      : "Continue to administration";
const password = ref(""),
    code = ref(""),
    qr = ref(""),
    secret = ref(""),
    codes = ref<string[]>([]),
    status = ref(""),
    busy = ref(false),
    confirmed = ref(false);

const checklist = [
    "Use an authenticator app rather than SMS codes.",
    "Store recovery codes somewhere offline and private.",
    "Re-confirm your password before changing security settings.",
];
async function prepare() {
    busy.value = true;
    status.value = "";
    try {
        await request("user/confirm-password", "POST", {
            password: password.value,
        });
        password.value = "";
        confirmed.value = true;
        if (!page.props.auth?.mfaEnabled) {
            await request("user/two-factor-authentication", "POST");
            // Resolve both in one tick so the setup key and QR code appear
            // together; a half-rendered panel would expose an empty key.
            const [image, key] = await Promise.all([
                request("user/two-factor-qr-code"),
                request("user/two-factor-secret-key"),
            ]);
            secret.value = key.secretKey;
            qr.value = image.svg;
        } else codes.value = await request("user/two-factor-recovery-codes");
    } catch (e) {
        status.value = message(e);
    } finally {
        busy.value = false;
    }
}
async function verify() {
    busy.value = true;
    try {
        await request("user/confirmed-two-factor-authentication", "POST", {
            code: code.value,
        });
        codes.value = await request("user/two-factor-recovery-codes");
        qr.value = "";
        secret.value = "";
        code.value = "";
        router.reload();
        status.value =
            "Two-factor authentication is enabled. Save your recovery codes securely.";
    } catch (e) {
        status.value = message(e);
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <Head title="Account security" />
    <AppLayout>
        <div class="page-shell">
            <PageHeader
                eyebrow="ACCOUNT SECURITY"
                title="Protect your account"
                :description="
                    page.props.mfaAvailable
                        ? 'Administrators must configure two-factor authentication before accessing the portal.'
                        : undefined
                "
            >
                <template #actions>
                    <span
                        class="security-state"
                        :class="{ pending: !page.props.auth?.mfaEnabled }"
                    >
                        <Icon name="shield" :size="13" />
                        {{
                            page.props.auth?.mfaEnabled
                                ? "MFA enabled"
                                : "MFA not configured"
                        }}
                    </span>
                </template>
            </PageHeader>
            <p v-if="status" class="notice" role="status">{{ status }}</p>
            <div class="security-layout">
                <section
                    v-if="!page.props.mfaAvailable"
                    class="panel security-card"
                >
                    <h2>Two-factor authentication is turned off</h2>
                    <p>
                        MFA is currently disabled for this development
                        environment.
                    </p>
                    <Link class="button primary" :href="url(workspace)"
                        >{{ workspaceLabel }}</Link
                    >
                </section>
                <section v-else class="panel security-card">
                    <h2>Authenticator app</h2>
                    <p>
                        {{
                            page.props.auth?.mfaEnabled
                                ? "Two-factor authentication is enabled."
                                : "Use an authenticator app to add a second sign-in step."
                        }}
                    </p>
                    <form v-if="!confirmed" @submit.prevent="prepare">
                        <div class="field">
                            <FieldLabel
                                for="security-password"
                                label="Confirm your password"
                                required
                            />
                            <input
                                id="security-password"
                                v-model="password"
                                type="password"
                                autocomplete="current-password"
                                required
                            />
                            <small
                                >We only ask again before security settings
                                change.</small
                            >
                        </div>
                        <div class="form-actions">
                            <button class="primary" :disabled="busy">
                                {{
                                    page.props.auth?.mfaEnabled
                                        ? "View recovery codes"
                                        : "Set up authenticator"
                                }}
                            </button>
                        </div>
                    </form>
                    <div v-if="qr">
                        <div class="qr" v-html="qr"></div>
                        <p v-if="secret">
                            Or enter this setup key: <code>{{ secret }}</code>
                        </p>
                        <form @submit.prevent="verify">
                            <div class="field">
                                <FieldLabel
                                    for="security-code"
                                    label="Six-digit code"
                                    required
                                />
                                <input
                                    id="security-code"
                                    v-model="code"
                                    inputmode="numeric"
                                    autocomplete="one-time-code"
                                    pattern="[0-9]{6}"
                                    class="code-input"
                                    required
                                />
                                <small
                                    >Enter the current code from your
                                    authenticator app.</small
                                >
                            </div>
                            <div class="form-actions">
                                <button class="primary" :disabled="busy">
                                    Verify and enable
                                </button>
                            </div>
                        </form>
                    </div>
                    <div v-if="codes.length">
                        <h3>Recovery codes</h3>
                        <p class="muted">
                            Save these somewhere secure. Each code works once.
                        </p>
                        <pre>{{ codes.join("\n") }}</pre>
                        <Link class="button primary" :href="url(workspace)"
                            >{{ workspaceLabel }}</Link
                        >
                    </div>
                </section>
                <aside class="security-aside">
                    <h2>Security checklist</h2>
                    <p>
                        Stronger sign-in protects every queue, agent and
                        conversation in your tenant.
                    </p>
                    <ul class="security-list">
                        <li v-for="item in checklist" :key="item">
                            <Icon name="check" :size="14" />
                            <span>{{ item }}</span>
                        </li>
                    </ul>
                </aside>
            </div>
        </div>
    </AppLayout>
</template>
