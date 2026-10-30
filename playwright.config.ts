import { defineConfig } from "@playwright/test";
import { mkdirSync, closeSync, openSync } from "node:fs";
import { resolve } from "node:path";
mkdirSync("storage/testing", { recursive: true });
closeSync(openSync("storage/testing/browser.sqlite", "a"));
const env: Record<string, string> = {
    ...Object.fromEntries(
        Object.entries(process.env).filter(
            (e): e is [string, string] => typeof e[1] === "string",
        ),
    ),
    APP_ENV: "testing",
    MFA_ENABLED: "true",
    APP_URL: "http://127.0.0.1:8124",
    DB_CONNECTION: "sqlite",
    DB_DATABASE: resolve("storage/testing/browser.sqlite"),
    CACHE_STORE: "array",
    SESSION_DRIVER: "file",
    QUEUE_CONNECTION: "sync",
    SESSION_COOKIE: "gwosevo_browser_test_session",
    SESSION_SECURE_COOKIE: "false",
};

export default defineConfig({
    globalSetup: "./tests/browser/setup.ts",
    timeout: 120000,
    expect: { timeout: 15000 },
    testDir: "tests/browser",
    testMatch: "*.spec.ts",
    workers: 1,
    use: {
        baseURL: "http://127.0.0.1:8124",
        browserName: "chromium",
        headless: true,
    },
    webServer: {
        command: "php artisan serve --host=127.0.0.1 --port=8124",
        url: "http://127.0.0.1:8124/up",
        reuseExistingServer: false,
        env,
        timeout: 30000,
    },
});
