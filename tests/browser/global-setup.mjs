import { execSync } from "node:child_process";
import path from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));

const DEFAULT_EMAIL = "playwright@kirby-blueprint-areas.test";
const DEFAULT_PASSWORD = "playwright";

function resolveRoot() {
  return path.resolve(__dirname, "..", "..");
}

function ensureEnv(env) {
  if (!env.KIRBY_USER_EMAIL) {
    env.KIRBY_USER_EMAIL = DEFAULT_EMAIL;
  }
  if (!env.KIRBY_USER_PASSWORD) {
    env.KIRBY_USER_PASSWORD = DEFAULT_PASSWORD;
  }
}

export default async function globalSetup() {
  const root = resolveRoot();
  const env = { ...process.env };
  ensureEnv(env);

  execSync(
    `php ${path.join(root, "tools", "create-test-user.php")} --email="${env.KIRBY_USER_EMAIL}" --password="${env.KIRBY_USER_PASSWORD}"`,
    { cwd: root, stdio: "inherit", env },
  );
}
