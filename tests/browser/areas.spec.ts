import { expect, test } from "@playwright/test";
import { URLS } from "./utils/constants";
import { areaFieldInput, loginToPanel, PANEL_USERS } from "./utils/panel";

test.describe("Blueprint Areas", () => {
  test("admin sees and opens the Fields area", async ({ page }) => {
    await loginToPanel(page);

    const menu = page.getByRole("navigation", { name: "Menu" });
    await expect(menu).toBeVisible({ timeout: 10_000 });

    const fieldsLink = menu.getByRole("link", { name: "Fields" });
    await expect(fieldsLink).toBeVisible();
    await fieldsLink.click();

    await expect(page).toHaveURL(/\/panel\/fields(?:\?.*)?$/);
    await expect(page.locator(".k-areas-view")).toBeVisible();
    await expect(page.getByRole("heading", { name: "Fields" })).toBeVisible();
  });

  test("translation area exposes content-language values", async ({ page }) => {
    const pageErrors: string[] = [];
    page.on("pageerror", (error) => pageErrors.push(error.message));

    await loginToPanel(page);

    await page.goto(`${URLS.PANEL}/translations?language=en`);
    await expect(page.locator(".k-areas-view")).toBeVisible({
      timeout: 15_000,
    });
    await expect(
      page.getByText("Switch the content language in the header.", {
        exact: false,
      }),
    ).toBeVisible();
    await expect(areaFieldInput(page, "translatedcontent")).toHaveValue(
      "This value is stored in English.",
    );

    await page.goto(`${URLS.PANEL}/translations?language=de`);
    await expect(page.locator(".k-areas-view")).toBeVisible({
      timeout: 15_000,
    });
    await expect(areaFieldInput(page, "translatedcontent")).toHaveValue(
      "Dieser Wert ist auf Deutsch gespeichert.",
    );

    expect(pageErrors).toEqual([]);
  });
});

test.describe("Role-based access", () => {
  test("editor sees only permitted Blueprint Areas", async ({ page }) => {
    await loginToPanel(page, PANEL_USERS.editor);

    const menu = page.getByRole("navigation", { name: "Menu" });
    await expect(menu.getByRole("link", { name: "Fields" })).toBeVisible();
    await expect(menu.getByRole("link", { name: "Buttons" })).toHaveCount(0);
    await expect(menu.getByRole("link", { name: "Home" })).toHaveCount(0);
  });

  test("editor is denied a restricted area via direct URL", async ({ page }) => {
    await loginToPanel(page, PANEL_USERS.editor);

    const response = await page.goto(`${URLS.PANEL}/buttons`);
    await page.waitForLoadState("networkidle");

    const redirected = !page.url().includes("/buttons");
    const deniedResponse =
      response !== null && [401, 403, 404].includes(response.status());
    const deniedMessage = page
      .locator("body")
      .getByText(/not allowed|access denied|forbidden/i)
      .first();

    expect(
      redirected ||
        deniedResponse ||
        (await deniedMessage.isVisible().catch(() => false)),
    ).toBe(true);
  });
});
