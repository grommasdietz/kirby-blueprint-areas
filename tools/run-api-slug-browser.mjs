import { spawnSync } from "node:child_process";

const command = process.platform === "win32" ? "pnpm.cmd" : "pnpm";
const result = spawnSync(
  command,
  ["exec", "playwright", "test", "tests/browser/api-slug.spec.ts"],
  {
    cwd: process.cwd(),
    stdio: "inherit",
    env: {
      ...process.env,
      KIRBY_API_SLUG: "control",
      PLAYWRIGHT_WEB_PORT: process.env.PLAYWRIGHT_API_SLUG_PORT ?? "8999",
    },
  },
);

if (result.error) {
  throw result.error;
}

process.exit(result.status ?? 1);
