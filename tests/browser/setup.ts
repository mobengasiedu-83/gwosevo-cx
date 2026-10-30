import type { FullConfig } from "@playwright/test";
import { execFileSync } from "node:child_process";
export default function setup(config: FullConfig) {
    const env = config.webServer?.env;
    if (!env) throw new Error("Missing isolated browser environment");
    execFileSync("php", ["tests/browser/prepare.php"], {
        env,
        stdio: "inherit",
    });
}
