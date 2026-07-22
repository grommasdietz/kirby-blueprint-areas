import { expect, test, type Request, type Response } from "@playwright/test";
import { rawPanelApi } from "./utils/api";
import {
  areaFieldInput,
  discardPendingChanges,
  loginToPanel,
  openArea,
  waitForAreaSection,
} from "./utils/panel";

function areaRequest(request: Request, suffix: string) {
  const url = new URL(request.url());
  return (
    request.method() === "POST" &&
    url.pathname.endsWith(
      `/grommasdietz/blueprint-areas/blueprints/fields/${suffix}`,
    )
  );
}

function normalPageRequest(request: Request, suffix: string) {
  const url = new URL(request.url());
  return (
    request.method() === "POST" &&
    url.pathname.endsWith(`/pages/home/changes/${suffix}`)
  );
}

function areaResponse(response: Response, suffix: string) {
  return areaRequest(response.request(), suffix);
}

function normalPageResponse(response: Response, suffix: string) {
  return normalPageRequest(response.request(), suffix);
}

async function expectSuccessfulMutation(response: Promise<Response>) {
  const result = await response;
  expect(result.status()).toBe(200);
  return result.request();
}

test.describe("Blueprint Areas content lifecycle", () => {
  test("uses canonical payloads for draft, publish and discard", async ({
    page,
  }) => {
    await loginToPanel(page);
    const fieldsResponse = waitForAreaSection(page, "fields", "textfields");
    await openArea(page, "fields");
    expect((await fieldsResponse).status()).toBe(200);
    await discardPendingChanges(page);

    const input = areaFieldInput(page, "text");
    await expect(input).toBeVisible({ timeout: 15_000 });
    const original = await input.inputValue();
    const published = `Published ${Date.now()}`;

    let areaSaveRequests = 0;
    page.on("request", (request) => {
      if (areaRequest(request, "save")) {
        areaSaveRequests += 1;
      }
    });

    const draftResponse = page.waitForResponse((response) =>
      areaResponse(response, "save"),
    );
    await input.fill(published);
    const draft = await expectSuccessfulMutation(draftResponse);
    await expect.poll(() => areaSaveRequests).toBe(1);

    const draftPayload = draft.postDataJSON() as {
      values?: Record<string, unknown>;
    };
    expect(Object.keys(draftPayload)).toEqual(["values"]);
    expect(draftPayload.values?.text).toBe(published);

    const controls = page.locator(".k-form-controls");
    await expect(controls.getByRole("button", { name: "Save" })).toBeVisible();

    const publishResponse = page.waitForResponse((response) =>
      areaResponse(response, "publish"),
    );
    await controls.getByRole("button", { name: "Save" }).click();
    const publish = await expectSuccessfulMutation(publishResponse);
    const publishPayload = publish.postDataJSON() as {
      values?: Record<string, unknown>;
    };
    expect(Object.keys(publishPayload)).toEqual(["values"]);
    expect(publishPayload.values?.text).toBe(published);

    // Wait until the Panel has consumed the publish response and refreshed the
    // view before starting a real navigation. Waiting only for the request would
    // race the component's own view refresh and could abort the mutation.
    await expect(controls).toHaveCount(0, { timeout: 15_000 });

    const reloadedSection = waitForAreaSection(page, "fields", "textfields");
    await page.reload({ waitUntil: "domcontentloaded" });
    expect((await reloadedSection).status()).toBe(200);
    await expect(areaFieldInput(page, "text")).toHaveValue(published);

    const discarded = `Discarded ${Date.now()}`;
    const secondDraftResponse = page.waitForResponse((response) =>
      areaResponse(response, "save"),
    );
    await areaFieldInput(page, "text").fill(discarded);
    await expectSuccessfulMutation(secondDraftResponse);

    const discardResponse = page.waitForResponse((response) =>
      areaResponse(response, "discard"),
    );
    await page
      .locator(".k-form-controls")
      .getByRole("button", { name: "Discard" })
      .click();
    const dialog = page.getByRole("dialog");
    await expect(dialog).toBeVisible();
    await dialog.getByRole("button", { name: /Discard/i }).click();
    await expectSuccessfulMutation(discardResponse);

    await expect(dialog).toBeHidden();
    await expect(page.locator(".k-form-controls")).toHaveCount(0, {
      timeout: 15_000,
    });
    await expect(areaFieldInput(page, "text")).toHaveValue(published);

    // Restore the fixture through the authenticated API. Cleanup is not part
    // of the UI behavior under test and must not depend on transient form
    // controls that can disappear after autosave/view synchronization.
    const restore = await rawPanelApi(
      page,
      "grommasdietz/blueprint-areas/blueprints/fields/publish",
      {
        method: "POST",
        body: { values: { text: original } },
      },
    );
    expect(restore.status).toBe(200);
  });

  test("does not rewrite normal Kirby page content requests", async ({ page }) => {
    await loginToPanel(page);
    await page.goto("/panel/pages/home");
    await discardPendingChanges(page);

    const input = page.locator(".k-field-name-subtitle input").first();
    await expect(input).toBeVisible({ timeout: 15_000 });
    const original = await input.inputValue();
    const updated = `Normal page ${Date.now()}`;

    let normalSaveRequests = 0;
    page.on("request", (request) => {
      if (normalPageRequest(request, "save")) {
        normalSaveRequests += 1;
      }
    });

    const draftResponse = page.waitForResponse((response) =>
      normalPageResponse(response, "save"),
    );
    await input.fill(updated);
    const draft = await expectSuccessfulMutation(draftResponse);
    await expect.poll(() => normalSaveRequests).toBe(1);

    const payload = draft.postDataJSON() as Record<string, unknown>;
    expect(payload.subtitle).toBe(updated);
    expect(payload).not.toHaveProperty("values");
    expect(draft.url()).not.toContain("grommasdietz/blueprint-areas");

    const publishResponse = page.waitForResponse((response) =>
      normalPageResponse(response, "publish"),
    );
    await page
      .locator(".k-form-controls")
      .getByRole("button", { name: "Save" })
      .click();
    await expectSuccessfulMutation(publishResponse);
    await expect(page.locator(".k-form-controls")).toHaveCount(0, {
      timeout: 15_000,
    });

    // Restore the normal page fixture directly as well. The lifecycle above
    // already verified Kirby's native UI request path; cleanup should be
    // deterministic and independent of another autosave/control-render cycle.
    const restore = await rawPanelApi(page, "pages/home/changes/publish", {
      method: "POST",
      body: { subtitle: original },
    });
    expect(restore.status).toBe(200);
  });
});
