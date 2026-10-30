import { test, expect } from "@playwright/test";
import { createHmac } from "node:crypto";
function otp(base32: string, offsetStep = 0) {
    const alphabet = "ABCDEFGHIJKLMNOPQRSTUVWXYZ234567";
    let bits = "";
    for (const c of base32.toUpperCase().replace(/=+$/, ""))
        bits += alphabet.indexOf(c).toString(2).padStart(5, "0");
    const bytes = [];
    for (let i = 0; i + 8 <= bits.length; i += 8)
        bytes.push(parseInt(bits.slice(i, i + 8), 2));
    const counter = Buffer.alloc(8);
    counter.writeBigUInt64BE(
        BigInt(Math.floor(Date.now() / 30000) + offsetStep),
    );
    const hash = createHmac("sha1", Buffer.from(bytes))
            .update(counter)
            .digest(),
        offset = hash[19] & 15;
    return ((hash.readUInt32BE(offset) & 0x7fffffff) % 1000000)
        .toString()
        .padStart(6, "0");
}
test("administrator enrolls MFA, configures a site and tenant settings, then signs in with MFA", async ({
    page,
}) => {
    await page.goto("/login");
    await page.getByLabel("Email address").fill("browser@example.test");
    await page
        .getByLabel("Password", { exact: true })
        .fill("BrowserTest12345!");
    await page.getByRole("button", { name: "Sign in", exact: true }).click();
    await expect(page).toHaveURL(/\/security$/);
    await page.getByLabel("Confirm your password").fill("BrowserTest12345!");
    await page.getByRole("button", { name: "Set up authenticator" }).click();
    await expect(page.locator("code")).toBeVisible();
    const secret = (await page.locator("code").textContent())!;
    await page.getByLabel("Six-digit code").fill(otp(secret));
    await page.getByRole("button", { name: "Verify and enable" }).click();
    await page
        .getByRole("link", { name: "Continue to administration" })
        .click();
    await expect(
        page.getByRole("heading", { name: "Sites", exact: true }),
    ).toBeVisible();
    await page.getByRole("button", { name: "+ Create record" }).click();
    await page.getByLabel("Name", { exact: true }).fill("Accra HQ");
    await page.getByRole("button", { name: "Save configuration" }).click();
    await expect(
        page.getByRole("cell", { name: "Accra HQ", exact: true }),
    ).toBeVisible();
    await page.getByRole("button", { name: "Skills", exact: true }).click();
    await page.getByRole("button", { name: "+ Create record" }).click();
    await page.getByLabel("Name", { exact: true }).fill("English");
    await page.getByRole("button", { name: "Save configuration" }).click();
    await expect(
        page.getByRole("cell", { name: "English", exact: true }),
    ).toBeVisible();
    await page
        .getByRole("button", { name: "Business Hours", exact: true })
        .click();
    await page.getByRole("button", { name: "+ Create record" }).click();
    await page.getByLabel("Name", { exact: true }).fill("Weekday Hours");
    await page.getByRole("button", { name: "Save configuration" }).click();
    await expect(
        page.getByRole("cell", { name: "Weekday Hours", exact: true }),
    ).toBeVisible();
    await page
        .getByRole("button", { name: "Routing Profiles", exact: true })
        .click();
    await page.getByRole("button", { name: "+ Create record" }).click();
    await page.getByLabel("Name", { exact: true }).fill("Standard Routing");
    await page
        .getByLabel("Business Hours Id")
        .selectOption({ label: "Weekday Hours" });
    await page.getByRole("button", { name: "Save configuration" }).click();
    await expect(
        page.getByRole("cell", { name: "Standard Routing", exact: true }),
    ).toBeVisible();
    await page.getByRole("button", { name: "Extensions", exact: true }).click();
    await page.getByRole("button", { name: "+ Create record" }).click();
    await page.getByLabel("Name", { exact: true }).fill("Desk One");
    await page.getByLabel("Site Id").selectOption({ label: "Accra HQ" });
    await page.getByLabel("Number", { exact: true }).fill("1001");
    await page.getByRole("button", { name: "Save configuration" }).click();
    await expect(
        page.getByRole("cell", { name: "Desk One", exact: true }),
    ).toBeVisible();
    await page.getByRole("button", { name: "Agents", exact: true }).click();
    await page.getByRole("button", { name: "+ Create record" }).click();
    await page.getByLabel("Name", { exact: true }).fill("Agent One");
    await page.getByLabel("Site Id").selectOption({ label: "Accra HQ" });
    await page
        .getByLabel("User Id")
        .selectOption({ label: "Browser Administrator" });
    await page.getByLabel("Extension Id").selectOption({ label: "Desk One" });
    await page.getByRole("button", { name: "+ Add assignment" }).click();
    await page
        .getByLabel("Assignment", { exact: true })
        .selectOption({ label: "English" });
    await page.getByRole("button", { name: "Save configuration" }).click();
    await expect(
        page.getByRole("cell", { name: "Agent One", exact: true }),
    ).toBeVisible();
    await page.getByRole("button", { name: "Queues", exact: true }).click();
    await page.getByRole("button", { name: "+ Create record" }).click();
    await page.getByLabel("Name", { exact: true }).fill("Support Queue");
    await page.getByLabel("Site Id").selectOption({ label: "Accra HQ" });
    await page
        .getByLabel("Routing Profile Id")
        .selectOption({ label: "Standard Routing" });
    await page
        .getByRole("button", { name: "+ Add assignment" })
        .first()
        .click();
    await page
        .getByLabel("Assignment", { exact: true })
        .selectOption({ label: "Agent One" });
    await page.getByRole("button", { name: "Save configuration" }).click();
    await expect(
        page.getByRole("cell", { name: "Support Queue", exact: true }),
    ).toBeVisible();
    await page.screenshot({
        path: "storage/testing/admin-desktop.png",
        fullPage: true,
    });
    await page.getByRole("button", { name: "Settings", exact: true }).click();
    await page.getByLabel("Display Name").fill("GWO SEVO Test");
    await page.getByRole("button", { name: "Save configuration" }).click();
    await expect(page.getByRole("status")).toContainText("Configuration saved");
    await page.getByRole("button", { name: "Sign out" }).click();
    await page.goto("/login");
    await page.getByLabel("Email address").fill("browser@example.test");
    await page
        .getByLabel("Password", { exact: true })
        .fill("BrowserTest12345!");
    await page.getByRole("button", { name: "Sign in", exact: true }).click();
    await expect(page).toHaveURL(/two-factor-challenge/);
    await page.getByLabel("Authentication code").fill(otp(secret, 1));
    await page.getByRole("button", { name: "Verify", exact: true }).click();
    await expect(page).toHaveURL(/\/admin$/);
    await expect(
        page.getByRole("cell", { name: "Accra HQ", exact: true }),
    ).toBeVisible();
    await page.setViewportSize({ width: 390, height: 844 });
    await expect(
        page.getByRole("heading", { name: "Sites", exact: true }),
    ).toBeVisible();
    await page.screenshot({
        path: "storage/testing/admin-mobile.png",
        fullPage: true,
    });
});
