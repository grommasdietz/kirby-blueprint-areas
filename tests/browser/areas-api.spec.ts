import { expect, test } from "@playwright/test";
import { rawPanelApi } from "./utils/api";
import { loginToPanel, PANEL_USERS } from "./utils/panel";

const ROOT = "grommasdietz/blueprint-areas";

test.describe("Blueprint Areas browser API authorization", () => {
  test("rejects completely unauthenticated HTTP requests", async ({
    request,
  }) => {
    const response = await request.get(
      `/api/${ROOT}/blueprints`,
      { failOnStatusCode: false },
    );

    expect(response.status()).toBe(401);
  });

  test("returns concrete authorization and routing statuses", async ({ page }) => {
    await loginToPanel(page, PANEL_USERS.editor);

    expect((await rawPanelApi(page, `${ROOT}/blueprints/fields`)).status).toBe(
      200,
    );
    expect((await rawPanelApi(page, `${ROOT}/blueprints/buttons`)).status).toBe(
      403,
    );
    expect(
      (
        await rawPanelApi(
          page,
          `${ROOT}/blueprints/buttons/fields/note/probe`,
        )
      ).status,
    ).toBe(403);
    expect(
      (
        await rawPanelApi(
          page,
          `${ROOT}/blueprints/buttons/sections/missing`,
        )
      ).status,
    ).toBe(403);

    const changes = await rawPanelApi(page, `${ROOT}/changes`);
    expect(JSON.stringify(changes.json)).not.toContain('"buttons"');
    expect((await rawPanelApi(page, `${ROOT}/blueprints/missing`)).status).toBe(
      404,
    );
    expect(
      (
        await rawPanelApi(page, `${ROOT}/blueprints/fields`, {
          method: "PUT",
          body: { values: { text: "Denied" } },
        })
      ).status,
    ).toBe(405);
  });

  test("read-only role can read but cannot mutate", async ({ page }) => {
    await loginToPanel(page, PANEL_USERS.readonly);

    expect((await rawPanelApi(page, `${ROOT}/blueprints/fields`)).status).toBe(
      200,
    );
    expect(
      (await rawPanelApi(page, `${ROOT}/blueprints/fields/sections/pages`))
        .status,
    ).toBe(200);
    expect(
      (
        await rawPanelApi(page, `${ROOT}/blueprints/fields/save`, {
          method: "POST",
          body: { values: { text: "Denied" } },
        })
      ).status,
    ).toBe(403);
  });

  test("malformed live payloads return 400", async ({ page }) => {
    await loginToPanel(page);

    const invalidValues = await rawPanelApi(
      page,
      `${ROOT}/blueprints/fields/save`,
      {
        method: "POST",
        body: { values: "invalid" },
      },
    );
    expect(invalidValues.status).toBe(400);

    const unknownTopLevel = await rawPanelApi(
      page,
      `${ROOT}/blueprints/fields/save`,
      {
        method: "POST",
        body: { values: { text: "value" }, unexpected: true },
      },
    );
    expect(unknownTopLevel.status).toBe(400);
  });

  test("session mutations require a CSRF token", async ({ page }) => {
    await loginToPanel(page);

    const response = await rawPanelApi(page, `${ROOT}/blueprints/fields/save`, {
      method: "POST",
      body: { values: { text: "Denied without CSRF" } },
      csrf: false,
    });

    expect(response.status).toBe(401);
  });
});
