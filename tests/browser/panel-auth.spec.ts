import { expect, test } from "@playwright/test";
import { loginToPanel } from "./utils/panel";

test.describe("Panel authentication", () => {
  test("admin can log into the Panel", async ({ page }) => {
    await loginToPanel(page);

    await expect(page).toHaveURL(/\/panel(?:\/.*)?$/);
    await expect(page.getByRole("heading", { level: 1 }).first()).toBeVisible();
  });
});
