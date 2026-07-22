import type { Page } from "@playwright/test";

interface RawApiOptions {
  method?: string;
  body?: unknown;
  csrf?: boolean;
}

interface RawApiResponse {
  status: number;
  url: string;
  json: unknown;
  text: string;
}

export async function rawPanelApi(
  page: Page,
  path: string,
  options: RawApiOptions = {},
): Promise<RawApiResponse> {
  return page.evaluate(
    async ({ path, options }) => {
      const panel = (
        window as unknown as Window & {
          panel: {
            api: {
              endpoint: string;
              csrf: string | null;
              language: string | null;
            };
          };
        }
      ).panel;
      const endpoint = panel.api.endpoint.replace(/\/$/, "");
      const headers: Record<string, string> = {
        "content-type": "application/json",
      };

      if (panel.api.language) {
        headers["x-language"] = panel.api.language;
      }
      if (options.csrf !== false && panel.api.csrf) {
        headers["x-csrf"] = panel.api.csrf;
      }

      const response = await fetch(`${endpoint}/${path.replace(/^\//, "")}`, {
        method: options.method ?? "GET",
        credentials: "same-origin",
        headers,
        body:
          options.body === undefined ? undefined : JSON.stringify(options.body),
      });
      const text = await response.text();
      let json: unknown;
      try {
        json = text === "" ? null : JSON.parse(text);
      } catch {
        json = null;
      }

      return {
        status: response.status,
        url: response.url,
        json,
        text,
      };
    },
    { path, options },
  );
}
