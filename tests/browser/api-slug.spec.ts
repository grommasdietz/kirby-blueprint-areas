import { expect, test } from "@playwright/test";
import {
  areaFieldInput,
  discardPendingChanges,
  loginToPanel,
  openArea,
  waitForAreaSection,
} from "./utils/panel";

test("Blueprint Areas follows a custom Kirby API slug", async ({ page }) => {
  await loginToPanel(page);

  const endpoint = await page.evaluate(() => {
    return (
      window as unknown as Window & { panel: { api: { endpoint: string } } }
    ).panel.api.endpoint;
  });
  expect(endpoint).toMatch(/\/control\/?$/);

  const fieldsResponse = waitForAreaSection(page, "fields", "textfields");
  await openArea(page, "fields");
  expect((await fieldsResponse).status()).toBe(200);
  await discardPendingChanges(page);
  const input = areaFieldInput(page, "text");
  const original = await input.inputValue();

  const saveResponse = page.waitForResponse((response) => {
    const url = new URL(response.url());
    return (
      response.request().method() === "POST" &&
      url.pathname.endsWith(
        "/control/grommasdietz/blueprint-areas/blueprints/fields/save",
      )
    );
  });
  await input.fill(`Custom slug ${Date.now()}`);
  expect((await saveResponse).status()).toBe(200);

  // Discard to leave the playground unchanged.
  const discardResponse = page.waitForResponse((response) => {
    const url = new URL(response.url());
    return (
      response.request().method() === "POST" &&
      url.pathname.endsWith(
        "/control/grommasdietz/blueprint-areas/blueprints/fields/discard",
      )
    );
  });
  await page
    .locator(".k-form-controls")
    .getByRole("button", { name: "Discard" })
    .click();
  await page
    .getByRole("dialog")
    .getByRole("button", { name: /Discard/i })
    .click();
  expect((await discardResponse).status()).toBe(200);
  await expect(page.locator(".k-form-controls")).toHaveCount(0, {
    timeout: 15_000,
  });
  await expect(input).toHaveValue(original);
});
