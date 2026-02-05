import { execSync } from "node:child_process";
import path from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));

const DEFAULT_EMAIL = "admin@kirby-blueprint-areas.test";
const EDITOR_EMAIL = "editor@kirby-blueprint-areas.test";

function resolveRoot() {
  return path.resolve(__dirname, "..", "..");
}

export default async function globalTeardown() {
  const root = resolveRoot();
  const env = { ...process.env };
  const email = env.KIRBY_USER_EMAIL ?? DEFAULT_EMAIL;
  const deleteScript = path.join(root, "tools", "create-test-user.php");

  // Delete admin test user
  execSync(`php ${deleteScript} --delete --email="${email}"`, {
    cwd: root,
    stdio: "inherit",
    env,
  });

  // Delete editor test user
  execSync(`php ${deleteScript} --delete --email="${EDITOR_EMAIL}"`, {
    cwd: root,
    stdio: "inherit",
    env,
  });
}
