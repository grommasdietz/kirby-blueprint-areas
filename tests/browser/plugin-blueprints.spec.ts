import { execFileSync } from "node:child_process";
import { mkdirSync, rmSync, writeFileSync } from "node:fs";
import path from "node:path";
import { expect, test } from "@playwright/test";
import { rawPanelApi } from "./utils/api";
import {
  areaFieldInput,
  loginToPanel,
  openArea,
  PANEL_USERS,
} from "./utils/panel";

const root = process.cwd();
const wrapper = path.join(
  root,
  "playground/site/plugins/zzz-test-plugin-blueprints",
);
const api = "grommasdietz/blueprint-areas/blueprints/plugin-area";
const reader = {
  email: "plugin-reader@kirby-blueprint-areas.test",
  password: "playwright",
};

test.beforeAll(() => {
  mkdirSync(wrapper, { recursive: true });
  writeFileSync(
    path.join(wrapper, "index.php"),
    "<?php require dirname(__DIR__, 4) . " +
      "'/tests/Fixtures/plugins/plugin-blueprints/index.php';\n",
  );

  try {
    execFileSync(
      "php",
      [
        "-r",
        String.raw`
require 'tests/bootstrap.php';
$kirby = GrommasDietz\Areas\Tests\Support\TestEnvironment::boot([
    'testPluginsAfter' => ['plugin-blueprints'],
]);
$kirby->impersonate('kirby');
$kirby->users()->create([
    'email' => 'plugin-reader@kirby-blueprint-areas.test',
    'role' => 'pluginreader',
    'password' => 'playwright',
]);
`,
      ],
      { cwd: root, stdio: "inherit" },
    );
  } catch (error) {
    rmSync(wrapper, { recursive: true, force: true });
    throw error;
  }
});

test.afterAll(() => {
  rmSync(wrapper, { recursive: true, force: true });
});

test("opens and publishes an area supplied by a later-loading plugin", async ({
  page,
}) => {
  await loginToPanel(page);
  const menu = page
    .getByRole("navigation", { name: "Menu" })
    .getByRole("link", { name: "Plugin area", exact: true });
  await expect(menu).toBeVisible({ timeout: 5_000 });
  await menu.click();

  const input = areaFieldInput(page, "plugin_note");
  await expect(input).toBeVisible();
  const original = await input.inputValue();
  const value = `Plugin note ${Date.now()}`;

  try {
    const draft = page.waitForResponse(
      (response) =>
        response.request().method() === "POST" &&
        new URL(response.url()).pathname.endsWith(`/${api}/save`),
    );
    await input.fill(value);
    expect((await draft).status()).toBe(200);

    const publish = page.waitForResponse(
      (response) =>
        response.request().method() === "POST" &&
        new URL(response.url()).pathname.endsWith(`/${api}/publish`),
    );
    await page
      .locator(".k-form-controls")
      .getByRole("button", { name: "Save" })
      .click();
    expect((await publish).status()).toBe(200);
    await expect(page.locator(".k-form-controls")).toHaveCount(0);
    await page.reload({ waitUntil: "domcontentloaded" });
    await expect(areaFieldInput(page, "plugin_note")).toHaveValue(value);
  } finally {
    const restore = await rawPanelApi(page, `${api}/publish`, {
      method: "POST",
      body: { values: { plugin_note: original } },
    });
    expect(restore.status).toBe(200);
  }
});

test("keeps plugin areas read-only when model updates are denied", async ({
  page,
}) => {
  await loginToPanel(page, reader);
  await openArea(page, "plugin-area");
  await expect(areaFieldInput(page, "plugin_note")).toBeDisabled();
  await expect(page.locator(".k-form-controls")).toHaveCount(0);

  const read = await rawPanelApi(page, api);
  expect(read.status).toBe(200);
  const write = await rawPanelApi(page, `${api}/publish`, {
    method: "POST",
    body: { values: { plugin_note: "Forbidden update" } },
  });
  expect(write.status).toBe(403);
});

test("applies existing role denials to plugin areas", async ({ page }) => {
  await loginToPanel(page, PANEL_USERS.editor);
  await expect(
    page
      .getByRole("navigation", { name: "Menu" })
      .getByRole("link", { name: "Plugin area", exact: true }),
  ).toHaveCount(0);
  const denied = await rawPanelApi(page, api);
  expect(denied.status).toBe(403);
});
