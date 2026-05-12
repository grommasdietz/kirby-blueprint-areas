import { expect, test } from "@playwright/test";
import { URLS } from "./utils/constants";
import { AppLocators } from "./utils/locators";

test.describe("Frontend layout", () => {
  test("site title is visible in the header", async ({ page }) => {
    const app = new AppLocators(page);

    await page.goto(URLS.HOME);

    await expect(app.siteTitle).toHaveText("Kirby Playground");
    await expect(page).toHaveTitle("Kirby Playground");
  });

  test("page text blocks render Kirbytext markup", async ({ page }) => {
    const app = new AppLocators(page);

    await page.goto(URLS.HOME);

    // Kirbytext wraps paragraphs in <p> tags; ensure markup is rendered
    const paragraphs = app.pageText.locator("p");
    await expect(paragraphs).toHaveCount(1);
    await expect(paragraphs.first()).toContainText("Playground");
  });
});
