import { expect, test } from "@playwright/test";
import { rawPanelApi } from "./utils/api";
import {
  areaFieldInput,
  loginToPanel,
  PANEL_USERS,
  waitForAreaView,
} from "./utils/panel";

test.describe("Blueprint Areas read-only UI", () => {
  test("renders native fields without any mutation controls", async ({
    page,
  }) => {
    await loginToPanel(page, PANEL_USERS.readonly);

    const pagesSectionResponse = waitForAreaView(page, "fields");
    await page.goto("/panel/fields?tab=sections");
    const sectionResponse = await pagesSectionResponse;
    expect(sectionResponse.status()).toBe(200);

    const view = page.locator('.k-areas-view[data-readonly="true"]');
    await expect(view).toBeVisible();
    await expect(page.locator(".k-form-controls")).toHaveCount(0);

    const pagesSection = page.locator(".k-field-name-sitepages").first();
    await expect(pagesSection).toBeVisible();
    const nativeState = await rawPanelApi(
      page,
      "grommasdietz/blueprint-areas/blueprints/fields/fields/sitepages",
    );
    expect(nativeState.status).toBe(200);
    expect(nativeState.json).toMatchObject({
      add: false,
      sortable: false,
      models: expect.arrayContaining([
        expect.objectContaining({ id: "home", permissions: expect.objectContaining({ changeTitle: false }) }),
      ]),
      pagination: expect.objectContaining({ total: expect.any(Number) }),
    });
    await expect(pagesSection.getByRole("link", { name: "Home", exact: true })).toBeVisible();
    await expect(pagesSection.getByRole("button", { name: /Add/i })).toHaveCount(
      0,
    );

    const fieldsResponse = waitForAreaView(page, "fields");
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
