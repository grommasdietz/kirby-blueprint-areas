import { execSync } from "node:child_process";
import path from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));

const DEFAULT_EMAIL = "admin@kirby-blueprint-areas.test";
const DEFAULT_PASSWORD = "playwright";
const EDITOR_EMAIL = "editor@kirby-blueprint-areas.test";
const EDITOR_PASSWORD = "playwright";

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

  const createUserScript = path.join(root, "tools", "create-test-user.php");

  // Create admin test user
  execSync(
    `php ${createUserScript} --email="${env.KIRBY_USER_EMAIL}" --password="${env.KIRBY_USER_PASSWORD}" --role=admin`,
    { cwd: root, stdio: "inherit", env },
  );

  // Create editor test user for role-based access tests
  execSync(
    `php ${createUserScript} --email="${EDITOR_EMAIL}" --password="${EDITOR_PASSWORD}" --role=editor`,
    { cwd: root, stdio: "inherit", env },
  );
}
