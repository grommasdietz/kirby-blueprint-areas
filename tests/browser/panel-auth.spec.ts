import { expect, test, type Request } from "@playwright/test";
import { loginToPanel } from "./utils/panel";

test.describe("Panel authentication", () => {
  test("admin can log into the Panel", async ({ page }) => {
    await loginToPanel(page);

    await expect(page).toHaveURL(/\/panel(?:\/.*)?$/);
    await expect(page.getByRole("heading", { level: 1 }).first()).toBeVisible();
  });

  test("loads change badges only after authentication", async ({ page }) => {
    const changesRequests: Request[] = [];
    const isChangesRequest = (request: Request) =>
      new URL(request.url()).pathname.endsWith(
        "/grommasdietz/blueprint-areas/changes",
      );
    page.on("request", (request) => {
      if (isChangesRequest(request)) changesRequests.push(request);
    });

    await page.goto("/panel/login");
    await expect(page.getByLabel("Email")).toBeVisible();
    await page.waitForLoadState("networkidle");
    expect(changesRequests).toHaveLength(0);

    const badges = page.waitForResponse((response) =>
      isChangesRequest(response.request()),
    );
    await loginToPanel(page);
    expect((await badges).status()).toBe(200);
    expect(changesRequests.length).toBeGreaterThan(0);

    await page.evaluate(async () => {
      const panel = (window as unknown as {
        panel: { api: { post: (path: string) => Promise<unknown> } };
      }).panel;
      await panel.api.post("auth/logout");
    });
    changesRequests.length = 0;
    await page.goto("/panel/login");
    await expect(page.getByLabel("Email")).toBeVisible();
    await page.waitForLoadState("networkidle");
    expect(changesRequests).toHaveLength(0);
  });
});
