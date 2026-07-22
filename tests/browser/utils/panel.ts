import { expect, type Page } from "@playwright/test";
import { URLS } from "./constants";

export const PANEL_USERS = {
  admin: {
    email:
      process.env.KIRBY_USER_EMAIL ??
      "admin@kirby-blueprint-areas.test",
    password: process.env.KIRBY_USER_PASSWORD ?? "playwright",
  },
  editor: {
    email: "editor@kirby-blueprint-areas.test",
    password: "playwright",
  },
  readonly: {
    email: "readonly@kirby-blueprint-areas.test",
    password: "playwright",
  },
} as const;

export async function loginToPanel(
  page: Page,
  user: { email: string; password: string } = PANEL_USERS.admin,
) {
  await page.goto(URLS.PANEL_LOGIN);
  await page.getByLabel("Email").fill(user.email);
  await page.getByLabel("Password").fill(user.password);

  await Promise.all([
    page.waitForURL(/\/panel(?:\/(?!login).*)?$/, { timeout: 30_000 }),
    page.getByRole("button", { name: "Log in" }).click(),
  ]);

  await expect(page).not.toHaveURL(/\/panel\/login/);
  await page.waitForLoadState("networkidle");
}

export async function openArea(page: Page, name: string) {
  await page.goto(`${URLS.PANEL}/${name}`);
  await expect(page.locator(".k-areas-view")).toBeVisible({ timeout: 15_000 });
}

export function waitForAreaSection(
  page: Page,
  area: string,
  section: string,
) {
  return page.waitForResponse((response) => {
    const url = new URL(response.url());
    return (
      response.request().method() === "GET" &&
      url.pathname.endsWith(
        `/grommasdietz/blueprint-areas/blueprints/${area}/sections/${section}`,
      )
    );
  });
}

export function areaFieldInput(page: Page, name: string) {
  return page.locator(`.k-field-name-${name} input`).first();
}

export async function discardPendingChanges(page: Page) {
  const discard = page
    .locator(".k-form-controls")
    .getByRole("button", { name: "Discard" });

  if ((await discard.count()) === 0 || !(await discard.first().isVisible())) {
    return false;
  }

  const discardResponse = page.waitForResponse((response) => {
    const url = new URL(response.url());
    return (
      response.request().method() === "POST" &&
      url.pathname.endsWith("/discard")
    );
  });

  await discard.first().click();
  const dialog = page.getByRole("dialog");
  await expect(dialog).toBeVisible();
  await dialog.getByRole("button", { name: /Discard/i }).click();

  const response = await discardResponse;
  expect(response.status()).toBe(200);
  await expect(dialog).toBeHidden();
  await expect(page.locator(".k-form-controls")).toHaveCount(0, {
    timeout: 15_000,
  });

  return true;
}
