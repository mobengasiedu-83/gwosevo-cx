import { test, expect } from "@playwright/test";
import type { Page } from "@playwright/test";
import { createHmac } from "node:crypto";
/**
 * The directory is an accordion: the group that owns the current page is
 * expanded and every other group collapses, so a group is opened explicitly
 * before its entries are clicked.
 */
async function openMenu(page: Page, title: string) {
    const toggle = page.getByRole("button", { name: title, exact: true });
    if ((await toggle.getAttribute("aria-expanded")) !== "true") {
        await toggle.click();
    }
    await expect(toggle).toHaveAttribute("aria-expanded", "true");
}
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
    // Only the menu that owns the current page is expanded; opening another
    // group collapses it, which keeps the directory scannable.
    const adminMenu = page.getByRole("button", {
        name: "Administration & Configuration",
        exact: true,
    });
    const callCenterMenu = page.getByRole("button", {
        name: "Call Center Management",
        exact: true,
    });
    await expect(adminMenu).toHaveAttribute("aria-expanded", "true");
    await expect(callCenterMenu).toHaveAttribute("aria-expanded", "false");
    await openMenu(page, "Call Center Management");
    await expect(adminMenu).toHaveAttribute("aria-expanded", "false");
    await page
        .getByRole("button", { name: "Collapse menu", exact: true })
        .click();
    await expect(callCenterMenu).toHaveAttribute("aria-expanded", "false");
    await page
        .getByRole("button", { name: "Current menu", exact: true })
        .click();
    await expect(adminMenu).toHaveAttribute("aria-expanded", "true");
    await openMenu(page, "Administration & Configuration");
    await page.getByRole("button", { name: "+ Create record" }).click();
    await page.getByLabel("Name", { exact: true }).fill("Accra HQ");
    await page.getByRole("button", { name: "Save configuration" }).click();
    await expect(
        page.getByRole("cell", { name: "Accra HQ", exact: true }),
    ).toBeVisible();
    // The dedicated extension editor covers tabs, segmented controls and both
    // repeaters in one pass: endpoint, voicemail, recording, follow me routing.
    await openMenu(page, "Extensions & Devices");
    await page
        .getByRole("button", { name: "Extension Management", exact: true })
        .click();
    await expect(page).toHaveURL(/module=extensions/);
    const extensionsHeading = page.getByRole("heading", {
        name: "Extensions",
        level: 1,
    });
    await expect(extensionsHeading).toBeVisible();
    await page.getByRole("button", { name: "+ New extension" }).click();
    await page
        .getByRole("textbox", { name: "Extension", exact: true })
        .fill("1001");
    await page
        .getByRole("textbox", { name: "Name", exact: true })
        .fill("Front Desk");
    await page
        .getByRole("combobox", { name: "Site", exact: true })
        .selectOption({ label: "Accra HQ" });
    await page.getByRole("tab", { name: "Voicemail" }).click();
    await page
        .getByRole("group", { name: "Voicemail", exact: true })
        .getByRole("button", { name: "Enabled", exact: true })
        .click();
    await page
        .getByRole("textbox", { name: "Mailbox", exact: true })
        .fill("1001");
    await page
        .getByRole("textbox", { name: "Voicemail Password", exact: true })
        .fill("7788");
    await page
        .getByRole("checkbox", { name: "Attach the recording to the email" })
        .check();
    await page.getByRole("tab", { name: "Recording" }).click();
    await page
        .getByRole("combobox", { name: "Call Recording", exact: true })
        .selectOption("always");
    await page.getByRole("tab", { name: "Follow Me" }).click();
    await page
        .getByRole("group", { name: "Follow Me", exact: true })
        .getByRole("button", { name: "Enabled", exact: true })
        .click();
    await page.getByRole("button", { name: "+ Add number" }).click();
    await page
        .getByRole("textbox", { name: "Follow me number 1", exact: true })
        .fill("5551234");
    await page.getByRole("tab", { name: "Incoming Routes" }).click();
    await page.getByRole("button", { name: "+ Add DID" }).click();
    await page
        .getByRole("textbox", { name: "DID 1", exact: true })
        .fill("+15551234567");
    await page.getByRole("button", { name: "Create extension" }).click();
    await expect(
        page.getByRole("cell", { name: "Front Desk", exact: true }),
    ).toBeVisible();
    await page.getByRole("button", { name: "Edit extension 1001" }).click();
    await expect(
        page.getByRole("heading", { name: "Extension 1001", level: 1 }),
    ).toBeVisible();
    await page.getByRole("tab", { name: "Follow Me" }).click();
    await expect(
        page.getByRole("textbox", { name: "Follow me number 1", exact: true }),
    ).toHaveValue("5551234");
    await page.getByRole("button", { name: "Cancel", exact: true }).click();
    await expect(extensionsHeading).toBeVisible();
    // Validation stays server side: a duplicate number must surface on the
    // editor instead of silently rolling back to the list.
    await page.getByRole("button", { name: "+ New extension" }).click();
    await page
        .getByRole("textbox", { name: "Extension", exact: true })
        .fill("1001");
    await page
        .getByRole("textbox", { name: "Name", exact: true })
        .fill("Duplicate Desk");
    await page
        .getByRole("combobox", { name: "Site", exact: true })
        .selectOption({ label: "Accra HQ" });
    await page.getByRole("button", { name: "Create extension" }).click();
    await expect(
        page.getByText("The number has already been taken.", { exact: true }),
    ).toBeVisible();
    await expect(
        page.getByText("The user device has already been taken.", {
            exact: true,
        }),
    ).toBeVisible();
    await page.getByRole("button", { name: "Cancel", exact: true }).click();
    await expect(extensionsHeading).toBeVisible();
    const search = page.getByPlaceholder("Search menus and records…");
    await search.fill("Accra");
    await expect(
        page.locator(".search-record", { hasText: "Accra HQ" }),
    ).toBeVisible();
    await search.fill("");
    await openMenu(page, "Call Center Management");
    await page
        .getByRole("button", { name: "Skills-Based Routing", exact: true })
        .click();
    await page.getByRole("button", { name: "+ Create record" }).click();
    await page.getByLabel("Name", { exact: true }).fill("English");
    await page.getByRole("button", { name: "Save configuration" }).click();
    await expect(
        page.getByRole("cell", { name: "English", exact: true }),
    ).toBeVisible();
    await openMenu(page, "Administration & Configuration");
    await page
        .getByRole("button", { name: "Business Hours", exact: true })
        .click();
    await page.getByRole("button", { name: "+ Create record" }).click();
    await page.getByLabel("Name", { exact: true }).fill("Weekday Hours");
    await page.getByRole("button", { name: "Save configuration" }).click();
    await expect(
        page.getByRole("cell", { name: "Weekday Hours", exact: true }),
    ).toBeVisible();
    await openMenu(page, "Call Center Management");
    await page
        .getByRole("button", { name: "Queue Strategies", exact: true })
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
    await page
        .getByRole("button", { name: "Agent Management", exact: true })
        .click();
    await page.getByRole("button", { name: "+ Create record" }).click();
    await page.getByLabel("Name", { exact: true }).fill("Agent One");
    await page.getByLabel("Site Id").selectOption({ label: "Accra HQ" });
    await page
        .getByLabel("User Id")
        .selectOption({ label: "Browser Administrator" });
    await page.getByLabel("Extension Id").selectOption({ label: "Front Desk" });
    await page.getByRole("button", { name: "+ Add assignment" }).click();
    await page
        .getByLabel("Assignment", { exact: true })
        .selectOption({ label: "English" });
    await page.getByRole("button", { name: "Save configuration" }).click();
    await expect(
        page.getByRole("cell", { name: "Agent One", exact: true }),
    ).toBeVisible();
    await page
        .getByRole("button", { name: "Call Queues", exact: true })
        .click();
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
    await openMenu(page, "Administration & Configuration");
    await page
        .getByRole("button", { name: "Organisation Profile", exact: true })
        .click();
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

test("agent workspace is separate and menu search opens explicit feature status", async ({
    page,
}) => {
    await page.goto("/login");
    await page.getByLabel("Email address").fill("agent@example.test");
    await page
        .getByLabel("Password", { exact: true })
        .fill("BrowserTest12345!");
    await page.getByRole("button", { name: "Sign in", exact: true }).click();
    await expect(page).toHaveURL(/\/agent$/);
    await expect(
        page.getByRole("heading", { name: "Agent workspace", exact: true }),
    ).toBeVisible();
    await page.getByLabel("Find a feature").fill("Dialpad");
    await page
        .getByRole("link", { name: "Dialpad Planned", exact: true })
        .click();
    await expect(
        page.getByRole("heading", { name: "Dialpad", exact: true }),
    ).toBeVisible();
    await expect(
        page.getByRole("heading", { name: "Implementation is pending" }),
    ).toBeVisible();
    const response = await page.request.get("/admin");
    expect(response.status()).toBe(403);
    await page.screenshot({
        path: "storage/testing/agent-navigation.png",
        fullPage: true,
    });
});
