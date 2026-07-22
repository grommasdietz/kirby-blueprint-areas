import { expect, test } from "@playwright/test";
import {
  areaFieldInput,
  loginToPanel,
  PANEL_USERS,
  waitForAreaSection,
} from "./utils/panel";

test.describe("Blueprint Areas read-only UI", () => {
  test("renders fields and sections without any mutation controls", async ({
    page,
  }) => {
    await loginToPanel(page, PANEL_USERS.readonly);

    const pagesSectionResponse = page.waitForResponse((response) => {
      const url = new URL(response.url());
      return (
        response.request().method() === "GET" &&
        url.pathname.endsWith(
          "/grommasdietz/blueprint-areas/blueprints/fields/sections/pages",
        )
      );
    });
    await page.goto("/panel/fields?tab=sections");
    const sectionResponse = await pagesSectionResponse;
    expect(sectionResponse.status()).toBe(200);

    const view = page.locator('.k-areas-view[data-readonly="true"]');
    await expect(view).toBeVisible();
    await expect(page.locator(".k-form-controls")).toHaveCount(0);

    const pagesSection = page.locator(".k-pages-section").first();
    await expect(pagesSection).toBeVisible();
    await expect(pagesSection.getByRole("button", { name: /Add/i })).toHaveCount(
      0,
    );

    const fieldsResponse = waitForAreaSection(page, "fields", "textfields");
    await page.goto("/panel/fields?tab=basics");
    expect((await fieldsResponse).status()).toBe(200);
    const text = areaFieldInput(page, "text");
    await expect(text).toBeDisabled({ timeout: 15_000 });

    let mutations = 0;
    page.on("request", (request) => {
      if (
        request.method() === "POST" &&
        /\/grommasdietz\/blueprint-areas\/.*\/(save|publish|discard)$/.test(
          new URL(request.url()).pathname,
        )
      ) {
        mutations += 1;
      }
    });

    const saveShortcut = process.platform === "darwin" ? "Meta+S" : "Control+S";
    await page.keyboard.press(saveShortcut);
    await page.waitForTimeout(750);
    expect(mutations).toBe(0);
  });
});
